# Foundation — Testing

> Gli aiuti che rendono banale il test che nessuno scrive volentieri: quello di isolamento.

---

## Indice

1. [Descrizione](#descrizione)
2. [InteractsWithTenants](#interactswithtenants)
3. [I test di isolamento obbligatori](#i-test-di-isolamento-obbligatori)
4. [L'orologio fermo](#lorologio-fermo)
5. [Test di architettura](#test-di-architettura)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Gli aiuti di questa area esistono per una ragione misurabile: **se scrivere un test costa venti
righe, non viene scritto; se costa tre, viene scritto per ogni entità**.

Il test di isolamento tra tenant è l'esempio perfetto. È il test che protegge dal difetto più
costoso del sistema, ed è anche quello con l'impianto più noioso: creare due clienti, aprire un
contesto, popolare, chiudere, aprire l'altro, contare. Nessuno lo scrive trenta volte a mano.

---

## InteractsWithTenants

```php
uses(InteractsWithTenants::class);
```

| Metodo | Che cosa fa |
|---|---|
| `tenant(string $slug)` | recupera un tenant, fallendo con un messaggio comprensibile |
| `forTenant($tenant, $callback)` | esegue il callback nel contesto, ripristinando il precedente |
| `asLandlord($callback)` | esegue nel contesto di piattaforma |
| `assertTenantsAreIsolated(...)` | popola nel primo, conta nel secondo, verifica che sia zero |

Il ripristino avviene anche in caso di eccezione: un test fallito non deve lasciare il contesto
aperto sul tenant sbagliato, perché il test successivo fallirebbe per una ragione che non ha nulla a
che vedere con ciò che verifica.

---

## I test di isolamento obbligatori

Sono quattro famiglie, e coprono i cinque punti.

### 1. Isolamento dei dati — per ogni entità

```php
it('non espone i movimenti di un altro tenant', function (): void {
    $this->assertTenantsAreIsolated(
        first: 'acme',
        second: 'globex',
        seed: fn () => StockMovement::factory()->count(5)->create(),
        count: fn (): int => StockMovement::query()->count(),
    );
});
```

### 2. Isolamento della cache

```php
it('non espone la cache di un altro tenant', function (): void {
    $this->forTenant('acme', fn () => Cache::put(TenantCacheKey::for('stats'), 42, 60));

    $this->forTenant('globex', function (): void {
        expect(Cache::get(TenantCacheKey::for('stats')))->toBeNull();
    });
});
```

### 3. Contesto tenant nei job

```php
it('ripristina il contesto tenant nel job', function (): void {
    $this->forTenant('acme', fn () => RecalculateStockJob::dispatch(1));

    $this->forTenant('globex', function (): void {
        // Il job accodato da acme non deve scrivere qui.
        expect(StockMovement::query()->count())->toBe(0);
    });
});

it('fallisce se il job non ha contesto tenant', function (): void {
    $job = new RecalculateStockJob(1);      // nessuna cattura: non è passato dal dispatch

    expect(fn () => (new RestoreTenantContext())->handle($job, fn () => null))
        ->toThrow(MissingTenantContext::class);
});
```

Il secondo test è quello che conta: dimostra che il fallimento è **rumoroso**. Un job che perde il
contesto e prosegue in silenzio è peggio di un job che fallisce.

### 4. Rifiuto del resolver da header fuori dallo sviluppo

```php
it('rifiuta la risoluzione da header fuori da local e testing', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $request = Request::create('/', server: ['HTTP_X_TENANT' => 'acme']);

    expect(app(HeaderResolver::class)->resolve($request))->toBeNull();
});
```

---

## L'orologio fermo

```php
$this->app->instance(Clock::class, new FrozenClock('2026-12-31 23:59:00'));
```

Rende verificabile ciò che altrimenti si potrebbe verificare solo aspettando: scadenze, fine mese,
cambio d'anno, periodi di conservazione.

La condizione perché funzioni è che il dominio **riceva** il `Clock` invece di chiamare `now()`. È
la ragione per cui il contratto esiste: `now()` dentro un'entità rende quell'entità verificabile
solo nel momento giusto dell'anno.

```php
final class Batch
{
    public function isExpired(Clock $clock): bool
    {
        return $this->expiryDate < $clock->today();
    }
}
```

```php
it('considera scaduto un lotto con data passata', function (): void {
    $clock = new FrozenClock('2026-07-01');
    $batch = new Batch(expiryDate: new DateTimeImmutable('2026-06-30'));

    expect($batch->isExpired($clock))->toBeTrue();

    $clock->travelTo('2026-06-29');
    expect($batch->isExpired($clock))->toBeFalse();
});
```

---

## Test di architettura

La Foundation è verificabile con gli stessi test di architettura che impone ai progetti:

```php
arch('le action sono final con un solo metodo pubblico')
    ->expect('App\Application')->classes()->toHaveSuffix('Action')
    ->toBeFinal()->toHaveMethod('execute');

arch('ogni job usa TenantAware')
    ->expect('App\Jobs')->toUseTrait(WidStudios\Foundation\Concerns\TenantAware::class);

arch('nessun job sovrascrive middleware()')
    ->expect('App\Jobs')->not->toHaveMethod('middleware');

arch('nessuna chiave di cache scritta a mano')
    ->expect('App')->not->toUse('Illuminate\Support\Facades\Cache');
```

L'ultimo è drastico e va calibrato: il modo corretto è consentire `Cache` solo dove passa da
`TenantCacheKey`, tipicamente permettendola nei soli `Concerns` e vietandola altrove.

---

## Esempi

### Impianto tipico di una suite multi-tenant

```php
// tests/Pest.php

uses(TestCase::class, RefreshDatabase::class, InteractsWithTenants::class)
    ->beforeEach(function (): void {
        createTenant('acme');
        createTenant('globex');
    })
    ->in('Tenant');

function createTenant(string $slug): Tenant
{
    $tenant = Tenant::factory()->create(['slug' => $slug]);

    app(TenantManager::class)->run($tenant, function (): void {
        Artisan::call('migrate', [
            '--database' => config('foundation.tenancy.tenant_connection'),
            '--path' => 'database/migrations/tenant',
        ]);
    });

    return $tenant;
}
```

---

## Best practice

- Scrivere il test di isolamento **insieme** all'entità, non alla fine del progetto.
- Verificare che il fallimento sia rumoroso, non solo che il percorso corretto funzioni.
- Iniettare `Clock` nel dominio: rende verificabile ciò che dipende dalla data.
- Falsificare i test ogni tanto: rompere il codice e verificare che il test fallisca davvero.
- Aggiungere un test di architettura ogni volta che una regola strutturale viene violata due volte.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Test di isolamento rimandato | Non viene scritto | Insieme all'entità |
| Un solo tenant nei test | L'isolamento non è mai verificato | Almeno due, reali |
| Contesto non ripristinato | Il test successivo fallisce per un'altra ragione | `forTenant()` |
| `now()` nel dominio | Verificabile solo nel momento giusto | `Clock` iniettato |
| Solo il percorso corretto testato | Il fallimento silenzioso non si scopre | Test del rifiuto |
| Test di architettura aggiunti tardi | Cinquanta violazioni insieme | Dal primo giorno |

---

## Checklist

- [ ] Ogni entità ha il suo test di isolamento.
- [ ] Esiste il test di isolamento della cache.
- [ ] Esiste il test sul ripristino del contesto nei job.
- [ ] Esiste il test che dimostra il fallimento rumoroso di un job senza contesto.
- [ ] Esiste il test che rifiuta `HeaderResolver` fuori da `local` e `testing`.
- [ ] Il dominio riceve `Clock` invece di chiamare `now()`.
- [ ] I test di architettura sono presenti e verdi.

---

## Riferimenti

- [Regole di testing](../../rules/testing.md) · [ADR-0007](../../architecture/decisions/0007-testing-strategy.md)
- [Tenancy](02-tenancy.md) · [Checklist di testing](../../checklists/testing-checklist.md)
- [Strategia di testing](../../docs/04-quality/01-testing-strategy.md)
