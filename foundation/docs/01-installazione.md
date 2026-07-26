# Foundation — Installazione

> Come si installa la Foundation in un progetto, che cosa il progetto deve fornire e come si
> configura.

---

## Indice

1. [Descrizione](#descrizione)
2. [Installazione](#installazione)
3. [Che cosa deve fornire il progetto](#che-cosa-deve-fornire-il-progetto)
4. [Configurazione](#configurazione)
5. [Connessioni al database](#connessioni-al-database)
6. [Middleware](#middleware)
7. [Verifica dell'installazione](#verifica-dellinstallazione)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

L'installazione della Foundation è deliberatamente **incompleta da sola**: il pacchetto non sa come
si chiama il model dei tenant, quali rotte appartengono ai clienti e dove vivono i loro database.
Sono decisioni del progetto, e imporle avrebbe significato imporre a tutti la forma del primo
progetto che ne ha avuto bisogno.

Questo documento elenca esattamente ciò che manca e come fornirlo.

---

## Installazione

```bash
composer require widstudios/foundation
php artisan vendor:publish --tag=foundation-config
```

Il service provider si registra da solo tramite il *package discovery* di Laravel. Non serve
aggiungerlo a `bootstrap/providers.php`.

---

## Che cosa deve fornire il progetto

### 1. Il model del tenant

Vive nel landlord e implementa il contratto della Foundation.

```php
<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use WidStudios\Foundation\Contracts\Tenancy\Tenant as TenantContract;
use WidStudios\Foundation\Tenancy\TenantStatus;

final class Tenant extends Model implements TenantContract
{
    protected $connection = 'landlord';

    protected $fillable = ['name', 'slug', 'status'];

    protected function casts(): array
    {
        return ['status' => TenantStatus::class];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDatabaseName(): string
    {
        return config('foundation.tenancy.database_prefix').$this->slug;
    }

    public function getDomains(): array
    {
        return $this->domains->pluck('domain')->all();
    }

    public function getStatus(): TenantStatus
    {
        return $this->status;
    }
}
```

Il nome del database si **deriva** dallo slug invece di essere una colonna: una colonna può
divergere dal database che esiste davvero, e la divergenza si scopre quando la migration fallisce
su un cliente solo.

### 2. Il repository dei tenant

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Landlord;

use App\Models\Landlord\Tenant;
use WidStudios\Foundation\Repositories\EloquentTenantRepository;

final class TenantRepository extends EloquentTenantRepository
{
    protected function model(): string
    {
        return Tenant::class;
    }
}
```

### 3. Il binding

```php
// app/Providers/AppServiceProvider.php

use WidStudios\Foundation\Contracts\Tenancy\TenantRepository as TenantRepositoryContract;
use App\Infrastructure\Landlord\TenantRepository;

public function register(): void
{
    $this->app->singleton(TenantRepositoryContract::class, TenantRepository::class);
}
```

È l'**unico** binding obbligatorio. Tutto il resto ha un valore predefinito funzionante.

---

## Configurazione

Le chiavi si trovano in `config/foundation.php`. Le più importanti:

| Chiave | Significato | Predefinito |
|---|---|---|
| `tenancy.landlord_connection` | connessione di piattaforma | `landlord` |
| `tenancy.tenant_connection` | connessione riconfigurata a ogni cambio di tenant | `tenant` |
| `tenancy.tenant_connection_template` | connessione da cui ereditare host e credenziali | `mysql` |
| `tenancy.database_prefix` | prefisso del nome database: `<prefisso><slug>` | `tenant_` |
| `tenancy.resolvers` | resolver applicati in ordine | dominio, header |
| `tenancy.central_domains` | domini di piattaforma, mai di un tenant | — |
| `tenancy.bootstrappers` | riconfigurazioni all'apertura del contesto | i quattro |
| `cache.prefix` | primo segmento di ogni chiave di cache | `ws` |
| `storage.root` | radice dei dischi per tenant | `storage/tenants` |
| `modules.enabled` | moduli attivi | `[]` |

Nessun accesso a `env()` fuori da questo file: il codice legge sempre da `config('foundation....')`.
Con la cache di configurazione attiva, `env()` restituisce `null` e il difetto si manifesta solo in
produzione.

---

## Connessioni al database

`config/database.php` deve dichiarare **tre** connessioni.

```php
'connections' => [

    // Piattaforma: tenant, domini, utenti di piattaforma, job falliti.
    'landlord' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_LANDLORD_DATABASE', 'landlord'),
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'strict' => true,
    ],

    // Modello: la Foundation ne copia host, porta e credenziali,
    // sostituendo solo il nome del database.
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => null,
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'strict' => true,
    ],

    // Riscritta a ogni cambio di tenant: qui non si mette nulla a mano.
    'tenant' => [
        'driver' => 'mysql',
        'database' => null,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'strict' => true,
    ],
],
```

Le migration si separano per destinazione:

```
database/migrations/
├── landlord/     tenants, tenant_domains, platform_users, failed_jobs
└── tenant/       tutto il dominio applicativo
```

Non esiste una terza cartella. Una migration che non si sa dove mettere è il segnale che la tabella
non si sa a chi appartenga, e quella domanda va risolta prima di scrivere la migration.

---

## Middleware

La Foundation fornisce i middleware; il progetto decide dove applicarli.

```php
// bootstrap/app.php

->withMiddleware(function (Middleware $middleware): void {
    $middleware->group('tenant', [
        \WidStudios\Foundation\Tenancy\Middleware\InitializeTenancy::class,
        // … middleware web dell'applicazione
    ]);

    $middleware->group('platform', [
        \WidStudios\Foundation\Tenancy\Middleware\PreventAccessFromTenantDomains::class,
    ]);
})
```

Ogni rotta appartiene a uno dei due gruppi. Una rotta che non sta in nessuno dei due è un difetto:
significa che risponde su tutti i domini, con un contesto che dipende da come è arrivata la
richiesta.

---

## Verifica dell'installazione

```bash
# 1. Il landlord esiste ed è migrato
php artisan migrate --database=landlord

# 2. Almeno un tenant esiste
php artisan tenants:list

# 3. Le migration tenant si applicano
php artisan tenants:migrate

# 4. Il contesto si apre davvero
php artisan tenants:artisan "db:show" --tenant=acme
```

Il quarto comando è quello che conta: se mostra il database del tenant e non il landlord, i
bootstrapper funzionano.

---

## Esempi

### Provisioning di un tenant nuovo

```php
final class ProvisionTenantAction extends BaseAction
{
    public function execute(ProvisionTenantData $data): Tenant
    {
        $tenant = $this->transaction(fn (): Tenant => Tenant::query()->create([
            'name' => $data->name,
            'slug' => $data->slug,
            'status' => TenantStatus::Provisioning,
        ]));

        // Fuori dalla transazione: la creazione di un database è DDL,
        // e in MySQL un DDL provoca un commit implicito.
        $this->databases->create($tenant->getDatabaseName());

        app(TenantManager::class)->run($tenant, function (): void {
            Artisan::call('migrate', [
                '--database' => config('foundation.tenancy.tenant_connection'),
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', ['--class' => 'System\\PermissionSeeder']);
        });

        $tenant->update(['status' => TenantStatus::Active]);

        return $tenant;
    }
}
```

---

## Best practice

- Derivare il nome del database dallo slug, mai conservarlo in una colonna.
- Provare il provisioning di un tenant nuovo **in CI**: intercetta le migration che dipendono dai
  dati esistenti, che è il difetto più frequente e il più silenzioso.
- Tenere `mysql` come sola connessione modello: duplicarne le credenziali in `tenant` significa
  aggiornarle in due posti.
- Assegnare ogni rotta a uno dei due gruppi di middleware, senza eccezioni.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Binding di `TenantRepository` mancante | Errore di risoluzione al primo accesso | Registrarlo in `AppServiceProvider` |
| Nome del database in colonna | Diverge dal database reale su un cliente solo | Derivarlo dallo slug |
| Migration tenant e landlord mescolate | Tabelle di dominio nel landlord | Due cartelle separate |
| Rotta senza gruppo di middleware | Contesto dipendente dal dominio di arrivo | Ogni rotta in un gruppo |
| `env()` fuori da `config/` | Restituisce `null` con la cache attiva | `config('foundation....')` |
| Credenziali duplicate nella connessione `tenant` | Divergono al primo cambio | Solo nella connessione modello |
| Provisioning mai provato in CI | I nuovi clienti non si attivano dopo un rilascio | Prova automatica |

---

## Checklist

- [ ] `composer require` eseguito e configurazione pubblicata.
- [ ] Il model del tenant implementa il contratto `Tenant`.
- [ ] `TenantRepository` è registrato nel container.
- [ ] Le tre connessioni sono dichiarate in `config/database.php`.
- [ ] Le migration sono separate in `landlord/` e `tenant/`.
- [ ] I gruppi di middleware sono definiti e applicati a tutte le rotte.
- [ ] `tenants:artisan "db:show"` mostra il database del tenant.
- [ ] Il provisioning di un tenant nuovo è verificato in CI.

---

## Riferimenti

- [Foundation](../README.md) · [Tenancy](02-tenancy.md)
- [Database tenant](../../architecture/05-tenant-databases.md) · [Landlord](../../architecture/04-landlord-database.md)
- [Ciclo di vita del tenant](../../architecture/07-tenant-lifecycle.md)
- [Ambiente locale](../../docs/01-getting-started/02-local-environment.md)
