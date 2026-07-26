# Foundation — Moduli

> Registro, dipendenze e provider: come un modulo si dichiara e cosa significa che sia
> «indipendente».

---

## Indice

1. [Descrizione](#descrizione)
2. [Che cos'è un modulo](#che-cosè-un-modulo)
3. [Dichiarare un modulo](#dichiarare-un-modulo)
4. [Il service provider](#il-service-provider)
5. [Dipendenze](#dipendenze)
6. [Permessi](#permessi)
7. [Verificare l'indipendenza](#verificare-lindipendenza)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

Un modulo è una funzionalità che si può **togliere**. Non è una cartella con dentro del codice
correlato: è un'unità la cui rimozione lascia il software funzionante, salvo per ciò che il modulo
forniva.

La differenza è verificabile, ed è l'unica definizione utile: se disattivare un modulo rompe la
suite di test, quel modulo non era un modulo.

Decisione di riferimento: [ADR-0004](../../architecture/decisions/0004-modular-system.md).

---

## Che cos'è un modulo

| È un modulo | Non è un modulo |
|---|---|
| Registro di audit | Il livello di dominio |
| Gestione documentale | Le entità principali del gestionale |
| CMS e landing page | L'autenticazione |
| Notifiche multicanale | Il sistema di permessi |
| Reportistica | La tenancy |

Il criterio: **si può togliere?** L'autenticazione non si può togliere, e quindi non è un modulo:
metterla in uno significherebbe avere un modulo che tutti gli altri richiedono, cioè non un modulo.

---

## Dichiarare un modulo

```php
<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use WidStudios\Foundation\Modules\ModuleDefinition;

final class AuditModule extends ModuleDefinition
{
    protected string $name = 'audit';

    protected string $version = '1.2.0';

    protected string $description = 'Registro immutabile delle mutazioni sui dati sensibili.';

    protected array $dependsOn = [];

    protected array $permissions = [
        'audit.view',
        'audit.export',
    ];

    protected array $tenantMigrationPaths = [
        'app/Modules/Audit/database/migrations/tenant',
    ];
}
```

L'attivazione è in configurazione, non nel codice:

```php
// config/foundation.php
'modules' => [
    'enabled' => ['audit', 'documents', 'notifications'],
],
```

Un modulo presente ma non elencato è registrato e inattivo: `ModuleRegistry` lo conosce, ma il suo
provider non carica nulla. È la condizione che rende verificabile l'indipendenza.

---

## Il service provider

```php
final class AuditServiceProvider extends ModuleServiceProvider
{
    protected function module(): Module
    {
        return new AuditModule();
    }

    protected function bootModule(Module $module): void
    {
        $this->app->bind(AuditLogger::class, DatabaseAuditLogger::class);

        $this->loadMigrationsFrom(__DIR__.'/database/migrations/tenant');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadTranslationsFrom(__DIR__.'/lang', 'audit');

        Gate::policy(AuditEntry::class, AuditEntryPolicy::class);
    }
}
```

`bootModule()` viene invocato **solo** se il modulo è attivo. Tutto ciò che sta fuori — in `boot()`
o in `register()` — viene eseguito sempre, anche a modulo disattivato, e quindi non deve lasciare
tracce: niente rotte, niente traduzioni, niente migration.

Un modulo disattivato che continua a registrare rotte rende la parola «disattivato» priva di
conseguenze, e l'indipendenza smette di essere verificabile.

---

## Dipendenze

```php
protected array $dependsOn = ['documents'];
```

`ModuleManager::enabled()` restituisce i moduli attivi **ordinati per dipendenza**: ogni modulo
compare dopo quelli da cui dipende. Serve a una cosa concreta: garantire che migration e seeder di
un modulo girino dopo quelli dei moduli su cui si appoggia.

Due condizioni vengono rifiutate **all'avvio**, non a runtime:

| Condizione | Eccezione |
|---|---|
| Dipendenza dichiarata ma non attiva | `MissingModuleDependency::for()` |
| Ciclo tra le dipendenze | `MissingModuleDependency::circular()` |

All'avvio, perché è il momento in cui il messaggio d'errore può ancora essere utile: una dipendenza
mancante scoperta al primo utilizzo si manifesta come un errore incomprensibile in un punto lontano
dalla causa.

Le dipendenze vanno tenute al minimo. Un grafo in cui ogni modulo dipende da tre altri non è un
sistema modulare: è un sistema monolitico con le cartelle ordinate.

---

## Permessi

I permessi introdotti dal modulo si dichiarano, e il seeder di sistema li crea e li assegna al ruolo
amministratore:

```php
foreach (app(ModuleRegistry::class)->permissions() as $permission) {
    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'tenant']);
}

Role::firstOrCreate(['name' => 'tenant_admin', 'guard_name' => 'tenant'])
    ->syncPermissions(Permission::all());
```

Senza l'assegnazione al ruolo amministratore, una funzionalità nuova è **invisibile a tutti** dopo
il deploy: esiste, funziona, e nessuno ha il permesso di vederla. È uno dei difetti che si scoprono
solo in produzione, perché in sviluppo si lavora con un utente che ha tutti i permessi.

---

## Verificare l'indipendenza

```bash
php artisan module:disable audit
composer test
```

La suite deve restare **verde**, salvo i test del modulo stesso. Se fallisce qualcos'altro, esiste
una dipendenza non dichiarata: qualcuno usa il modulo senza che il modulo lo sappia.

La verifica va in pipeline, un modulo per volta. Farla a mano significa non farla.

---

## Esempi

### Un modulo che dipende da un altro

```php
final class ReportingModule extends ModuleDefinition
{
    protected string $name = 'reporting';
    protected string $version = '0.4.0';
    protected string $description = 'Report pianificati ed esportazioni.';
    protected array $dependsOn = ['documents'];      // i report vengono archiviati
    protected array $permissions = ['report.view', 'report.schedule', 'report.export'];
}
```

Con `enabled = ['reporting']` e `documents` non attivo, l'applicazione **non parte**, e il messaggio
dice esattamente cosa fare:

```
Il modulo 'reporting' richiede 'documents', che non risulta attivo.
Aggiungerlo a config('foundation.modules.enabled') o disattivare 'reporting'.
```

### Uso opzionale di un modulo dal codice applicativo

```php
// ✓ Il codice funziona con e senza il modulo
if ($this->modules->isEnabled('notifications')) {
    NotifyExpiringBatchesJob::dispatch()->afterCommit();
}
```

Un `if` di questo tipo è ammesso e utile. Ciò che non è ammesso è usare le classi di un modulo senza
verificarne l'attivazione: il codice funziona finché il modulo c'è, e si rompe con un errore di
classe non trovata il giorno in cui qualcuno lo disattiva.

---

## Best practice

- Un modulo è tale solo se si può togliere: verificarlo, non presumerlo.
- Tenere le dipendenze tra moduli al minimo; una dipendenza in più costa più di una duplicazione.
- Dichiarare tutti i permessi nel modulo: sono l'unico modo perché la funzionalità sia visibile.
- Registrare rotte, viste e traduzioni **solo** dentro `bootModule()`.
- Aggiungere alla pipeline la verifica di disattivazione per ogni modulo nuovo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Modulo che non si può disattivare | Non è un modulo, ed è chiamato così | Verificare con `module:disable` |
| Registrazioni fuori da `bootModule()` | «Disattivato» non significa nulla | Tutto dentro `bootModule()` |
| Dipendenza non dichiarata | Errore incomprensibile a runtime | Dichiararla in `dependsOn` |
| Dipendenze circolari | Ordine di caricamento impossibile | Grafo aciclico |
| Permessi non dichiarati | Funzionalità invisibile dopo il deploy | Elenco nel modulo, seeder che assegna |
| Classi di un modulo usate senza verifica | Errore di classe non trovata alla disattivazione | `isEnabled()` |
| Troppe dipendenze tra moduli | Monolite con le cartelle ordinate | Ridurre l'accoppiamento |

---

## Checklist

- [ ] Il modulo dichiara nome, versione, descrizione.
- [ ] Le dipendenze sono dichiarate e minime.
- [ ] Tutti i permessi sono elencati.
- [ ] Rotte, viste e traduzioni si registrano solo in `bootModule()`.
- [ ] Il seeder crea i permessi e li assegna al ruolo amministratore.
- [ ] `module:disable` seguito da `composer test` lascia la suite verde.
- [ ] La verifica di indipendenza è in pipeline.

---

## Riferimenti

- [ADR-0004](../../architecture/decisions/0004-modular-system.md)
- [Sistema modulare](../../architecture/10-modular-system.md) · [Contratto di modulo](../../architecture/11-module-contract.md)
- [Catalogo dei moduli](../../modules/README.md) · [Workflow di modulo](../../workflows/20-module-workflow.md)
