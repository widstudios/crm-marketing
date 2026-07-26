# Templates — Testing

> Gli stub delle quattro categorie di test: unitari, di feature, di isolamento, di architettura.

---

## Indice

1. [Descrizione](#descrizione) 2. [Gli stub](#gli-stub) 3. [Le quattro categorie](#le-quattro-categorie)
4. [I tre test di ogni operazione](#i-tre-test-di-ogni-operazione) 5. [Esempi](#esempi)
6. [Best practice](#best-practice) 7. [Errori comuni](#errori-comuni) 8. [Checklist](#checklist)
9. [Riferimenti](#riferimenti)

---

## Descrizione

I test hanno due funzioni: verificare che il codice funzioni oggi, e permettere di modificarlo
domani. In un sistema che vive anni e viene modificato da persone e agenti diversi, la seconda vale
più della prima.

Gli stub di questa cartella sono costruiti attorno ai due test che nessuno scrive spontaneamente:
quello di **autorizzazione negata** e quello di **isolamento tra tenant**. Sono anche i due che
proteggono dai difetti peggiori, e non è una coincidenza: verificano che qualcosa **non** accada, e
le cose che non accadono non si vedono.

---

## Gli stub

| Stub | Categoria | Quando |
|---|---|---|
| [UnitTest.php.stub](UnitTest.php.stub) | `tests/Unit/` | enum, value object, calcoli, politiche di dominio |
| [FeatureTest.php.stub](FeatureTest.php.stub) | `tests/Feature/` | ogni operazione, attraverso i livelli |
| [TenantIsolationTest.php.stub](TenantIsolationTest.php.stub) | `tests/Tenant/` | **ogni entità di dominio** |
| [ArchitectureTest.php.stub](ArchitectureTest.php.stub) | `tests/Architecture/` | dal primo giorno del progetto |

---

## Le quattro categorie

| Cartella | Natura | Database | Velocità |
|---|---|---|---|
| `tests/Unit/` | classe in isolamento | nessuno | < 1 ms |
| `tests/Feature/` | comportamento end-to-end | SQLite in memoria | 10-100 ms |
| `tests/Tenant/` | isolamento tra tenant | multi-tenant reale | 100-500 ms |
| `tests/Architecture/` | vincoli strutturali | nessuno | < 1 ms |

Soglie di copertura: **80%** complessiva, **100%** su Action, Policy, value object ed enum di
dominio. La soglia è doppia perché una copertura media alta può nascondere un buco esattamente dove
conta.

---

## I tre test di ogni operazione

1. **percorso corretto** — l'operazione fa ciò che deve;
2. **violazione di una regola di dominio** — l'operazione viene rifiutata;
3. **autorizzazione negata** — chi non ha il permesso non passa.

Il terzo è quello che si dimentica. È anche quello che, quando manca, lascia passare i difetti più
gravi: un endpoint aperto non produce errori, produce accessi.

Per le API se ne aggiunge un quarto: **risorsa inesistente**, che per una risorsa di un altro tenant
deve rispondere `404` e non `403`.

---

## Esempi

### I tre test, per esteso

```php
it('registra uno scarico e aggiorna la giacenza', function (): void {
    $batch = Batch::factory()->create(['quantity' => 100]);

    actingAs(userWithPermission('movement.create'))
        ->postJson("/api/v1/batches/{$batch->id}/movements", ['type' => 'outbound', 'quantity' => 10])
        ->assertCreated();

    expect($batch->fresh()->quantity)->toEqual(90);
});

it('rifiuta lo scarico oltre la disponibilità', function (): void {
    $batch = Batch::factory()->create(['quantity' => 5]);

    actingAs(userWithPermission('movement.create'))
        ->postJson("/api/v1/batches/{$batch->id}/movements", ['type' => 'outbound', 'quantity' => 10])
        ->assertStatus(422);
});

it('nega a chi non ha il permesso', function (): void {
    $batch = Batch::factory()->create();

    actingAs(userWithoutPermissions())
        ->postJson("/api/v1/batches/{$batch->id}/movements", ['type' => 'inbound', 'quantity' => 1])
        ->assertForbidden();
});
```

### Il test che non serve

```php
it('assegna il nome', function (): void {
    $supplier = new Supplier();
    $supplier->name = 'ACME';
    expect($supplier->name)->toBe('ACME');
});
```

Verifica il funzionamento di PHP. Non può fallire per un difetto reale, alza la copertura e non
protegge da nulla — cioè fa esattamente il danno peggiore che un test possa fare.

---

## Best practice

- Scrivere per primo il test del percorso di errore.
- Scrivere il test di isolamento **insieme** all'entità, non alla fine del progetto.
- Verificare gli effetti collaterali: evento emesso, job accodato, audit scritto.
- Falsificare i test ogni tanto: se non falliscono quando il codice si rompe, non servono.
- Usare helper condivisi (`userWithPermission`, `createTenant`) invece di ripetere l'impianto.
- Aggiungere un test di architettura quando lo stesso rilievo compare in revisione due volte.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Nessun test di autorizzazione negata | I difetti più gravi passano | Tre test per operazione |
| Nessun test di isolamento | Rischio di fuga di dati | Obbligatorio per entità |
| Un solo tenant nel test di isolamento | Non verifica nulla | Due tenant reali |
| Test sull'implementazione | Rotti a ogni refactoring | Comportamento osservabile |
| Test che verificano il framework | Copertura alta, valore nullo | Rimuoverli |
| Solo il codice di stato verificato | Un 201 con database invariato passa | Verificare l'effetto |
| Effetti collaterali non verificati | Evento mancante che nessuno nota | `assertDispatched` |
| Job idempotente provato una volta | L'idempotenza è dichiarata, non verificata | Eseguirlo due volte |
| `sleep()` nei test | Suite lenta e instabile | `travel()` |
| Chiamate esterne reali | Fallimenti dipendenti dalla rete | `Http::fake()` |
| Test di architettura aggiunti tardi | Cinquanta violazioni insieme | Dal primo giorno |

---

## Checklist

- [ ] Le quattro cartelle esistono.
- [ ] Tre test per ogni operazione, quattro per gli endpoint API.
- [ ] Un test di isolamento per ogni entità, con due tenant reali.
- [ ] Test di isolamento della cache e del contesto nei job.
- [ ] Test di architettura presenti dal primo giorno.
- [ ] Copertura 100% su Action, Policy, value object ed enum di dominio.
- [ ] Nessun `sleep()`, nessuna chiamata esterna reale.
- [ ] La suite è verde su SQLite e su MySQL, in ordine casuale.

---

## Riferimenti

- [Regole di testing](../../rules/testing.md) · [ADR-0007](../../architecture/decisions/0007-testing-strategy.md)
- [Foundation — Testing](../../foundation/docs/07-testing.md)
- [Testing Agent](../../agents/13-testing-agent.md) · [Checklist di testing](../../checklists/testing-checklist.md)
- [Strategia di testing](../../docs/04-quality/01-testing-strategy.md)
