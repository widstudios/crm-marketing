# Riferimento della configurazione

> Le chiavi di configurazione introdotte dalla Foundation, il loro significato e i valori
> consigliati per ambiente.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole generali](#regole-generali)
3. [`config/tenancy.php`](#configtenancyphp)
4. [`config/modules.php`](#configmodulesphp)
5. [`config/audit.php`](#configauditphp)
6. [`config/factory.php`](#configfactoryphp)
7. [Variabili d'ambiente](#variabili-dambiente)
8. [Valori per ambiente](#valori-per-ambiente)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

La configurazione della Foundation ha un principio guida: **valori predefiniti sicuri**. Un
progetto che non tocca nulla deve comportarsi in modo corretto e restrittivo; l'allentamento
richiede una scelta esplicita.

---

## Regole generali

| Regola | Motivo |
|---|---|
| `env()` solo dentro `config/` | con `config:cache` ritorna `null` altrove |
| Nessun segreto nel repository | `.env` è ignorato da git |
| `.env.example` sempre allineato | altrimenti il progetto non si avvia su una macchina nuova |
| Ogni chiave ha un default sicuro | l'assenza di configurazione non deve aprire falle |
| Le chiavi si documentano qui | altrimenti nessuno sa cosa fanno |

---

## `config/tenancy.php`

```php
return [
    // Connessioni
    'landlord_connection' => env('LANDLORD_DB_CONNECTION', 'landlord'),
    'tenant_connection' => 'tenant',

    // Schema di denominazione dei database tenant
    'database' => [
        'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),
        'suffix' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    // Risoluzione del tenant, in ordine di tentativo
    'resolvers' => [
        DomainResolver::class,
        SubdomainResolver::class,
        TokenResolver::class,
        // HeaderResolver::class,   // solo local e testing
    ],

    // Domini che NON appartengono ad alcun tenant
    'central_domains' => [
        env('APP_DOMAIN', 'gestionale.it'),
        'www.' . env('APP_DOMAIN', 'gestionale.it'),
    ],

    // Servizi riconfigurati all'ingresso nel contesto tenant
    'bootstrappers' => [
        DatabaseBootstrapper::class,
        CacheBootstrapper::class,
        FilesystemBootstrapper::class,
        QueueBootstrapper::class,
        RedisBootstrapper::class,
    ],

    // Provisioning
    'provisioning' => [
        'queue' => 'provisioning',
        'run_seeders' => ['System\\PermissionSeeder', 'System\\StatusSeeder'],
        'timeout' => 600,
    ],

    // Dismissione
    'termination' => [
        'retention_days' => 30,
        'require_export' => true,
    ],
];
```

| Chiave | Significato | Default | Nota |
|---|---|---|---|
| `landlord_connection` | connessione di piattaforma | `landlord` | mai usata dai model di dominio |
| `database.prefix` | prefisso dei database tenant | `tenant_` | non modificabile dopo il primo tenant |
| `resolvers` | ordine di risoluzione | dominio, sottodominio, token | `HeaderResolver` solo in locale |
| `central_domains` | domini del landlord | dominio applicativo | senza, la landing viene risolta come tenant |
| `bootstrappers` | servizi riconfigurati | tutti | rimuoverne uno produce fughe tra tenant |
| `provisioning.timeout` | secondi massimi | 600 | aumentare con molte migration |
| `termination.retention_days` | ripensamento | 30 | mai zero |

---

## `config/modules.php`

```php
return [
    'path' => base_path('modules'),
    'namespace' => 'Modules',

    'enabled' => [
        'core',
        'auth',
        'tenancy',
        'audit',
        // moduli di dominio del progetto
    ],

    'autoload' => [
        'migrations' => true,
        'translations' => true,
        'views' => true,
        'routes' => true,
        'policies' => true,
    ],
];
```

L'ordine in `enabled` è l'ordine di caricamento: i moduli che ne dipendono altri vanno dopo.

---

## `config/audit.php`

```php
return [
    'enabled' => env('AUDIT_ENABLED', true),

    // Entità sottoposte ad audit obbligatorio
    'audited' => [
        // App\Domain\Inventory\Models\Batch::class,
    ],

    'record' => [
        'old_values' => true,
        'new_values' => true,
        'user' => true,
        'ip' => true,
        'user_agent' => false,   // dato personale, raramente utile
    ],

    // Campi mai registrati, nemmeno in forma cifrata
    'excluded_attributes' => ['password', 'remember_token', 'api_token'],

    'retention_years' => env('AUDIT_RETENTION_YEARS', 10),

    'queue' => env('AUDIT_QUEUE', null),   // null = sincrono
];
```

`queue` va impostata quando il volume di scritture è alto: l'audit sincrono raddoppia il costo di
ogni scrittura.

---

## `config/factory.php`

Configurazione degli strumenti della Factory nel progetto:

```php
return [
    'version' => 'factory-v0.1.0',       // versione di riferimento
    'foundation_version' => '^0.1',

    'conventions' => [
        'strict_types' => true,
        'final_actions' => true,
        'readonly_dto' => true,
    ],

    'quality' => [
        'min_coverage' => 80,
        'min_coverage_actions' => 100,
        'phpstan_level' => 8,
    ],
];
```

Queste chiavi sono lette dai test di architettura e dagli script di verifica: un progetto che
abbassa `min_coverage` lo dichiara qui, in modo visibile, invece di modificare la pipeline.

---

## Variabili d'ambiente

| Variabile | Obbligatoria | Default | Nota |
|---|---|---|---|
| `APP_ENV` | sì | `production` | mai `local` in produzione |
| `APP_DEBUG` | sì | `false` | **`false`** in staging e produzione |
| `APP_KEY` | sì | — | generata, mai condivisa tra ambienti |
| `APP_DOMAIN` | sì | — | dominio del landlord |
| `LANDLORD_DB_*` | sì | — | connessione di piattaforma |
| `TENANT_DB_HOST` | sì | — | host dei database tenant |
| `TENANT_DB_PREFIX` | no | `tenant_` | immutabile dopo il primo tenant |
| `REDIS_HOST`, `REDIS_PASSWORD` | sì | — | password obbligatoria fuori dal locale |
| `QUEUE_CONNECTION` | sì | `redis` | `sync` solo nei test |
| `CACHE_STORE` | sì | `redis` | `array` nei test |
| `SESSION_DRIVER` | sì | `redis` | non `file` in produzione |
| `MAIL_*` | sì | — | Mailpit in locale |
| `FILESYSTEM_DISK` | sì | `tenant` | mai `public` per i dati |
| `AUDIT_ENABLED` | no | `true` | disabilitarlo richiede motivazione |
| `AUDIT_RETENTION_YEARS` | no | `10` | secondo il dominio |
| `TELESCOPE_ENABLED` | no | `false` | mai `true` in produzione |
| `HORIZON_PREFIX` | no | nome applicazione | distingue le installazioni |

---

## Valori per ambiente

| Chiave | local | testing | staging | production |
|---|---|---|---|---|
| `APP_ENV` | `local` | `testing` | `staging` | `production` |
| `APP_DEBUG` | `true` | `true` | `false` | `false` |
| `CACHE_STORE` | `redis` | `array` | `redis` | `redis` |
| `QUEUE_CONNECTION` | `redis` | `sync` | `redis` | `redis` |
| `SESSION_DRIVER` | `redis` | `array` | `redis` | `redis` |
| `TELESCOPE_ENABLED` | `true` | `false` | `true` | **`false`** |
| `LOG_LEVEL` | `debug` | `debug` | `info` | `warning` |
| `AUDIT_QUEUE` | `null` | `null` | `audit` | `audit` |

---

## Esempi

### Esempio 1 — errore di configurazione ricorrente

```php
// ✗ In un service provider: ritorna null quando la configurazione è in cache
public function register(): void
{
    if (env('FEATURE_X_ENABLED')) { /* … */ }
}

// ✓ Passa da config/
if (config('features.x_enabled')) { /* … */ }
```

In produzione `config:cache` è sempre attivo: la prima versione disabilita silenziosamente la
funzionalità.

### Esempio 2 — dominio centrale non configurato

Senza `central_domains`, una richiesta a `www.gestionale.it` viene interpretata come tenant
inesistente e produce un errore 404 sulla landing page pubblica.

---

## Best practice

- `env()` solo in `config/`, senza eccezioni.
- Aggiungere ogni nuova variabile a `.env.example` nello stesso commit.
- Documentare qui ogni chiave introdotta dalla Foundation.
- Default sicuri: l'assenza di configurazione non deve allentare nulla.
- Verificare `php artisan about` dopo ogni deploy.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `env()` fuori da `config/` | Valore `null` in produzione | `config()` |
| `.env.example` non aggiornato | Il progetto non si avvia altrove | Aggiornare sempre |
| `APP_DEBUG=true` in produzione | Esposizione di configurazione | `false` |
| Rimuovere un bootstrapper | Fughe di dati tra tenant | Non modificare l'elenco |
| Cambiare `TENANT_DB_PREFIX` dopo il primo tenant | Database non più trovati | Immutabile |
| `FILESYSTEM_DISK=public` | File accessibili senza autorizzazione | Disco `tenant` |
| `SESSION_DRIVER=file` in produzione | Sessioni perse con più container | `redis` |

---

## Checklist

- [ ] Nessun `env()` fuori da `config/`.
- [ ] `.env.example` allineato.
- [ ] `APP_DEBUG=false` in staging e produzione.
- [ ] `TELESCOPE_ENABLED=false` in produzione.
- [ ] `central_domains` configurato.
- [ ] Tutti i bootstrapper attivi.
- [ ] `FILESYSTEM_DISK` non è `public`.
- [ ] Sessioni e cache su Redis in produzione.
- [ ] Conservazione dell'audit conforme al dominio.

---

## Riferimenti

- [Foundation](../../foundation/README.md)
- [Multitenancy](../../architecture/03-multitenancy-overview.md)
- [Regole di configurazione](../../rules/configuration.md)
- [Ambienti](../05-operations/01-environments.md)
