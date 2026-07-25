# Strategia di testing

> Che cosa si testa, a quale livello, con quale profondità, e perché in un'applicazione
> multitenant alcuni test sono obbligatori e non negoziabili.

---

## Indice

1. [Descrizione](#descrizione)
2. [La piramide](#la-piramide)
3. [Test unitari](#test-unitari)
4. [Test di feature](#test-di-feature)
5. [Test di isolamento tenant](#test-di-isolamento-tenant)
6. [Test di architettura](#test-di-architettura)
7. [Copertura](#copertura)
8. [Dati di test](#dati-di-test)
9. [Test lenti e come evitarli](#test-lenti-e-come-evitarli)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

I test hanno due funzioni distinte, ed è utile tenerle separate: verificano che il codice funzioni
**oggi**, e permettono di modificarlo **domani** senza paura. La seconda vale più della prima in
un sistema che vive anni.

Il criterio di valore di un test non è la copertura che produce: è **quale difetto intercetta**.
Un test che non può fallire è un costo di manutenzione senza contropartita.

---

## La piramide

```
             ╱╲          Architettura   ~20 test, istantanei
            ╱  ╲                        vincoli strutturali
           ╱────╲
          ╱      ╲       Isolamento     1 per entità, obbligatori
         ╱        ╲                     nessun dato tra tenant
        ╱──────────╲
       ╱            ╲    Feature        ~40% dei test
      ╱              ╲                  comportamento end-to-end
     ╱────────────────╲
    ╱                  ╲  Unit          ~55% dei test, istantanei
   ╱────────────────────╲               regole di dominio
```

| Livello | Velocità | Database | Cosa verifica |
|---|---|---|---|
| Unit | < 1 ms | no | regole di dominio, calcoli, transizioni |
| Feature | 10-100 ms | sì | l'operazione completa attraverso i livelli |
| Isolamento | 100-500 ms | sì, multi-tenant | che un tenant non veda l'altro |
| Architettura | < 1 ms | no | dipendenze, naming, struttura |

---

## Test unitari

Verificano una classe **senza framework e senza database**. Se un test unitario ha bisogno del
database, o non è unitario o la classe ha una dipendenza che non dovrebbe avere.

```php
it('non ammette transizioni da archiviato', function (): void {
    expect(SupplierStatus::Archived->canTransitionTo(SupplierStatus::Active))->toBeFalse();
});

it('rifiuta una partita IVA con formato errato', function (string $value): void {
    expect(fn () => new VatNumber($value))->toThrow(InvalidVatNumber::class);
})->with(['', 'IT123', 'FR12345678901', '12345678901']);
```

Candidati naturali: enum con transizioni, value object, calcoli, politiche di dominio, mappature.

---

## Test di feature

Verificano un comportamento completo, dal punto di ingresso al database.

```php
it('registra un movimento di scarico e aggiorna la giacenza', function (): void {
    $batch = Batch::factory()->create(['quantity' => 100]);

    $this->actingAs(userWithPermission('movement.create'))
        ->postJson("/api/v1/batches/{$batch->id}/movements", [
            'type' => 'outbound',
            'quantity' => 10,
        ])
        ->assertCreated();

    expect($batch->fresh()->available())->toBe(90.0);
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

Per ogni operazione servono almeno tre test: percorso corretto, violazione di una regola di
dominio, autorizzazione negata. La terza è quella che si dimentica più spesso, ed è quella che
protegge dai difetti più gravi.

---

## Test di isolamento tenant

**Obbligatori per ogni entità.** Sono la verifica del principio che non cede mai.

```php
it('non espone i movimenti di un altro tenant', function (): void {
    $acme = createTenant('acme');
    $globex = createTenant('globex');

    tenancy()->run($acme, function (): void {
        StockMovement::factory()->count(5)->create();
    });

    tenancy()->run($globex, function (): void {
        expect(StockMovement::query()->count())->toBe(0);
    });
});

it('non permette di leggere una risorsa di un altro tenant via API', function (): void {
    $acme = createTenant('acme');
    $globex = createTenant('globex');

    $id = tenancy()->run($acme, fn (): int => Supplier::factory()->create()->id);

    tenancy()->run($globex, function () use ($id): void {
        $this->actingAs(userWithPermission('supplier.view'))
            ->getJson("/api/v1/suppliers/{$id}")
            ->assertNotFound();   // 404, non 403
    });
});
```

Questi test vivono in `tests/Tenant/` perché hanno esigenze diverse dagli altri: creano tenant
reali e non possono usare le scorciatoie della suite di feature.

---

## Test di architettura

Verificano i vincoli strutturali che nessuna revisione umana riesce a controllare in modo
sistematico.

```php
arch('il dominio non dipende dal framework')
    ->expect('App\Domain')
    ->not->toUse('Illuminate');

arch('le action sono final con un solo metodo pubblico')
    ->expect('App\Application')
    ->classes()
    ->toBeFinal()
    ->toHaveSuffix('Action')
    ->toHaveMethod('execute');

arch('i controller non usano direttamente i model')
    ->expect('App\Http\Controllers')
    ->not->toUse('App\Models');

arch('nessun debug residuo')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('i model di dominio non usano la connessione landlord')
    ->expect('App\Domain')
    ->not->toUse('Illuminate\Support\Facades\DB');
```

Costano pochi millisecondi e intercettano l'erosione strutturale, che è il modo in cui i progetti
si allontanano dagli standard senza che nessuno se ne accorga.

---

## Copertura

| Ambito | Soglia | Motivo |
|---|---|---|
| Complessiva | ≥ 80% | soglia di merge |
| Action | **100%** | ogni mutazione di stato è verificata |
| Policy | **100%** | ogni decisione di accesso è verificata |
| Value object ed enum di dominio | **100%** | contengono le invarianti |
| Controller | ≥ 70% | sono sottili, la logica è altrove |
| Filament Resource | non conteggiata | dichiarative; si testano le azioni |
| Migration, config, provider | escluse | non contengono logica |

La copertura è un indicatore, non un obiettivo: 100% su codice banale e 40% sulle Action è un
risultato peggiore di 75% ben distribuito. Per questo la soglia è **doppia**.

---

## Dati di test

| Strumento | Uso |
|---|---|
| Factory | creazione di entità valide secondo il dominio |
| Stati nominati | rappresentare i casi limite (`expired()`, `suspended()`) |
| Seeder `System/` | dati obbligatori, anche nei test |
| Seeder `Demo/` | mai nei test automatici |
| Helper di test | `userWithPermission()`, `createTenant()` |

Le factory devono produrre entità **valide**: una factory che genera dati che il dominio
rifiuterebbe rende i test inaffidabili, perché verificano situazioni impossibili.

---

## Test lenti e come evitarli

| Causa | Rimedio |
|---|---|
| Database ricreato ad ogni test | `RefreshDatabase` con transazioni |
| Creazione di tenant in ogni test | solo in `tests/Tenant/` |
| `sleep()` per attendere | `travel()` per manipolare il tempo |
| Chiamate HTTP reali | `Http::fake()` |
| Invio reale di mail | `Mail::fake()` |
| Job eseguiti davvero | `Queue::fake()`, tranne quando il job è l'oggetto del test |
| Factory che creano alberi di relazioni | creare solo ciò che serve |

Obiettivo: la suite unitaria sotto i 10 secondi, l'intera suite sotto i 3 minuti. Una suite lenta
viene eseguita di rado, e una suite eseguita di rado non protegge da nulla.

---

## Esempi

### Esempio 1 — test che non serve

```php
it('ha un nome', function (): void {
    $supplier = new Supplier();
    $supplier->name = 'ACME';
    expect($supplier->name)->toBe('ACME');
});
```

Verifica il funzionamento di PHP, non del dominio. Non può fallire per un difetto reale.

### Esempio 2 — test che vale

```php
it('non permette di archiviare un fornitore con movimenti nell\'ultimo anno', function (): void {
    $supplier = Supplier::factory()->create();
    StockMovement::factory()->create(['supplier_id' => $supplier->id, 'occurred_at' => now()->subMonths(6)]);

    expect(fn () => app(ArchiveSupplierAction::class)->execute($supplier))
        ->toThrow(SupplierHasRecentMovements::class);
});
```

Verifica una regola di dominio che qualcuno potrebbe rompere modificando l'Action.

---

## Best practice

- Un test per comportamento, non per metodo.
- Nome del test in italiano, che descrive il comportamento atteso.
- Per ogni operazione: percorso corretto, regola violata, autorizzazione negata.
- Test di isolamento tenant per ogni entità, senza eccezioni.
- Test di architettura fin dal primo giorno.
- Ogni bug produce prima un test rosso.
- Falsificare i test ogni tanto: se un test non fallisce quando si rompe il codice, non serve.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Testare l'implementazione invece del comportamento | I test si rompono ad ogni refactoring | Verificare l'effetto osservabile |
| Nessun test di autorizzazione | I difetti più gravi passano | Test obbligatorio per operazione |
| Nessun test di isolamento tenant | Rischio di data leak | Obbligatorio per entità |
| Copertura alta su codice banale | Falsa sicurezza | Soglia doppia |
| Test che dipendono dall'ordine | Fallimenti intermittenti | Isolamento completo |
| `sleep()` nei test | Suite lenta e instabile | `travel()` |
| Factory che generano dati non validi | Test che verificano l'impossibile | Factory conformi al dominio |
| Bug corretto senza test | Il difetto torna | Test rosso prima della correzione |

---

## Checklist

- [ ] Ogni Action ha test al 100%.
- [ ] Ogni Policy ha test al 100%.
- [ ] Ogni operazione ha i tre test: corretto, regola violata, autorizzazione negata.
- [ ] Ogni entità ha il test di isolamento tenant.
- [ ] I test di architettura sono presenti e verdi.
- [ ] La copertura complessiva è ≥ 80%.
- [ ] La suite completa gira sotto i 3 minuti.
- [ ] Nessun test dipende dall'ordine di esecuzione.
- [ ] La suite gira anche su MySQL.

---

## Riferimenti

- [Regole di testing](../../rules/testing.md)
- [Agente di testing](../../agents/13-testing-agent.md)
- [Template di test](../../templates/testing/README.md)
- [Checklist di testing](../../checklists/testing-checklist.md)
- [Analisi statica](03-static-analysis.md)
