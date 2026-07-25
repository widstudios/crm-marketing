# Contratto di modulo

> Cosa un modulo deve dichiarare, cosa deve garantire e cosa può aspettarsi dagli altri.

---

## Indice

1. [Descrizione](#descrizione)
2. [Il manifesto](#il-manifesto)
3. [Cosa il modulo garantisce](#cosa-il-modulo-garantisce)
4. [Cosa il modulo può aspettarsi](#cosa-il-modulo-può-aspettarsi)
5. [Superficie pubblica](#superficie-pubblica)
6. [Versionamento](#versionamento)
7. [Verifica del contratto](#verifica-del-contratto)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

Il contratto di modulo è l'insieme di garanzie reciproche che rendono possibile comporre
un'applicazione senza che le parti si conoscano in dettaglio.

Senza contratto, «modulo» significa solo «cartella»: la separazione è apparente, e la prima
scadenza stretta la fa saltare.

---

## Il manifesto

`module.json` è la dichiarazione formale del contratto.

```json
{
    "name": "inventory",
    "version": "1.2.0",
    "description": "Gestione articoli, lotti e movimenti di magazzino",
    "provider": "Modules\\Inventory\\Providers\\InventoryServiceProvider",

    "requires": ["core", "auth"],
    "optional": ["notifications", "reporting"],
    "conflicts": [],

    "database": "tenant",

    "permissions": [
        "article.view", "article.create", "article.update", "article.delete",
        "batch.view", "batch.create",
        "movement.view", "movement.create"
    ],

    "provides": {
        "contracts": ["Modules\\Inventory\\Contracts\\StockReader"],
        "capabilities": ["stock-calculation", "expiry-monitoring"]
    },

    "emits": [
        "Modules\\Inventory\\Domain\\Events\\MovementRegistered",
        "Modules\\Inventory\\Domain\\Events\\BatchExpiringSoon"
    ],

    "listens": [
        "Modules\\Auth\\Domain\\Events\\UserDeactivated"
    ],

    "settings": {
        "expiry_warning_days": { "type": "integer", "default": 30 },
        "allow_negative_stock": { "type": "boolean", "default": false }
    }
}
```

| Sezione | Serve a |
|---|---|
| `requires` / `optional` / `conflicts` | risoluzione delle dipendenze |
| `permissions` | seeding automatico |
| `provides` | ciò che gli altri possono usare |
| `emits` / `listens` | mappa delle integrazioni |
| `settings` | configurazione per tenant, con tipo e default |

`emits` e `listens` insieme producono una mappa delle integrazioni dell'intera applicazione:
è la documentazione che nessuno scrive a mano e che serve sempre.

---

## Cosa il modulo garantisce

| # | Garanzia | Verifica |
|---|---|---|
| 1 | Si installa e si disinstalla senza rompere gli altri | test di disinstallazione |
| 2 | Le sue migration sono reversibili | rollback in CI |
| 3 | Non accede ai model di altri moduli | test di architettura |
| 4 | Dichiara tutti i permessi che usa | confronto manifesto ↔ codice |
| 5 | Funziona senza i moduli `optional` | suite eseguita senza di essi |
| 6 | Ha test propri, che girano in isolamento | esecuzione della suite del modulo |
| 7 | Traduce ogni stringa visibile | analisi delle viste |
| 8 | Non rompe la propria superficie pubblica entro la major | confronto delle interfacce |
| 9 | Documenta le proprie decisioni | presenza di `docs/overview.md` |
| 10 | Rispetta le regole della Factory | `composer qa` |

La garanzia 5 è la più trascurata e la più importante: un modulo che *sembra* avere dipendenze
facoltative ma non funziona senza di esse non è indipendente.

---

## Cosa il modulo può aspettarsi

Dalla piattaforma:

| Aspettativa | Fornito da |
|---|---|
| Contesto tenant risolto | Foundation |
| Utente autenticato (dove previsto) | modulo `auth` |
| Sistema di permessi funzionante | modulo `auth` |
| Connessione al database corretta | Foundation |
| Cache tenant-scoped | Foundation |
| Storage per tenant | Foundation |
| Code con contesto | Foundation |
| Audit automatico sulle entità marcate | modulo `audit` |
| Traduzioni caricate | Foundation |

Dagli altri moduli: **nulla**, se non ciò che dichiarano in `provides` e solo dopo aver verificato
che siano disponibili.

---

## Superficie pubblica

Un modulo ha una superficie pubblica ristretta e dichiarata.

| Pubblico (usabile da altri moduli) | Privato (interno) |
|---|---|
| Contratti in `Contracts/` | model, repository, Action |
| Eventi in `Domain/Events/` | Query, DTO interni |
| Enum di dominio esposti | servizi interni |
| Comandi Artisan documentati | listener |
| Configurazione in `settings` | viste e componenti |

Ciò che è privato si marca esplicitamente:

```php
/**
 * @internal Uso riservato al modulo inventory. Può cambiare in qualsiasi versione.
 */
final readonly class StockCalculator { /* … */ }
```

Solo ciò che è pubblico è soggetto alle garanzie di compatibilità: è questa distinzione che
permette di rifattorizzare l'interno di un modulo senza coordinarsi con gli altri.

---

## Versionamento

Ogni modulo ha una versione propria, in semver, secondo l'impatto sulla **superficie pubblica**.

| Modifica | Versione |
|---|---|
| Nuovo contratto o evento | MINOR |
| Nuovo metodo su un contratto esistente | **MAJOR** (rompe gli implementatori) |
| Cambio del payload di un evento pubblicato | **MAJOR** |
| Rimozione di un contratto o di un evento | **MAJOR** |
| Nuova impostazione con default | MINOR |
| Cambio del default di un'impostazione | **MAJOR** se cambia il comportamento |
| Refactoring interno | PATCH |
| Nuova entità o funzionalità interna | MINOR |
| Correzione | PATCH |

---

## Verifica del contratto

```php
arch('il modulo non accede ai model di altri moduli')
    ->expect('Modules\Inventory')
    ->not->toUse([
        'Modules\Documents\Domain\Models',
        'Modules\Notifications\Domain\Models',
    ]);

arch('le classi interne sono marcate')
    ->expect('Modules\Inventory\Application')
    ->toBeFinal();

it('dichiara tutti i permessi che usa', function (): void {
    $declared = collect(json_decode(file_get_contents(module_path('inventory', 'module.json')), true)['permissions']);
    $used = collect(permissionsUsedIn(module_path('inventory', 'src')));

    expect($used->diff($declared))->toBeEmpty();
});

it('funziona senza i moduli facoltativi', function (): void {
    withoutModules(['notifications', 'reporting']);

    $this->artisan('test', ['--testsuite' => 'inventory'])->assertSuccessful();
});
```

---

## Esempi

### Esempio 1 — contratto pubblicato e consumato

```php
// inventory dichiara ciò che offre
namespace Modules\Inventory\Contracts;

interface StockReader
{
    public function availableFor(int $batchId): float;

    /** @return array<int, array{batch_id: int, days_left: int}> */
    public function expiringWithin(int $days): array;
}
```

```php
// reporting lo consuma, verificando la disponibilità
final readonly class StockReportQuery
{
    public function execute(): array
    {
        if (! app()->bound(StockReader::class)) {
            return ['available' => false];   // sezione omessa dal report
        }

        return ['available' => true, 'data' => app(StockReader::class)->expiringWithin(30)];
    }
}
```

### Esempio 2 — violazione del contratto

Il modulo `reporting` importa `Modules\Inventory\Domain\Models\Batch` per «fare prima».

Conseguenze: `reporting` non è più installabile senza `inventory`; una modifica interna al model
`Batch` rompe `reporting` senza preavviso; il test di architettura fallisce.

---

## Best practice

- Dichiarare tutto nel manifesto: dipendenze, permessi, eventi, impostazioni.
- Mantenere ristretta la superficie pubblica.
- Marcare come `@internal` tutto ciò che non è pubblico.
- Verificare periodicamente il funzionamento senza i moduli facoltativi.
- Versionare secondo l'impatto sulla superficie pubblica.
- Documentare gli eventi emessi: sono un contratto quanto le interfacce.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Manifesto incompleto | Dipendenze non risolte, permessi mancanti | Dichiarare tutto |
| Superficie pubblica ampia | Impossibile rifattorizzare l'interno | Restringere e marcare `@internal` |
| Modulo che non funziona senza gli `optional` | Falsa indipendenza | Test senza moduli facoltativi |
| Payload di evento cambiato in minor | Ascoltatori rotti senza preavviso | MAJOR |
| Metodo aggiunto a un contratto in minor | Implementatori rotti | MAJOR |
| Permessi usati ma non dichiarati | Non seminati, funzionalità invisibile | Verifica automatica |

---

## Checklist

- [ ] Manifesto completo e valido.
- [ ] Dipendenze obbligatorie minime, facoltative dichiarate.
- [ ] Tutti i permessi usati sono dichiarati.
- [ ] Eventi emessi e ascoltati dichiarati.
- [ ] Superficie pubblica ristretta; l'interno è marcato `@internal`.
- [ ] Il modulo funziona senza i moduli facoltativi.
- [ ] Migration reversibili.
- [ ] Test di architettura sulle dipendenze.
- [ ] Versione coerente con l'impatto sulla superficie pubblica.

---

## Riferimenti

- [Sistema modulare](10-modular-system.md)
- [Blueprint di modulo](../modules/_blueprint/README.md) · [Catalogo](../modules/README.md)
- [Eventi](21-events-and-messaging.md)
- [Versionamento della Factory](../governance/versioning.md)
