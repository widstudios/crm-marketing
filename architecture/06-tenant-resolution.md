# Risoluzione del tenant

> Come si determina quale cliente sta parlando, in ogni punto di ingresso: HTTP, API, coda, riga
> di comando.

---

## Indice

1. [Descrizione](#descrizione)
2. [Il contesto tenant](#il-contesto-tenant)
3. [I resolver](#i-resolver)
4. [Il bootstrap](#il-bootstrap)
5. [Risoluzione fuori da HTTP](#risoluzione-fuori-da-http)
6. [Regole di sicurezza](#regole-di-sicurezza)
7. [Gestione degli errori](#gestione-degli-errori)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

La risoluzione è il punto in cui l'applicazione decide **su quali dati sta per lavorare**. È il
singolo punto più critico dell'architettura: un errore qui non produce un malfunzionamento, produce
una fuga di dati.

Per questo la risoluzione avviene **una sola volta**, all'ingresso, e il contesto non è modificabile
per il resto della richiesta.

---

## Il contesto tenant

```php
final class TenantContext
{
    private static ?Tenant $current = null;

    public static function current(): ?Tenant
    {
        return self::$current;
    }

    public static function currentOrFail(): Tenant
    {
        return self::$current ?? throw new TenantNotResolved();
    }

    public static function set(Tenant $tenant): void
    {
        throw_if(self::$current !== null, new TenantAlreadyResolved());

        self::$current = $tenant;
    }

    public static function forget(): void
    {
        self::$current = null;
    }

    public static function isLandlord(): bool
    {
        return self::$current === null;
    }
}
```

`set()` solleva un'eccezione se il contesto è già stato risolto: impedisce il cambio di tenant a
metà richiesta, che è il modo in cui una fuga di dati potrebbe verificarsi senza che nessuno se ne
accorga.

L'unica eccezione ammessa è `tenancy()->run($tenant, $callback)`, usata dai comandi e dai job, che
imposta e ripristina in modo controllato.

---

## I resolver

Si tenta in ordine; il primo che risolve vince.

| # | Resolver | Fonte | Ambiente |
|---|---|---|---|
| 1 | `DomainResolver` | dominio completo | tutti |
| 2 | `SubdomainResolver` | sottodominio | tutti |
| 3 | `TokenResolver` | token API | tutti |
| 4 | `HeaderResolver` | header `X-Tenant` | **solo local e testing** |

Se nessun resolver produce un risultato, la richiesta appartiene al contesto **landlord**, purché
il dominio sia tra quelli centrali.

### DomainResolver

```php
final readonly class DomainResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $host = $request->getHost();

        if (in_array($host, config('tenancy.central_domains'), true)) {
            return null;   // contesto landlord
        }

        return TenantDomain::query()
            ->where('domain', $host)
            ->whereNotNull('verified_at')
            ->with('tenant')
            ->first()
            ?->tenant;
    }
}
```

Il dominio è unico su tutta la piattaforma: la risoluzione è deterministica e non ha ambiguità.

### TokenResolver

```php
final readonly class TokenResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $token = PersonalAccessToken::findToken($request->bearerToken());

        // Il tenant deriva dal token, non da alcun parametro della richiesta.
        return $token?->tenant;
    }
}
```

### HeaderResolver

Ammesso **solo** in locale e nei test, per poter provare le API senza configurare i domini.

```php
public function resolve(Request $request): ?Tenant
{
    throw_unless(app()->environment(['local', 'testing']), new HeaderResolutionNotAllowed());

    return Tenant::query()->where('slug', $request->header('X-Tenant'))->first();
}
```

L'eccezione non è una precauzione teorica: senza, un errore di configurazione in produzione
aprirebbe l'accesso a qualunque tenant tramite un header.

---

## Il bootstrap

Risolto il tenant, i servizi vengono riconfigurati. **Tutti**, non alcuni.

| Bootstrapper | Cosa riconfigura | Se manca |
|---|---|---|
| `DatabaseBootstrapper` | connessione al database del tenant | dati di altri clienti |
| `CacheBootstrapper` | prefisso delle chiavi | dati in cache di altri clienti |
| `FilesystemBootstrapper` | disco dei file | file di altri clienti |
| `QueueBootstrapper` | contesto propagato ai job | job nel tenant sbagliato |
| `RedisBootstrapper` | prefisso di lock e sessioni | interferenze tra clienti |

```php
final readonly class CacheBootstrapper implements TenantBootstrapper
{
    public function bootstrap(Tenant $tenant): void
    {
        config([
            'cache.prefix' => "{$tenant->slug}:cache:",
            'session.cookie' => "{$tenant->slug}_session",
        ]);

        Cache::purge();
    }

    public function revert(): void
    {
        config(['cache.prefix' => config('cache.default_prefix')]);
        Cache::purge();
    }
}
```

Rimuovere un bootstrapper «perché non serve» è una delle modifiche più pericolose che si possano
fare a un progetto: ognuno chiude un canale attraverso cui i dati potrebbero passare da un tenant
all'altro.

---

## Risoluzione fuori da HTTP

### Nei job

```php
final class RecalculateStockJob implements ShouldQueue
{
    use TenantAware;   // serializza e ripristina il tenant

    public function __construct(public int $batchId) {}

    public function handle(): void
    {
        // Il contesto è già ripristinato dal trait.
        Batch::findOrFail($this->batchId)->recalculate();
    }
}
```

Il trait serializza lo `slug` del tenant al momento dell'accodamento e lo ripristina prima
dell'esecuzione. Un job senza il trait gira nel contesto landlord e fallisce — nel caso migliore.

### Nei comandi

```bash
# Su un tenant
php artisan tenant:artisan "stock:recalculate" --tenant=acme

# Su tutti
php artisan tenants:artisan "stock:recalculate"
```

```php
tenancy()->run($tenant, function (): void {
    // codice eseguito nel contesto del tenant
});
// il contesto è ripristinato automaticamente, anche in caso di eccezione
```

### Nello scheduler

```php
Schedule::command('stock:recalculate')->daily();          // ✗ gira nel landlord
Schedule::command('tenants:artisan "stock:recalculate"')  // ✓ gira su ogni tenant
    ->dailyAt('02:00')
    ->withoutOverlapping();
```

`withoutOverlapping()` è obbligatorio: un'attività che gira su N tenant può superare l'intervallo
di pianificazione, e due esecuzioni sovrapposte producono dati incoerenti.

---

## Regole di sicurezza

| # | Regola | Motivo |
|---|---|---|
| 1 | Il tenant deriva **sempre** da dominio o token | un parametro sarebbe modificabile dal client |
| 2 | La risoluzione avviene **una volta**, all'ingresso | il cambio a metà richiesta è una fuga |
| 3 | `HeaderResolver` solo in locale e test | in produzione sarebbe una porta aperta |
| 4 | Un tenant sospeso non risolve | l'accesso è bloccato prima dell'autenticazione |
| 5 | Tenant inesistente → `404`, non `403` | non si conferma l'esistenza di altri clienti |
| 6 | Nessuna API cambia il tenant corrente | non esiste un caso d'uso legittimo |
| 7 | Ogni resolver è coperto da test | è il punto più critico del sistema |

---

## Gestione degli errori

| Situazione | Risposta | Nota |
|---|---|---|
| Dominio sconosciuto | `404` | non rivela nulla sull'esistenza di altri tenant |
| Tenant sospeso | `403` con pagina esplicativa | l'utente deve sapere perché |
| Tenant in dismissione | `403` con informazioni sull'esportazione | |
| Tenant in migrazione | `503` con `Retry-After` | temporaneo |
| Database irraggiungibile | `503` | allarme critico |
| Contesto già risolto | eccezione, `500` | difetto applicativo, non condizione operativa |

---

## Esempi

### Esempio 1 — risoluzione completa

```
GET https://acme.gestionale.it/admin/batches

1. DomainResolver: `acme.gestionale.it` non è centrale
2. tenant_domains: trovato, verificato, tenant `acme`, stato `active`
3. TenantContext::set($acme)
4. bootstrap: database `tenant_acme`, cache `acme:cache:`, disco `acme/`,
   coda con contesto, prefisso Redis
5. guardia `tenant`: utente autenticato sul database del tenant
6. la richiesta procede: ogni query va sul database corretto
```

### Esempio 2 — tentativo di accesso incrociato

Un utente autenticato su `acme` invoca `GET /api/v1/suppliers/99`, dove 99 appartiene a `globex`.

La risoluzione dal token dà `acme`. La query gira su `tenant_acme`, dove l'identificatore 99 non
esiste. Risposta: `404`.

Non c'è alcun controllo applicativo che «impedisce» l'accesso: l'isolamento è una conseguenza
della struttura, ed è per questo che regge.

---

## Best practice

- Risolvere una volta sola, all'ingresso.
- Derivare il tenant da dominio o token, mai da input.
- Attivare tutti i bootstrapper, sempre.
- Usare il trait `TenantAware` in ogni job.
- Usare `tenancy()->run()` per l'esecuzione controllata nei comandi.
- Verificare lo stato del tenant durante la risoluzione, prima dell'autenticazione.
- Coprire ogni resolver con test.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Tenant da parametro della richiesta | Accesso ai dati altrui | Dominio o token |
| Cambio di tenant a metà richiesta | Fuga di dati difficile da rilevare | Contesto immutabile |
| `HeaderResolver` attivo in produzione | Accesso a qualunque tenant | Vincolo di ambiente |
| Bootstrapper rimosso | Canale di fuga aperto | Tutti attivi |
| Job senza `TenantAware` | Esecuzione nel contesto sbagliato | Trait obbligatorio |
| Comando schedulato senza contesto | Gira sul landlord, non fa nulla | `tenants:artisan` |
| Stato del tenant non verificato | Un tenant sospeso resta accessibile | Verifica nel resolver |
| `403` per tenant inesistente | Conferma l'esistenza di altri clienti | `404` |

---

## Checklist

- [ ] La risoluzione avviene una sola volta, in un middleware.
- [ ] Il tenant deriva da dominio o token, mai da input.
- [ ] `HeaderResolver` è attivo solo in locale e testing.
- [ ] Tutti i bootstrapper sono registrati.
- [ ] Ogni job usa `TenantAware`.
- [ ] I comandi schedulati girano nel contesto corretto.
- [ ] Lo stato del tenant è verificato durante la risoluzione.
- [ ] Ogni resolver ha test dedicati.
- [ ] Un tenant inesistente produce `404`.

---

## Riferimenti

- [Multitenancy](03-multitenancy-overview.md) · [Ciclo di vita](07-tenant-lifecycle.md)
- [Autenticazione](08-authentication.md) · [Code e scheduler](18-queue-scheduler.md)
- [Strategia di caching](22-caching-strategy.md)
- [Guida alla sicurezza](../docs/04-quality/05-security-guide.md)
