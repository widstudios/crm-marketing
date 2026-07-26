# Foundation — Tenancy

> Come la Foundation apre e chiude il contesto di un cliente, e dove l'isolamento può rompersi.

---

## Indice

1. [Descrizione](#descrizione)
2. [I componenti](#i-componenti)
3. [Il ciclo di vita del contesto](#il-ciclo-di-vita-del-contesto)
4. [Risoluzione del tenant](#risoluzione-del-tenant)
5. [I cinque punti dell'isolamento](#i-cinque-punti-dellisolamento)
6. [Lavorare su più tenant](#lavorare-su-più-tenant)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Il modello è quello a **un database per tenant**: i dati di un cliente vivono in un database che
contiene solo i suoi. Non c'è una colonna `tenant_id`, e non c'è un filtro globale da ricordarsi di
applicare.

Il vantaggio non è la prestazione: è che l'isolamento non dipende dalla disciplina. Con una colonna
di appartenenza, la separazione regge finché *ogni* query, per sempre, applica il filtro — e basta
una query dimenticata, una volta, perché i dati di un cliente compaiano a un altro.

Il modello sposta il rischio: non più «ogni query», ma «ogni cambio di contesto». I cambi di
contesto sono pochi, sono in un punto solo, e sono verificabili. Questo documento descrive quel
punto.

Decisione di riferimento: [ADR-0002](../../architecture/decisions/0002-tenant-isolation-strategy.md).

---

## I componenti

| Componente | Responsabilità |
|---|---|
| [`TenantContext`](../src/Tenancy/TenantContext.php) | conserva il tenant corrente, e nient'altro |
| [`TenantManager`](../src/Tenancy/TenantManager.php) | apre e chiude il contesto, orchestrando i bootstrapper |
| [`TenantResolverChain`](../src/Tenancy/TenantResolverChain.php) | applica i resolver configurati, in ordine |
| [`DomainResolver`](../src/Tenancy/Resolvers/DomainResolver.php) | deriva il tenant dal dominio: è il resolver di produzione |
| [`HeaderResolver`](../src/Tenancy/Resolvers/HeaderResolver.php) | deriva il tenant da un header, **solo** in `local` e `testing` |
| [`TenantCacheKey`](../src/Tenancy/TenantCacheKey.php) | compone le chiavi di cache nello spazio del tenant |
| [`TenantStatus`](../src/Tenancy/TenantStatus.php) | stati e transizioni ammesse |
| I quattro bootstrapper | riconfigurano database, cache, storage e code |

La separazione tra `TenantContext` e `TenantManager` è deliberata: leggere quale sia il tenant
corrente deve essere gratuito e senza effetti collaterali, mentre cambiarlo è un'operazione che
riconfigura mezzo framework. Tenere le due cose nella stessa classe avrebbe reso difficile
distinguere una lettura innocua da un cambio di contesto.

---

## Il ciclo di vita del contesto

```
Richiesta HTTP
      │
      ▼
InitializeTenancy (middleware)
      │
      ├─▶ TenantResolverChain::resolve()      dal dominio, o dall'header in sviluppo
      │        └─ nessun tenant → 404, la richiesta non prosegue
      │
      ├─▶ verifica dello stato                 non attivo → TenantNotActive
      │
      └─▶ TenantManager::initialize()
               │
               ├─ DatabaseBootstrapper     riscrive la connessione, purge(), reconnect()
               ├─ CacheBootstrapper        sposta il prefisso dello store
               ├─ FilesystemBootstrapper   punta il disco alla cartella privata del tenant
               └─ QueueBootstrapper        invalida le connessioni di coda risolte
                        │
                        ▼
                 TenancyInitialized (evento)
                        │
                        ▼
              L'applicazione lavora normalmente:
              nessun filtro da ricordare, nessuno scope globale.
```

La chiusura percorre i bootstrapper **in ordine inverso**. Non è un dettaglio estetico: alcuni
dipendono dallo stato lasciato dai precedenti, e annullarli nell'ordine di apertura lascia
configurazioni miste.

---

## Risoluzione del tenant

Il tenant si deriva sempre da qualcosa che il client **non controlla**.

| Resolver | Fonte | Ambienti | Perché |
|---|---|---|---|
| `DomainResolver` | dominio della richiesta | tutti | il dominio è scelto da DNS e certificato |
| `HeaderResolver` | header `X-Tenant` | solo `local`, `testing` | un header lo sceglie chi invia la richiesta |

`HeaderResolver` verifica l'ambiente **nel codice**, non solo in configurazione, e non ha un modo
per disattivare il controllo. La ragione è che una configurazione sbagliata in produzione è un
errore plausibile — un file copiato, una variabile dimenticata — mentre modificare quella classe
non lo è. In produzione, un resolver da header significherebbe che chiunque può scegliere di quale
cliente leggere i dati.

Un progetto che deve risolvere il tenant da un token API aggiunge il proprio resolver in
configurazione, senza toccare la Foundation:

```php
'resolvers' => [
    App\Infrastructure\Tenancy\ApiTokenResolver::class,
    WidStudios\Foundation\Tenancy\Resolvers\DomainResolver::class,
],
```

---

## I cinque punti dell'isolamento

Il database è protetto dalla struttura. Gli altri quattro punti sono protetti solo dal codice, e il
quinto è protetto solo dall'abitudine di chi esegue i comandi.

### 1. Database

```php
$this->config->set("database.connections.{$connection}", $settings);
$this->config->set('database.default', $connection);

$this->database->purge($connection);      // ← senza questo, la riga sotto non fa nulla di utile
$this->database->reconnect($connection);
```

`reconnect()` da solo riusa l'istanza già costruita, con la configurazione precedente. Il codice
sembra funzionare: nessun errore, nessun avviso, e le query vanno nel database del cliente
precedente. È il difetto più pericoloso dell'intera Foundation, ed è invisibile finché non ci sono
due tenant — cioè fino al secondo cliente in produzione.

### 2. Cache

Redis è uno solo. Due tenant che scrivono `dashboard.stats` scrivono nella stessa casella.

```php
// ✗ Il secondo tenant legge i numeri del primo
Cache::remember('dashboard.stats', 300, $callback);

// ✓
Cache::remember(TenantCacheKey::for('dashboard.stats'), 300, $callback);
```

Il `CacheBootstrapper` sposta anche il prefisso dello store, come rete di sicurezza. Non sostituisce
`TenantCacheKey`: lo store può essere sostituito, un array store nei test non ha prefissi, e una
disciplina esplicita è verificabile con una ricerca mentre una configurazione non lo è.

### 3. Code

Il job viene eseguito minuti dopo, in un processo che non ha mai visto la richiesta.

```php
use TenantAware;      // cattura lo slug al dispatch, lo ripristina prima di handle()
```

Senza il trait, il job gira nel contesto che il worker aveva per ultimo. Funziona in sviluppo, dove
c'è un tenant solo, e scrive nel database sbagliato al primo giorno di carico reale.

Per aggiungere altri middleware a un job si sovrascrive `jobMiddleware()`, **non** `middleware()`:
sovrascrivere `middleware()` rimuoverebbe il ripristino del contesto senza che nulla lo segnali.

### 4. Storage

Disco privato per tenant, radice separata, `visibility` fissata a `private` e non configurabile: un
file di un cliente su disco pubblico è raggiungibile da chiunque ne indovini il percorso.

### 5. Comandi CLI

```bash
# ✗ Gira sul landlord: le tabelle che cerca non ci sono
php artisan stock:recalculate

# ✓
php artisan tenants:artisan "stock:recalculate"
```

Il caso in cui il comando fallisce è quello fortunato. Il caso peggiore è un comando che trova
tabelle omonime nel landlord e le modifica.

---

## Lavorare su più tenant

```php
// Un tenant, con ripristino garantito anche in caso di eccezione
$tenancy->run($tenant, function () use ($batchId): void {
    // …
});

// Tutti i tenant, a lotti
$failures = $tenancy->runForEach(function (Tenant $tenant): void {
    Artisan::call('stock:recalculate');
});
```

`runForEach()` **non si ferma** al primo fallimento: raccoglie gli errori e li restituisce indicizzati
per slug. Un comando su N clienti che si interrompe al terzo lascia il sistema in uno stato peggiore
di quello da cui è partito, perché una parte è aggiornata e una parte no, e non è chiaro quale.

Salta i tenant il cui stato non ammette elaborazione. Un tenant sospeso viene comunque elaborato: la
sospensione riguarda l'accesso, non i dati, e un tenant sospeso che non riceve le migration non
potrà più essere riattivato.

---

## Esempi

### Aggregare dati di piattaforma senza violare l'isolamento

```php
final class CollectPlatformMetricsAction extends BaseAction
{
    public function execute(): PlatformMetrics
    {
        $totals = ['tenants' => 0, 'movements' => 0];

        $this->tenancy->runForEach(function (Tenant $tenant) use (&$totals): void {
            $totals['tenants']++;
            // Solo un numero: nessun identificativo esce dal contesto del tenant.
            $totals['movements'] += StockMovement::query()->count();
        });

        return new PlatformMetrics(...$totals);
    }
}
```

Ciò che attraversa il confine sono **valori numerici**, mai identificativi. Un aggregato che porta
al landlord l'elenco degli articoli di un cliente è una fuga di dati, anche se resta interno.

### Il test che dimostra l'isolamento

```php
it('non espone i movimenti di un altro tenant', function (): void {
    $acme = $this->tenant('acme');
    $globex = $this->tenant('globex');

    $this->forTenant($acme, fn () => StockMovement::factory()->count(5)->create());

    $this->forTenant($globex, function (): void {
        expect(StockMovement::query()->count())->toBe(0);
    });
});

it('non espone la cache di un altro tenant', function (): void {
    $this->forTenant('acme', fn () => Cache::put(TenantCacheKey::for('stats'), 42, 60));

    $this->forTenant('globex', function (): void {
        expect(Cache::get(TenantCacheKey::for('stats')))->toBeNull();
    });
});
```

---

## Best practice

- Non aprire mai il contesto a mano: `TenantManager` è l'unico punto autorizzato, ed è ciò che rende
  verificabile l'isolamento.
- Comporre le chiavi di cache con `TenantCacheKey` anche quando sembra superfluo.
- Scrivere il test di isolamento **insieme** all'entità, non dopo.
- Far girare `runForEach()` fino in fondo e leggere l'elenco dei falliti, invece di interrompere.
- Rileggere i cinque punti prima di ogni rilascio maggiore: sono cinque, e si controllano in dieci
  minuti.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `reconnect()` senza `purge()` | Query nel database del tenant precedente | L'ordine nel bootstrapper |
| Chiave di cache scritta a mano | Un cliente legge i numeri di un altro | `TenantCacheKey::for()` |
| Job senza `TenantAware` | Scrittura nel database sbagliato, intermittente | Trait obbligatorio |
| `middleware()` sovrascritto in un job | Il ripristino sparisce senza segnali | `jobMiddleware()` |
| Comando lanciato direttamente | Gira sul landlord | `tenants:artisan` |
| Tenant da parametro della richiesta | Accesso ai dati di un altro cliente | Da dominio o token |
| `HeaderResolver` attivo in produzione | Chiunque sceglie il cliente da leggere | Verifica nel codice, non solo in config |
| Aggregati con identificativi verso il landlord | Fuga di dati tra confini | Solo valori numerici |
| Tenant sospesi esclusi dalle migration | Non riattivabili | `allowsProcessing()` |
| `runForEach()` interrotto al primo errore | Sistema a metà, stato incerto | Raccogliere i falliti |

---

## Checklist

- [ ] Il tenant si deriva da dominio o token, mai da input del client.
- [ ] Ogni chiave di cache passa da `TenantCacheKey`.
- [ ] Ogni job usa `TenantAware`.
- [ ] I file vivono su disco privato per tenant.
- [ ] I comandi su dati di dominio girano via `tenants:artisan`.
- [ ] Esiste un test di isolamento per ogni entità.
- [ ] Esiste un test di isolamento della cache.
- [ ] Esiste un test sul ripristino del contesto nei job.
- [ ] `HeaderResolver` è rifiutato fuori da `local` e `testing`, con un test che lo dimostra.

---

## Riferimenti

- [ADR-0002](../../architecture/decisions/0002-tenant-isolation-strategy.md)
- [Multitenancy](../../architecture/03-multitenancy-overview.md) · [Risoluzione del tenant](../../architecture/06-tenant-resolution.md)
- [Database tenant](../../architecture/05-tenant-databases.md) · [Code e scheduler](../../architecture/18-queue-scheduler.md)
- [Regole di sicurezza](../../rules/security.md) · [Queue](../../rules/queue.md) · [Cache](../../rules/cache.md)
- [Checklist di sicurezza](../../checklists/security-checklist.md)
