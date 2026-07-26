# Regole — Testing

> Categorie obbligatorie, soglie di copertura, e i tre test che ogni operazione deve avere.

---

## Indice

1. [Descrizione](#descrizione)
2. [Categorie](#categorie)
3. [Soglie di copertura](#soglie-di-copertura)
4. [Regole generali](#regole-generali)
5. [Test unitari](#test-unitari)
6. [Test di feature](#test-di-feature)
7. [Test di isolamento tenant](#test-di-isolamento-tenant)
8. [Test di architettura](#test-di-architettura)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

I test hanno due funzioni: verificare che il codice funzioni oggi, e permettere di modificarlo
domani. In un sistema che vive anni e viene modificato da persone e agenti diversi, la seconda vale
più della prima.

Decisione di riferimento: [ADR-0007](../architecture/decisions/0007-testing-strategy.md).

---

## Categorie

| Cartella | Natura | Database | Velocità |
|---|---|---|---|
| `tests/Unit/` | classe in isolamento | nessuno | < 1 ms |
| `tests/Feature/` | comportamento end-to-end | SQLite in memoria | 10-100 ms |
| `tests/Tenant/` | isolamento tra tenant | multi-tenant reale | 100-500 ms |
| `tests/Architecture/` | vincoli strutturali | nessuno | < 1 ms |

---

## Soglie di copertura

| Ambito | Soglia | Bloccante |
|---|---|---|
| Complessiva | ≥ 80% | sì |
| Action | **100%** | sì |
| Policy | **100%** | sì |
| Value object ed enum di dominio | **100%** | sì |
| Controller | ≥ 70% | sì |
| Filament Resource | non conteggiata | — |
| Migration, config, provider | escluse | — |

**R1.** La soglia è **doppia**: complessiva e per ambito critico.
*Motivo:* una copertura media alta può nascondere un buco esattamente dove conta.
*Verifica:* pipeline con filtro sui namespace. *Livello: vincolante.*

---

## Regole generali

**R2.** Nessun artefatto di codice viene consegnato senza test.
*Verifica:* revisione. *Livello: vincolante.*

**R3.** Ogni bug produce **prima** un test rosso, poi la correzione.
*Motivo:* dimostra di aver capito il problema e impedisce il ritorno del difetto.
*Verifica:* revisione della PR di correzione.

**R4.** Per ogni operazione servono almeno **tre** test: percorso corretto, violazione di una regola
di dominio, autorizzazione negata.
*Motivo:* il terzo è quello che si dimentica, e protegge dai difetti più gravi.
*Verifica:* revisione.

**R5.** Il nome del test descrive il **comportamento**, in italiano.

```php
it('nega lo scarico da un lotto scaduto', function (): void { /* … */ });
```

*Verifica:* revisione.

**R6.** I test verificano il **comportamento osservabile**, non l'implementazione.
*Motivo:* i test sull'implementazione si rompono ad ogni refactoring.
*Verifica:* revisione.

**R7.** Nessun test dipende dall'ordine di esecuzione o dallo stato lasciato da un altro.
*Verifica:* esecuzione in ordine casuale in CI.

**R8.** Nessun `sleep()`: si manipola il tempo con `travel()`.
*Verifica:* ricerca in CI.

**R9.** Le dipendenze esterne sono simulate: `Http::fake()`, `Mail::fake()`, `Queue::fake()`,
`Storage::fake()`.
*Verifica:* revisione, assenza di traffico di rete in CI.

**R10.** La suite gira su **SQLite e MySQL** in pipeline.
*Verifica:* configurazione della pipeline. *Livello: vincolante.*

**R11.** La suite completa resta sotto i **3 minuti**; quella unitaria sotto i 10 secondi.
*Motivo:* una suite lenta viene eseguita di rado, e non protegge da nulla.
*Verifica:* metriche di pipeline.

---

## Test unitari

**R12.** Un test unitario non tocca il database né avvia il framework.
*Motivo:* se ne ha bisogno, la classe ha una dipendenza che non dovrebbe avere.
*Verifica:* configurazione della suite.

**R13.** Candidati obbligatori: enum di dominio, value object, calcoli, politiche di dominio.
*Verifica:* copertura al 100% su questi namespace.

**R14.** I test parametrici (`with()`) si usano per i casi limite dello stesso comportamento.

```php
it('rifiuta una partita IVA con formato errato', function (string $value): void {
    expect(fn () => new VatNumber($value))->toThrow(InvalidVatNumber::class);
})->with(['', 'IT123', 'FR12345678901', '12345678901']);
```

---

## Test di feature

**R15.** Verificano un comportamento completo attraverso i livelli, non un singolo metodo.
*Verifica:* revisione.

**R16.** Ogni endpoint API ha test per: risposta corretta, validazione fallita, autorizzazione
negata, risorsa inesistente.
*Verifica:* revisione.

**R17.** Ogni azione Filament personalizzata ha un test.
*Motivo:* è il punto in cui l'autorizzazione non è automatica.
*Verifica:* revisione.

**R18.** I test verificano anche gli **effetti collaterali**: evento emesso, job accodato, audit
scritto.

```php
Event::assertDispatched(MovementRegistered::class);
Queue::assertPushed(RecalculateStockJob::class);
```

---

## Test di isolamento tenant

**R19.** Ogni entità di dominio ha un test di isolamento.
*Verifica:* script di verifica. *Livello: assoluto.*

**R20.** Il test crea **almeno due** tenant reali e verifica l'invisibilità reciproca.

```php
it('non espone i movimenti di un altro tenant', function (): void {
    $acme = createTenant('acme');
    $globex = createTenant('globex');

    tenancy()->run($acme, fn () => StockMovement::factory()->count(5)->create());

    tenancy()->run($globex, function (): void {
        expect(StockMovement::query()->count())->toBe(0);
    });
});
```

**R21.** Esiste un test di isolamento della **cache**.
*Verifica:* script di verifica. *Livello: assoluto.*

**R22.** Esiste un test che verifica il ripristino del contesto tenant nei job.
*Verifica:* script di verifica.

**R23.** L'accesso a una risorsa di un altro tenant via API ritorna `404`, non `403`.
*Verifica:* test dedicato.

---

## Test di architettura

**R24.** I test di architettura esistono dal primo giorno del progetto.
*Motivo:* aggiungerli dopo significa scoprire cinquanta violazioni insieme.
*Verifica:* presenza in `tests/Architecture/`. *Livello: vincolante.*

Insieme minimo obbligatorio:

```php
arch('il dominio non dipende dal framework')
    ->expect('App\Domain')->not->toUse('Illuminate');

arch('le action sono final con un solo metodo pubblico')
    ->expect('App\Application')->classes()->toHaveSuffix('Action')
    ->toBeFinal()->toHaveMethod('execute');

arch('i DTO sono readonly')
    ->expect('App\Application')->classes()->toHaveSuffix('Data')->toBeReadonly();

arch('i controller non usano i repository')
    ->expect('App\Http\Controllers')->not->toUse('App\Infrastructure');

arch('nessun helper di debug residuo')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])->not->toBeUsed();

arch('tipizzazione stretta in ogni file')
    ->expect('App')->toUseStrictTypes();
```

---

## Esempi

### Esempio 1 — i tre test di un'operazione

```php
it('registra uno scarico e aggiorna la giacenza', function (): void {
    $batch = Batch::factory()->create(['quantity' => 100]);

    $this->actingAs(userWithPermission('movement.create'))
        ->postJson("/api/v1/batches/{$batch->id}/movements", ['type' => 'outbound', 'quantity' => 10])
        ->assertCreated();

    expect($batch->fresh()->quantity)->toEqual(90);
});

it('rifiuta lo scarico oltre la disponibilità', function (): void {
    $batch = Batch::factory()->create(['quantity' => 5]);

    $this->actingAs(userWithPermission('movement.create'))
        ->postJson("/api/v1/batches/{$batch->id}/movements", ['type' => 'outbound', 'quantity' => 10])
        ->assertStatus(422);
});

it('nega a chi non ha il permesso', function (): void {
    $batch = Batch::factory()->create();

    $this->actingAs(userWithoutPermissions())
        ->postJson("/api/v1/batches/{$batch->id}/movements", ['type' => 'inbound', 'quantity' => 1])
        ->assertForbidden();
});
```

### Esempio 2 — test che non serve

```php
// Verifica il funzionamento di PHP, non del dominio: non può fallire per un difetto reale
it('assegna il nome', function (): void {
    $supplier = new Supplier();
    $supplier->name = 'ACME';
    expect($supplier->name)->toBe('ACME');
});
```

---

## Best practice

- Scrivere il test insieme al codice, non alla fine.
- Iniziare dal test del percorso di errore: è quello che si dimentica.
- Falsificare i test ogni tanto: se un test non fallisce quando si rompe il codice, non serve.
- Usare helper di test (`userWithPermission`, `createTenant`) per ridurre la ripetizione.
- Misurare la durata della suite: se cresce, individuare i test lenti.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Nessun test di autorizzazione | I difetti più gravi passano | Tre test per operazione |
| Nessun test di isolamento tenant | Rischio di fuga di dati | Obbligatorio per entità |
| Testare l'implementazione | Test rotti ad ogni refactoring | Comportamento osservabile |
| Copertura alta su codice banale | Falsa sicurezza | Soglia doppia |
| Test dipendenti dall'ordine | Fallimenti intermittenti | Isolamento completo |
| `sleep()` nei test | Suite lenta e instabile | `travel()` |
| Chiamate esterne reali | Test fragili, dipendenti dalla rete | `Http::fake()` |
| Bug corretto senza test | Il difetto torna | Test rosso prima |
| Test di architettura aggiunti tardi | Cinquanta violazioni insieme | Dal primo giorno |

---

## Checklist

- [ ] Copertura complessiva ≥ 80%.
- [ ] Action, Policy, value object ed enum al 100%.
- [ ] Tre test per ogni operazione.
- [ ] Un test di isolamento per ogni entità.
- [ ] Test di isolamento della cache presente.
- [ ] Test sul contesto tenant nei job.
- [ ] Test di architettura presenti e verdi.
- [ ] Nessun test dipendente dall'ordine.
- [ ] Nessun `sleep()`, nessuna chiamata esterna reale.
- [ ] Suite eseguita su SQLite e MySQL.
- [ ] Suite completa sotto i 3 minuti.

---

## Riferimenti

- [ADR-0007](../architecture/decisions/0007-testing-strategy.md)
- [Strategia di testing](../docs/04-quality/01-testing-strategy.md)
- [Agente di testing](../agents/13-testing-agent.md)
- [Template di test](../templates/testing/README.md)
- [Checklist di testing](../checklists/testing-checklist.md)
