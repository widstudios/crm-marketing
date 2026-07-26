# Foundation

> Il pacchetto PHP `widstudios/foundation`: il codice che ogni progetto della Factory eredita, e che
> quindi non si scrive mai due volte.

---

## Indice

1. [Descrizione](#descrizione)
2. [Che cosa contiene](#che-cosa-contiene)
3. [Che cosa non contiene, e perché](#che-cosa-non-contiene-e-perché)
4. [Struttura](#struttura)
5. [Installazione](#installazione)
6. [I cinque punti dell'isolamento](#i-cinque-punti-dellisolamento)
7. [Contratto pubblico](#contratto-pubblico)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

La Foundation è la risposta a una domanda che si ripresenta a ogni nuovo progetto: *quanto di
questo l'ho già scritto?*

In un sistema multitenant la risposta è «quasi tutto ciò che è difficile». La risoluzione del
tenant, la riconfigurazione del database, il ripristino del contesto nei job, la composizione delle
chiavi di cache: sono le parti in cui un errore non produce un guasto ma una **fuga di dati**, e
sono esattamente quelle che nessuno vuole riscrivere a mano per la quarta volta.

Nella gerarchia delle fonti di verità la Foundation viene subito dopo le regole e l'architettura,
per una ragione precisa: **è la specifica più precisa che esiste**. Un documento descrive
un'intenzione, il codice descrive il comportamento. Quando i due divergono, o il codice è sbagliato
o il documento è vecchio — e in entrambi i casi il codice dice la verità su cosa succede oggi.

---

## Che cosa contiene

| Area | Cosa risolve |
|---|---|
| `Tenancy/` | risoluzione, apertura e chiusura del contesto tenant; i quattro bootstrapper |
| `Contracts/` | le interfacce che un progetto implementa o riceve per iniezione |
| `Actions/` | base per le Action: transazione e `afterCommit` |
| `Data/` | base per i DTO `readonly`, con serializzazione dei value object |
| `Repositories/` | base Eloquent per repository e Query object, con lista bianca sugli ordinamenti |
| `Modules/` | registro dei moduli, dipendenze, ordinamento topologico |
| `Concerns/` | `TenantAware` per i job, cache tenant-scoped, registrazione dell'audit |
| `Audit/` | contratto e voce di audit; l'implementazione arriva dal modulo `audit` |
| `Support/` | `Clock` iniettabile, con orologio reale e orologio fermo per i test |
| `Exceptions/` | gerarchia con costruttori nominati |
| `Testing/` | aiuti che rendono banale il test di isolamento tra tenant |

---

## Che cosa non contiene, e perché

Le assenze sono decisioni, non lacune.

| Assente | Perché |
|---|---|
| Il model `Tenant` | Ogni progetto ha colonne diverse. La Foundation conosce il contratto [`Tenant`](src/Contracts/Tenancy/Tenant.php), non una tabella. |
| Le migration di piattaforma | Sono la forma del landlord, e la forma appartiene al progetto. La Foundation dice quali informazioni servono, non come conservarle. |
| Ruoli e permessi | Il modello di autorizzazione è architettura, non infrastruttura. La Foundation non impone un pacchetto di gestione dei ruoli. |
| Registrazione dei middleware sulle rotte | Quali rotte siano «di tenant» è una decisione del progetto. La Foundation fornisce i middleware, il progetto li applica. |
| Qualunque entità di dominio | È la regola di ammissione della Factory: un artefatto che non servirebbe identico in tre software diversi non appartiene qui. |
| Un'implementazione dell'audit | Un progetto senza requisiti di tracciamento non deve portarsi dietro le tabelle. Il contratto sì, l'implementazione no. |

---

## Struttura

```
foundation/
├── composer.json
├── config/
│   └── foundation.php          Ogni chiave è contratto pubblico
├── docs/                       Documentazione approfondita, per area
├── src/
│   ├── FoundationServiceProvider.php
│   ├── Contracts/              Tenancy · Modules · Repositories · Audit · Support
│   ├── Tenancy/                Context · Manager · Resolvers · Bootstrappers
│   │                           Middleware · Jobs · Console · Events
│   ├── Actions/  Data/  Repositories/  Services/
│   ├── Concerns/  Modules/  Audit/  Support/  Exceptions/  Testing/
└── tests/
```

---

## Installazione

```bash
composer require widstudios/foundation
php artisan vendor:publish --tag=foundation-config
```

Il progetto deve fornire **un solo** binding: il repository dei tenant.

```php
// app/Providers/AppServiceProvider.php

use WidStudios\Foundation\Contracts\Tenancy\TenantRepository;

public function register(): void
{
    $this->app->singleton(TenantRepository::class, EloquentTenantRepository::class);
}
```

Guida completa: [`docs/01-installazione.md`](docs/01-installazione.md).

---

## I cinque punti dell'isolamento

L'isolamento tra clienti è garantito dalla struttura in un punto solo: il database. Negli altri
dipende dal **codice**, e il codice si dimentica. Questi sono i cinque punti, e per ognuno la
Foundation dice dove sta la garanzia.

| # | Punto | Dove sta la garanzia | Se manca |
|---|---|---|---|
| 1 | **Database** | [`DatabaseBootstrapper`](src/Tenancy/Bootstrappers/DatabaseBootstrapper.php): `purge()` **prima** di `reconnect()` | si scrive nel database del cliente precedente |
| 2 | **Cache** | [`TenantCacheKey`](src/Tenancy/TenantCacheKey.php) + [`CacheBootstrapper`](src/Tenancy/Bootstrappers/CacheBootstrapper.php) | un cliente legge i numeri di un altro |
| 3 | **Code** | trait [`TenantAware`](src/Concerns/TenantAware.php) + [`RestoreTenantContext`](src/Tenancy/Jobs/RestoreTenantContext.php) | il job scrive nel database sbagliato, in modo intermittente |
| 4 | **Storage** | [`FilesystemBootstrapper`](src/Tenancy/Bootstrappers/FilesystemBootstrapper.php), disco privato per tenant | i file di un cliente sono raggiungibili da un altro |
| 5 | **Comandi CLI** | [`tenants:artisan`](src/Tenancy/Console/TenantsArtisanCommand.php) | il comando gira sul landlord e non trova le tabelle |

Ognuno dei cinque ha un test obbligatorio. Vedi
[`docs/02-tenancy.md`](docs/02-tenancy.md) e la
[checklist di sicurezza](../checklists/security-checklist.md).

---

## Contratto pubblico

È pubblico — e quindi la sua modifica è **MAJOR** — tutto ciò che un progetto può usare
direttamente:

- le interfacce in `src/Contracts/`
- le classi base estendibili: `BaseAction`, `BaseData`, `BaseEloquentRepository`, `BaseQuery`,
  `BaseService`, `ModuleDefinition`, `EloquentTenantRepository`
- i trait in `src/Concerns/` e in `src/Testing/`
- gli eventi emessi e il loro payload
- le chiavi di `config/foundation.php`
- le firme dei comandi Artisan

**Non** è pubblico: tutto ciò che è marcato `@internal` e i dettagli implementativi delle classi
`final` che non hanno un'interfaccia corrispondente.

Regole complete: [`governance/versioning.md`](../governance/versioning.md).

---

## Esempi

### Un'Action che usa la Foundation

```php
final class RegisterMovementAction extends BaseAction
{
    use RecordsAudit;

    public function __construct(
        DatabaseManager $database,
        private readonly BatchRepository $batches,
    ) {
        parent::__construct($database);
    }

    public function execute(RegisterMovementData $data): StockMovement
    {
        return $this->transaction(function () use ($data): StockMovement {
            // Lock: senza, due scarichi simultanei superano entrambi la verifica.
            $batch = $this->batches->findForUpdateOrFail($data->batchId);

            // La regola sta nell'entità, non qui.
            $batch->assertCanRelease($data->quantity);

            $movement = $batch->release($data->quantity, $data->reason);
            $this->batches->save($batch);

            $this->audit('movement.registered', $movement->getKey(), [
                'batch_id' => $batch->getKey(),
                'type' => $data->type->value,
            ]);

            // Dopo il commit: prima, il listener non troverebbe i dati.
            $this->afterCommit(fn () => event(new MovementRegistered($movement->getKey())));

            return $movement;
        });
    }
}
```

### Un job che non può perdere il tenant

```php
final class RecalculateStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TenantAware;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $timeout = 120;

    public function __construct(public int $batchId) {}

    public function handle(RecalculateStockAction $action): void
    {
        $action->execute($this->batchId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Ricalcolo giacenza fallito', [
            'batch_id' => $this->batchId,
            'tenant' => $this->tenantSlug(),
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

### Il test che nessuno vuole scrivere, in tre righe

```php
uses(InteractsWithTenants::class);

it('non espone i movimenti di un altro tenant', function (): void {
    $this->assertTenantsAreIsolated(
        first: 'acme',
        second: 'globex',
        seed: fn () => StockMovement::factory()->count(5)->create(),
        count: fn (): int => StockMovement::query()->count(),
    );
});
```

---

## Best practice

- Prima di scrivere una classe di supporto, cercarla qui: se esiste già, usarla; se dovrebbe
  esistere, proporla.
- Dipendere dai contratti, non dalle implementazioni: è ciò che permette di sostituirle nei test.
- Comporre le chiavi di cache solo con `TenantCacheKey`, anche quando sembra superfluo.
- Iniettare `Clock` invece di chiamare `now()`: rende verificabile ciò che dipende dalla data.
- Promuovere nella Foundation il codice che compare identico nel terzo progetto — non nel secondo,
  perché due occorrenze non dimostrano ancora un pattern.
- Aggiungere il test di isolamento con `InteractsWithTenants` per ogni entità nuova, sempre.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `reconnect()` senza `purge()` | Si scrive nel database del tenant precedente | L'ordine è nel `DatabaseBootstrapper` |
| Chiave di cache scritta a mano | Un cliente legge i dati di un altro | `TenantCacheKey::for()` |
| Job senza `TenantAware` | Esecuzione nel contesto sbagliato, in modo intermittente | Trait obbligatorio |
| `middleware()` sovrascritto in un job | Il ripristino del contesto sparisce senza segnali | Sovrascrivere `jobMiddleware()` |
| Comando Artisan lanciato direttamente | Gira sul landlord, non trova le tabelle | `tenants:artisan` |
| Logica di dominio dentro la Foundation | Contamina tutti i progetti | Nel progetto, o in un modulo |
| Metodi generici in un repository | La logica di lettura si sparpaglia | Query object con un nome |
| `ORDER BY` da parametro della richiesta | Injection che i parametri legati non fermano | `BaseQuery::sortBy()` con lista bianca |
| Evento emesso dentro la transazione | Il listener non trova i dati | `afterCommit()` |

---

## Checklist

- [ ] Il progetto fornisce il binding di `TenantRepository`.
- [ ] Il model del tenant implementa il contratto `Tenant`.
- [ ] I middleware di tenancy sono applicati ai gruppi di rotte corretti.
- [ ] Ogni job usa `TenantAware`.
- [ ] Nessuna chiave di cache composta a mano.
- [ ] I comandi che toccano dati di dominio girano via `tenants:artisan`.
- [ ] Esiste un test di isolamento per ogni entità.
- [ ] Nessuna logica di dominio è finita nella Foundation.

---

## Riferimenti

- [Documentazione della Foundation](docs/README.md)
- [Multitenancy](../architecture/03-multitenancy-overview.md) · [ADR-0002](../architecture/decisions/0002-tenant-isolation-strategy.md)
- [Action Pattern](../rules/action-pattern.md) · [Repository Pattern](../rules/repository-pattern.md) · [DTO](../rules/dto.md)
- [Foundation Agent](../agents/01-foundation-agent.md) · [Checklist](../checklists/foundation-checklist.md)
- [Versionamento](../governance/versioning.md)
