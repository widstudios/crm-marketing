# Sistema modulare

> Come si compone un'applicazione a partire da moduli indipendenti, e cosa rende un modulo
> davvero disinstallabile.

---

## Indice

1. [Descrizione](#descrizione)
2. [Che cos'è un modulo](#che-cosè-un-modulo)
3. [Tipi di modulo](#tipi-di-modulo)
4. [Struttura](#struttura)
5. [Caricamento](#caricamento)
6. [Comunicazione tra moduli](#comunicazione-tra-moduli)
7. [Dipendenze](#dipendenze)
8. [Installazione e disinstallazione](#installazione-e-disinstallazione)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Un gestionale cresce per **aggiunta**: nuovi ambiti funzionali si affiancano a quelli esistenti nel
corso degli anni. Se le parti si conoscono a vicenda, dopo due anni non è più possibile aggiungere
nulla senza toccare tutto.

Il sistema modulare risolve questo, con un criterio verificabile: **un modulo si disinstalla e il
resto continua a funzionare**. Se disinstallandolo qualcosa si rompe, quel modulo non è
indipendente, e va corretto.

---

## Che cos'è un modulo

Un'unità funzionale completa e autonoma, con:

| Componente | Obbligatorio |
|---|---|
| Manifesto `module.json` | sì |
| Migration proprie | se ha entità |
| Codice stratificato (dominio, applicazione, infrastruttura, presentazione) | sì |
| Permessi dichiarati | sì |
| Traduzioni | sì |
| Test propri | sì |
| Documentazione | sì |
| Checklist | sì |

Un modulo **non** è: una cartella di comodo, un raggruppamento per tipo tecnico, o un insieme di
classi che «stanno bene insieme». È un ambito funzionale con un confine dichiarato.

---

## Tipi di modulo

| Tipo | Dove vive | Riuso | Esempi |
|---|---|---|---|
| **Core** | Foundation | tutti i progetti | tenancy, auth, audit |
| **Catalogo** | Factory, come pacchetto | più progetti | notifications, documents, reporting |
| **Dominio** | repository del progetto | un progetto | inventory, tickets, cases |

Un modulo di dominio che si rivela utile in tre progetti diventa candidato alla promozione nel
catalogo. Vedi [governance](../governance/README.md).

---

## Struttura

```
modules/<nome>/
├── module.json
├── README.md
├── database/
│   ├── migrations/tenant/
│   ├── factories/
│   └── seeders/
├── src/
│   ├── Domain/            entità, enum, VO, eventi, contratti
│   ├── Application/       Action, Query, DTO
│   ├── Infrastructure/    repository, integrazioni
│   ├── Http/              controller, request, resource
│   ├── Filament/          resource, widget, pagine
│   ├── Policies/
│   ├── Listeners/
│   └── Providers/
├── resources/
│   ├── views/
│   └── lang/{it,en}/
├── routes/{web,api}.php
├── tests/{Unit,Feature,Architecture}/
└── docs/{overview.md,checklist.md}
```

Il modulo replica **la stessa stratificazione** dell'applicazione: chi sa muoversi in `app/` sa
muoversi in qualunque modulo.

---

## Caricamento

```json
{
    "name": "inventory",
    "version": "1.2.0",
    "description": "Gestione articoli, lotti e movimenti di magazzino",
    "provider": "Modules\\Inventory\\Providers\\InventoryServiceProvider",
    "requires": ["core", "auth"],
    "optional": ["notifications", "reporting"],
    "database": "tenant",
    "permissions": [
        "article.view", "article.create", "article.update",
        "batch.view", "batch.create",
        "movement.view", "movement.create"
    ],
    "provides": ["stock-calculation", "expiry-monitoring"],
    "listens": ["notifications.channel-registered"]
}
```

| Campo | Significato |
|---|---|
| `requires` | senza questi il modulo non funziona |
| `optional` | se presenti, si integra; se assenti, funziona lo stesso |
| `database` | `tenant` o `landlord`: dove vanno le migration |
| `permissions` | seminati automaticamente |
| `provides` | capacità offerte agli altri moduli |
| `listens` | eventi a cui reagisce |

Il campo `optional` è ciò che rende possibile l'indipendenza: un modulo che *usa* le notifiche se
ci sono, ma non le *richiede*, resta installabile da solo.

L'ordine di caricamento in `config/modules.php` rispetta le dipendenze.

---

## Comunicazione tra moduli

Tre modalità, in ordine di preferenza.

### 1. Eventi (preferita)

```php
// Il modulo inventory emette
MovementRegistered::dispatch($movement->id);

// Il modulo notifications reagisce, se installato
final class NotifyOnLowStock implements ShouldQueue
{
    public function handle(MovementRegistered $event): void { /* … */ }
}
```

Accoppiamento **nullo**: chi emette non sa chi ascolta. Se il modulo che ascolta non è installato,
non accade nulla.

### 2. Contratti pubblicati

```php
// Il modulo inventory pubblica un contratto
namespace Modules\Inventory\Contracts;

interface StockReader
{
    public function availableFor(int $batchId): float;
}

// Il modulo reporting lo consuma, se disponibile
if (app()->bound(StockReader::class)) {
    $available = app(StockReader::class)->availableFor($batchId);
}
```

Accoppiamento **debole**: il consumatore dipende da un'interfaccia, non da un'implementazione, e
verifica che sia disponibile.

### 3. Accesso diretto (vietato)

```php
// ✗ Il modulo reporting importa un model di inventory
use Modules\Inventory\Domain\Models\Batch;

$batches = Batch::query()->expiringWithin(30)->get();
```

Accoppiamento **forte**: `reporting` non è più installabile senza `inventory`, e ogni modifica
interna a `inventory` può romperlo.

Verifica automatica:

```php
arch('i moduli non accedono direttamente ai model di altri moduli')
    ->expect('Modules\Reporting')
    ->not->toUse('Modules\Inventory\Domain\Models');
```

---

## Dipendenze

```
        ┌──────────┐
        │   core   │  ← nessuna dipendenza
        └────┬─────┘
             │
      ┌──────┴──────┐
      ▼             ▼
 ┌────────┐   ┌──────────┐
 │  auth  │   │  audit   │
 └───┬────┘   └──────────┘
     │
     ├──────────────┬──────────────┐
     ▼              ▼              ▼
┌──────────┐  ┌──────────┐  ┌──────────────┐
│inventory │  │ documents│  │notifications │
└────┬─────┘  └──────────┘  └──────────────┘
     │
     ▼ (facoltativa)
┌──────────┐
│reporting │
└──────────┘
```

Regole:

| Regola | Motivo |
|---|---|
| Nessuna dipendenza circolare | impedirebbe l'ordine di caricamento |
| Le dipendenze obbligatorie sono minime | ogni `requires` riduce l'indipendenza |
| Le integrazioni facoltative usano eventi | funzionano se il modulo c'è, tacciono se non c'è |
| Un modulo di catalogo non dipende da uno di dominio | il generale non conosce il particolare |

---

## Installazione e disinstallazione

```bash
php artisan module:install inventory
php artisan module:list
php artisan module:disable inventory
php artisan module:uninstall inventory --keep-data
```

Installazione:

1. verifica delle dipendenze obbligatorie;
2. registrazione del provider;
3. migration del modulo su tutti i tenant;
4. seeder dei permessi;
5. pubblicazione degli asset;
6. attivazione in `config/modules.php`.

Disinstallazione:

1. verifica che nessun modulo installato lo richieda;
2. disattivazione del provider;
3. rimozione dei permessi;
4. rollback delle migration (facoltativo, con `--keep-data` i dati restano);
5. rimozione dalla configurazione.

**Il test di disinstallazione è il vero criterio di indipendenza**: se disinstallando un modulo la
suite degli altri fallisce, quel modulo non era indipendente.

---

## Esempi

### Esempio 1 — integrazione facoltativa fatta bene

Il modulo `inventory` vuole notificare i lotti in scadenza, ma deve funzionare anche senza
`notifications`.

```php
// inventory emette l'evento, sempre
BatchExpiringSoon::dispatch($batch->id, $daysLeft);
```

```php
// notifications, se installato, registra il listener nel proprio provider
Event::listen(BatchExpiringSoon::class, SendExpiryNotification::class);
```

Senza `notifications`, l'evento viene emesso e nessuno lo ascolta. Nessun errore, nessuna
dipendenza.

### Esempio 2 — accoppiamento da correggere

`reporting` importa `Modules\Inventory\Domain\Models\Batch` per costruire un report.

Correzione: `inventory` pubblica il contratto `StockReader`; `reporting` lo consuma se disponibile
e altrimenti omette quella sezione del report.

---

## Best practice

- Un modulo = un ambito funzionale, non un raggruppamento tecnico.
- Comunicazione a eventi come prima scelta.
- Dipendenze obbligatorie ridotte al minimo.
- Integrazioni facoltative sempre tramite eventi o contratti verificati.
- Provare periodicamente la disinstallazione: è la verifica dell'indipendenza.
- Dichiarare tutto nel manifesto: dipendenze, permessi, capacità, eventi ascoltati.
- Migration del modulo reversibili.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Import diretto di model di altri moduli | Moduli non più disinstallabili | Eventi o contratti |
| Dipendenza circolare | Ordine di caricamento impossibile | Ripensare i confini |
| Tutto obbligatorio in `requires` | Nessuna indipendenza reale | Distinguere `optional` |
| Permessi non dichiarati nel manifesto | Non vengono seminati | Dichiararli |
| Migration non reversibili | Disinstallazione impossibile | `down()` sempre |
| Modulo che è solo una cartella | Nessun confine reale | Ambito funzionale con manifesto |
| Modulo di catalogo che dipende dal dominio | Non riutilizzabile altrove | Invertire la dipendenza |

---

## Checklist

- [ ] Il modulo ha un manifesto completo.
- [ ] Le dipendenze obbligatorie sono minime e giustificate.
- [ ] Le integrazioni facoltative passano da eventi o contratti verificati.
- [ ] Nessun import diretto di model di altri moduli.
- [ ] Nessuna dipendenza circolare.
- [ ] Permessi dichiarati e seminati.
- [ ] Migration reversibili.
- [ ] Il modulo si disinstalla senza rompere gli altri.
- [ ] Test di architettura sulle dipendenze tra moduli.

---

## Riferimenti

- [Contratto di modulo](11-module-contract.md)
- [ADR-0004 — Sistema modulare](decisions/0004-modular-system.md)
- [Blueprint di modulo](../modules/_blueprint/README.md) · [Catalogo](../modules/README.md)
- [Eventi](21-events-and-messaging.md)
