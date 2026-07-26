# I cinque punti dell'isolamento

> Come si rompe l'isolamento tra clienti, come si vede, e come si dimostra che regge.
> **Autorità: nulla.**

---

## Indice

1. [Descrizione](#descrizione) 2. [Perché cinque](#perché-cinque)
3. [Punto 1 — Database](#punto-1--database) 4. [Punto 2 — Cache](#punto-2--cache)
5. [Punto 3 — Code](#punto-3--code) 6. [Punto 4 — Storage](#punto-4--storage)
7. [Punto 5 — Comandi CLI](#punto-5--comandi-cli) 8. [La verifica completa](#la-verifica-completa)
9. [Esempi](#esempi) 10. [Best practice](#best-practice) 11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist) 13. [Riferimenti](#riferimenti)

---

## Descrizione

Il modello a un database per tenant sposta il rischio: non più «ogni query, per sempre, deve
applicare il filtro», ma «ogni cambio di contesto deve essere completo».

I cambi di contesto sono pochi e sono in un punto solo. Ma «completo» significa cinque cose diverse,
e quattro di esse dipendono dal codice, non dalla struttura.

Questo frammento mostra, per ognuna, **come si rompe**, **come si manifesta** e **il test che lo
dimostra**. La parte più utile è la seconda colonna: nessuno dei cinque difetti produce un errore.

---

## Perché cinque

| Punto | Protetto da | Se manca |
|---|---|---|
| Database | struttura + `DatabaseBootstrapper` | si scrive nel database del cliente precedente |
| Cache | **solo codice** | un cliente legge i numeri di un altro |
| Code | **solo codice** | il job scrive nel database sbagliato, in modo intermittente |
| Storage | **solo codice** | i file di un cliente sono raggiungibili da un altro |
| Comandi CLI | **solo abitudine** | il comando gira sul landlord e non trova le tabelle |

Il primo ha una rete di sicurezza strutturale: se la connessione punta al database sbagliato, le
tabelle esistono comunque e i dati sono plausibili — quindi anche lì la protezione non è totale.

Gli altri quattro non ne hanno nessuna.

---

## Punto 1 — Database

### Come si rompe

```php
// ✗ La riga che sembra sufficiente e non lo è
$this->config->set("database.connections.tenant.database", $tenant->getDatabaseName());
$this->database->reconnect('tenant');
```

`reconnect()` riusa l'istanza già costruita dal `DatabaseManager`, con la configurazione precedente.
La nuova non ha effetto.

```php
// ✓
$this->config->set("database.connections.tenant", $settings);
$this->config->set('database.default', 'tenant');

$this->database->purge('tenant');       // dimentica l'istanza esistente
$this->database->reconnect('tenant');   // ne apre una nuova
```

### Come si manifesta

Non si manifesta. Nessun errore, nessun avviso: le query vanno nel database del cliente precedente,
dove le tabelle esistono e i dati sono plausibili.

Invisibile finché non ci sono **due** tenant, cioè fino al secondo cliente in produzione.

### Il test

```php
it('cambia davvero database al cambio di tenant', function (): void {
    $acme = $this->tenant('acme');
    $globex = $this->tenant('globex');

    $first = $this->forTenant($acme, fn (): string => DB::connection('tenant')->getDatabaseName());
    $second = $this->forTenant($globex, fn (): string => DB::connection('tenant')->getDatabaseName());

    expect($first)->not->toBe($second)
        ->and($second)->toContain('globex');
});
```

---

## Punto 2 — Cache

### Come si rompe

```php
// ✗ Redis è uno solo: due tenant scrivono nella stessa casella
Cache::remember('dashboard.stats', 300, fn () => $this->computeStats());
```

```php
// ✓
Cache::remember(TenantCacheKey::for('dashboard.stats'), 300, fn () => $this->computeStats());
```

### Come si manifesta

Il secondo cliente vede i numeri del primo. I dati sono plausibili — sono numeri di un magazzino
vero — e nessuno si accorge che non sono i suoi finché non li confronta con qualcosa.

Nel [walkthrough](../walkthroughs/01-magazzino-sanitario.md#fase-7--security) questo difetto è stato
trovato in fase 7, su un widget scritto in fase 5. Due fasi di distanza, senza che nulla lo
segnalasse.

### Il test

```php
it('non espone la cache di un altro tenant', function (): void {
    $this->forTenant('acme', function (): void {
        Cache::put(TenantCacheKey::for('dashboard.stats'), 42, 60);
    });

    $this->forTenant('globex', function (): void {
        expect(Cache::get(TenantCacheKey::for('dashboard.stats')))->toBeNull();
    });
});
```

### La verifica che non dipende dalla disciplina

```php
arch('nessuna chiave di cache scritta a mano')
    ->expect('App')
    ->not->toUse('Illuminate\Support\Facades\Cache');
```

Drastico e va calibrato: la forma corretta è consentire `Cache` **solo** dove passa da
`TenantCacheKey` — tipicamente nei `Concerns` — e vietarla altrove.

---

## Punto 3 — Code

### Come si rompe

```php
// ✗
final class RecalculateStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    // Manca TenantAware.

    public function __construct(public int $batchId) {}
}
```

```php
// ✓
use TenantAware;
```

### Come si manifesta

Il job viene eseguito nel contesto che il worker aveva **per ultimo**. In sviluppo c'è un tenant
solo, quindi funziona sempre. In produzione dipende da quale job il worker ha elaborato prima:
funziona la maggior parte delle volte, e ogni tanto scrive nel database di un altro cliente.

È il difetto peggiore dei cinque, perché è **intermittente**: non si riproduce, e ogni tentativo di
riprodurlo conferma che «funziona».

### Il test

```php
it('esegue il job nel contesto del tenant che lo ha accodato', function (): void {
    $this->forTenant('acme', function (): void {
        $batch = Batch::factory()->create(['quantity' => 100]);
        RecalculateStockJob::dispatch($batch->getKey());
    });

    $this->forTenant('globex', function (): void {
        expect(Batch::query()->count())->toBe(0);
    });
});

it('fallisce rumorosamente se il job non porta il tenant', function (): void {
    // Un job che perde il contesto e prosegue in silenzio è peggio di uno che
    // fallisce: il fallimento si vede.
    app(TenantContext::class)->forget();

    expect(fn () => (new RestoreTenantContext())->handle(new RecalculateStockJob(1), fn () => null))
        ->toThrow(MissingTenantContext::class);
});
```

Il secondo test è quello che conta davvero.

### La trappola

```php
// ✗ Rimuove il ripristino del contesto senza che nulla lo segnali
public function middleware(): array
{
    return [new RateLimited('integrations')];
}

// ✓
protected function jobMiddleware(): array
{
    return [new RateLimited('integrations')];
}
```

`middleware()` è `final` nel trait proprio per questo. Un test di architettura lo verifica:

```php
arch('nessun job sovrascrive middleware()')
    ->expect('App\Jobs')->not->toHaveMethod('middleware');
```

---

## Punto 4 — Storage

### Come si rompe

```php
// ✗ Disco pubblico, cartella condivisa
Storage::disk('public')->put("documents/{$document->id}.pdf", $contents);
```

```php
// ✓ Disco privato del tenant corrente, nome rigenerato
Storage::disk('tenant')->put("documents/{$storedName}", $contents);
```

### Come si manifesta

Il file è raggiungibile via URL da chiunque ne indovini il percorso. I percorsi generati sono più
prevedibili di quanto sembri: `documents/1.pdf`, `documents/2.pdf`.

Non si manifesta affatto, finché qualcuno non prova.

### Il test

```php
it('scrive sul disco privato del tenant corrente', function (): void {
    $this->forTenant('acme', function (): void {
        Storage::disk('tenant')->put('prova.txt', 'contenuto');
    });

    $this->forTenant('globex', function (): void {
        expect(Storage::disk('tenant')->exists('prova.txt'))->toBeFalse();
    });
});

it('non usa mai il disco pubblico per i file dei clienti', function (): void {
    expect(config('filesystems.disks.tenant.visibility'))->toBe('private');
});
```

---

## Punto 5 — Comandi CLI

### Come si rompe

```bash
# ✗ Gira nel contesto di piattaforma
php artisan stock:recalculate
```

```bash
# ✓
php artisan tenants:artisan "stock:recalculate"
```

### Come si manifesta

Il caso in cui il comando **fallisce** — tabella inesistente — è quello fortunato. Il caso peggiore è
un comando che trova tabelle omonime nel landlord e le modifica.

Nella pianificazione il difetto è silenzioso: il comando gira ogni notte, non fa nulla di utile, e
nessuno se ne accorge finché qualcuno non nota che le giacenze non vengono più ricalcolate.

### La difesa nel comando

```php
public function handle(TenantContext $context): int
{
    if (! $context->has()) {
        $this->error('Questo comando richiede un contesto tenant.');
        $this->line('Eseguirlo con: php artisan tenants:artisan "stock:recalculate"');

        return self::FAILURE;
    }

    // …
}
```

Rende il difetto **impossibile** invece che improbabile: il comando si rifiuta di girare nel posto
sbagliato, e il messaggio dice come eseguirlo correttamente.

### Il test

```php
it('rifiuta di girare senza contesto tenant', function (): void {
    app(TenantContext::class)->forget();

    $this->artisan('stock:recalculate')
        ->expectsOutputToContain('tenants:artisan')
        ->assertFailed();
});
```

---

## La verifica completa

```bash
# I test di isolamento, su tenant reali
php artisan test --testsuite=Tenant

# I vincoli strutturali
php artisan test --testsuite=Architecture

# La ricerca delle forme note
php tooling/scripts/check-security.php .
```

E la verifica manuale che nessuno script sostituisce: **rileggere i cinque punti sul codice reale**,
non sulla documentazione, prima di ogni rilascio maggiore. Sono cinque, e si controllano in dieci
minuti.

---

## Esempi

### Un aggregato verso il landlord fatto bene e fatto male

```php
// ✗ Porta identificativi di un cliente fuori dal suo contesto
$totals['articles'] = Article::query()->pluck('name')->all();

// ✓ Solo un numero
$totals['articles'] = Article::query()->count();
```

Un aggregato che porta al landlord l'elenco degli articoli di un cliente è una fuga di dati, anche
se resta interno: il landlord è leggibile da chiunque abbia accesso di piattaforma, e quell'elenco è
un'informazione commerciale.

---

## Best practice

- Rileggere i cinque punti sul codice prima di ogni rilascio maggiore.
- Scrivere il test di isolamento insieme all'entità, mai dopo.
- Verificare che il fallimento sia **rumoroso**, non solo che il percorso corretto funzioni.
- Far rifiutare ai comandi di dominio l'esecuzione senza contesto.
- Aggiungere un test di architettura ogni volta che una di queste forme sfugge alla revisione.
- Negli aggregati verso il landlord far passare solo valori numerici.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `reconnect()` senza `purge()` | Query nel database del cliente precedente | L'ordine nel bootstrapper |
| Chiave di cache scritta a mano | Un cliente legge i numeri di un altro | `TenantCacheKey::for()` |
| Job senza `TenantAware` | Scrittura nel database sbagliato, intermittente | Trait obbligatorio |
| `middleware()` sovrascritto | Il ripristino sparisce senza segnali | `jobMiddleware()` |
| File su disco pubblico | Raggiungibili da chi indovina il percorso | Disco privato per tenant |
| Comando lanciato direttamente | Gira sul landlord, in silenzio | `tenants:artisan` + rifiuto nel comando |
| Aggregato con identificativi | Fuga di dati verso il landlord | Solo valori numerici |
| Isolamento verificato sulla documentazione | Il difetto è nel codice | Leggere il codice |

---

## Checklist

- [ ] `purge()` precede `reconnect()` nel bootstrapper del database.
- [ ] Nessuna chiave di cache scritta a mano.
- [ ] Ogni job usa `TenantAware` e nessuno sovrascrive `middleware()`.
- [ ] I file vivono su disco privato per tenant.
- [ ] I comandi di dominio rifiutano di girare senza contesto.
- [ ] Esiste un test di isolamento per ogni entità, più cache, code e storage.
- [ ] Esiste il test che dimostra il fallimento rumoroso di un job senza contesto.
- [ ] Gli aggregati verso il landlord contengono solo valori numerici.

---

## Riferimenti

- [Frammenti](README.md) · [Walkthrough](../walkthroughs/01-magazzino-sanitario.md)
- [Multitenancy](../../architecture/03-multitenancy-overview.md) · [ADR-0002](../../architecture/decisions/0002-tenant-isolation-strategy.md)
- [Foundation — Tenancy](../../foundation/docs/02-tenancy.md)
- [Sicurezza](../../rules/security.md) · [Testing](../../rules/testing.md)
- [Checklist di sicurezza](../../checklists/security-checklist.md)
