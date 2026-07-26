# Blueprint di modulo

> La forma di un modulo: struttura, contratto, percorso di costruzione, verifica di indipendenza.

---

## Indice

1. [Descrizione](#descrizione)
2. [Prima di iniziare](#prima-di-iniziare)
3. [Struttura](#struttura)
4. [Il contratto del modulo](#il-contratto-del-modulo)
5. [Il service provider](#il-service-provider)
6. [Permessi e seeder](#permessi-e-seeder)
7. [Integrazione con il resto](#integrazione-con-il-resto)
8. [La verifica di indipendenza](#la-verifica-di-indipendenza)
9. [La scheda di catalogo](#la-scheda-di-catalogo)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Questo documento descrive come si costruisce un modulo conforme. Non è una raccolta di consigli: la
struttura qui sotto è ciò che rende un modulo **verificabilmente** indipendente, e ogni scostamento
ha una conseguenza dichiarata.

Il percorso completo, con i quality gate, è in
[`workflows/20-module-workflow.md`](../../workflows/20-module-workflow.md).

---

## Prima di iniziare

Tre domande, in ordine. Una risposta negativa alla prima o alla seconda ferma il lavoro.

**1. Servirebbe identica in almeno tre software diversi?**
Due occorrenze dimostrano una somiglianza, tre dimostrano un pattern. Se il terzo progetto non
esiste ancora, aspettare costa meno che piegare un'astrazione costruita su un campione
insufficiente.

**2. Si può togliere?**
Se disattivarla rompe il software, non è un modulo opzionale. Le uniche eccezioni sono i moduli di
base (`tenancy`, `auth`), e l'elenco è chiuso.

**3. Esiste già, in una forma leggermente diversa?**
Il [catalogo](../README.md) va letto per intero prima di proporre un modulo nuovo. Due moduli che
fanno quasi la stessa cosa sono peggio di uno che ne fa una sola bene.

---

## Struttura

```
app/Modules/{{ Module }}/
├── {{ Module }}Module.php                   il contratto: nome, versione, dipendenze, permessi
├── {{ Module }}ServiceProvider.php          registra, ma solo se attivo
├── MODULE.md                                la scheda: cosa fornisce, cosa non fa
│
├── Domain/                                  entità, enum, value object, eventi, contratti
├── Application/                             Action, DTO, Query object
├── Infrastructure/                          repository, implementazioni dei contratti
├── Http/                                    controller, request, resource, rotte
├── Filament/                                Resource, widget, pagine del modulo
├── Policies/
├── Jobs/  Listeners/  Console/
│
├── config/{{ module }}.php
├── database/
│   ├── migrations/tenant/
│   ├── migrations/landlord/
│   └── seeders/System/
├── lang/it/
├── resources/views/
├── routes/{web.php, api.php}
└── tests/{Unit, Feature, Tenant}/
```

Un modulo è un'applicazione in miniatura, con gli stessi livelli e le stesse regole. Non è una
semplificazione: un modulo che salta i livelli «perché è piccolo» diventa il posto in cui la logica
si accumula senza controllo.

I **test del modulo stanno nel modulo**. Se stessero nella suite del progetto, disattivare il modulo
li farebbe fallire, e la verifica di indipendenza sarebbe impossibile.

---

## Il contratto del modulo

```php
<?php

declare(strict_types=1);

namespace App\Modules\{{ Module }};

use WidStudios\Foundation\Modules\ModuleDefinition;

final class {{ Module }}Module extends ModuleDefinition
{
    protected string $name = '{{ module }}';

    protected string $version = '1.0.0';

    protected string $description = '…';

    /** Al minimo indispensabile: ogni dipendenza costa più di una duplicazione. */
    protected array $dependsOn = [];

    /** Se manca un permesso qui, la funzionalità è invisibile a tutti dopo il deploy. */
    protected array $permissions = [
        '{{ module }}.view',
        '{{ module }}.create',
        '{{ module }}.update',
        '{{ module }}.delete',
    ];

    protected array $tenantMigrationPaths = [
        'app/Modules/{{ Module }}/database/migrations/tenant',
    ];
}
```

Il **nome** è in `snake_case` e non cambia mai: compare in configurazione, nei permessi, nelle
chiavi di traduzione e nei riferimenti degli altri moduli.

---

## Il service provider

```php
final class {{ Module }}ServiceProvider extends ModuleServiceProvider
{
    protected function module(): Module
    {
        return new {{ Module }}Module();
    }

    protected function bootModule(Module $module): void
    {
        $this->app->bind(SomeContract::class, SomeImplementation::class);

        $this->loadMigrationsFrom(__DIR__.'/database/migrations/tenant');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadTranslationsFrom(__DIR__.'/lang', '{{ module }}');
        $this->loadViewsFrom(__DIR__.'/resources/views', '{{ module }}');

        Gate::policy({{ Entity }}::class, {{ Entity }}Policy::class);
    }
}
```

`bootModule()` viene invocato **solo** se il modulo è attivo. Tutto ciò che sta fuori — in `boot()`
o in `register()` — viene eseguito sempre, anche a modulo disattivato.

Un modulo disattivato che continua a registrare rotte rende la parola «disattivato» priva di
conseguenze, e l'indipendenza smette di essere verificabile. Non è un dettaglio formale: è ciò che
distingue un sistema modulare da un monolite con le cartelle ordinate.

---

## Permessi e seeder

```php
foreach (app(ModuleRegistry::class)->permissions() as $permission) {
    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'tenant']);
}

Role::firstOrCreate(['name' => 'tenant_admin', 'guard_name' => 'tenant'])
    ->syncPermissions(Permission::all());
```

La seconda istruzione è quella che si dimentica. Senza, una funzionalità nuova è **invisibile a
tutti** dopo il deploy: esiste, funziona, e nessuno ha il permesso di vederla. Non si scopre in
sviluppo, dove si lavora con un utente che ha tutti i permessi.

I seeder di sistema di un modulo sono idempotenti: girano a ogni deploy e a ogni provisioning di
tenant.

---

## Integrazione con il resto

Un modulo si integra in tre modi, in ordine di preferenza.

### 1. Implementando un contratto della Foundation

```php
$this->app->bind(AuditLogger::class, DatabaseAuditLogger::class);
```

È la forma migliore: il codice applicativo invoca sempre il contratto e non sa se il modulo esista.
Senza il modulo, l'implementazione è quella nulla, e la scelta è visibile in configurazione.

### 2. Emettendo ed ascoltando eventi

```php
// Il modulo ascolta un evento del dominio, senza che il dominio lo sappia
Event::listen(DocumentArchived::class, NotifyDocumentArchived::class);
```

Accoppiamento minimo: chi emette non conosce chi ascolta.

### 3. Con una verifica esplicita di attivazione

```php
if ($this->modules->isEnabled('notifications')) {
    NotifyExpiringBatchesJob::dispatch()->afterCommit();
}
```

Ammesso, e a volte inevitabile. Ciò che non è ammesso è usare le classi di un modulo **senza** la
verifica: il codice funziona finché il modulo c'è, e si rompe con un errore di classe non trovata il
giorno in cui qualcuno lo disattiva.

---

## La verifica di indipendenza

```bash
php artisan module:disable {{ module }}
composer test
php artisan module:enable {{ module }}
```

La suite deve restare **verde**, salvo i test del modulo stesso.

Se fallisce qualcos'altro, esiste una dipendenza non dichiarata. Le cause tipiche, in ordine di
frequenza:

| Sintomo | Causa | Correzione |
|---|---|---|
| Classe non trovata | uso diretto senza `isEnabled()` | contratto o verifica esplicita |
| Rotta inesistente | un'altra parte genera un URL del modulo | rotta condizionale, o contratto |
| Permesso mancante | un test presuppone i permessi del modulo | permessi nel modulo, test nel modulo |
| Traduzione mancante | chiave del modulo usata altrove | chiave nel progetto |
| Vincolo di chiave esterna | una tabella del progetto punta al modulo | ripensare la direzione della dipendenza |

L'ultimo è il più serio: se una tabella del progetto ha un vincolo verso una tabella del modulo, il
modulo **non è togglibile** e la struttura va ripensata prima di proseguire.

La verifica gira in pipeline, un modulo per volta.

---

## La scheda di catalogo

Ogni modulo ha una scheda in [`../catalog/`](../catalog/README.md), con queste sezioni:

| Sezione | Contenuto |
|---|---|
| Descrizione | a che problema risponde |
| Che cosa fornisce | tabelle, permessi, comandi, eventi, contratti implementati |
| Che cosa **non** fa | i confini, esplicitamente |
| Dipendenze | moduli richiesti, e perché |
| Configurazione | chiavi e valori predefiniti |
| Integrazione | come il resto del software lo usa |
| Adozione | passi per attivarlo su un progetto esistente |

La sezione «che cosa non fa» è la più utile del documento: è quella che evita che il modulo venga
scelto per un problema che non risolve, e che venga esteso fino a risolverlo male.

---

## Esempi

### Un modulo che dipende da un altro

```php
final class ReportingModule extends ModuleDefinition
{
    protected string $name = 'reporting';
    protected array $dependsOn = ['documents'];      // i report vengono archiviati
    protected array $permissions = ['report.view', 'report.schedule', 'report.export'];
}
```

Con `documents` non attivo, l'applicazione **non parte**, e il messaggio dice esattamente cosa fare.
È deliberato: una dipendenza mancante scoperta al primo utilizzo si manifesta come un errore
incomprensibile in un punto lontano dalla causa.

### Un modulo che non passa la verifica

```
php artisan module:disable documents
composer test

FAILED  Tests\Feature\BatchTest > allega la scheda tecnica
        Class "App\Modules\Documents\Models\Document" not found
```

Il modulo non è indipendente: il dominio del progetto usa direttamente una classe del modulo. La
correzione non è aggiungere un `if`, ma spostare l'interazione su un contratto — perché il dominio
non deve sapere che i documenti esistano.

---

## Best practice

- Costruire il modulo con gli stessi livelli del progetto: un modulo «piccolo» diventa grande.
- Tenere i test del modulo nel modulo.
- Preferire l'integrazione per contratto a quella per verifica di attivazione.
- Dichiarare tutti i permessi, e assegnarli al ruolo amministratore nel seeder.
- Eseguire la verifica di indipendenza a ogni modifica, non solo alla fine.
- Scrivere la sezione «che cosa non fa» per prima: chiarisce il confine mentre è ancora modificabile.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Registrazioni fuori da `bootModule()` | «Disattivato» non significa nulla | Tutto dentro `bootModule()` |
| Test del modulo nella suite del progetto | La verifica di indipendenza è impossibile | Test nel modulo |
| Uso diretto di classi del modulo | Errore di classe non trovata alla disattivazione | Contratto, o `isEnabled()` |
| Vincolo di chiave esterna verso il modulo | Il modulo non è togglibile | Ripensare la direzione |
| Dipendenza non dichiarata | Errore incomprensibile a runtime | `dependsOn` |
| Permessi non assegnati al ruolo admin | Funzionalità invisibile | `syncPermissions` |
| Modulo che salta i livelli | La logica si accumula senza controllo | Stessi livelli del progetto |
| Logica verticale nel modulo | Contamina tutti i progetti | Nel progetto |
| Sezione «cosa non fa» omessa | Il modulo viene esteso fino a snaturarsi | Scriverla per prima |

---

## Checklist

- [ ] Le tre domande preliminari hanno risposta affermativa.
- [ ] La struttura rispetta i livelli del progetto.
- [ ] Il contratto dichiara nome, versione, dipendenze e **tutti** i permessi.
- [ ] Tutte le registrazioni stanno dentro `bootModule()`.
- [ ] Il seeder di sistema è idempotente e assegna i permessi al ruolo amministratore.
- [ ] I test del modulo stanno nel modulo.
- [ ] `module:disable` seguito da `composer test` lascia la suite verde.
- [ ] La verifica di indipendenza è in pipeline.
- [ ] `MODULE.md` esiste e la scheda di catalogo è collegata dall'indice.

---

## Riferimenti

- [Catalogo dei moduli](../README.md) · [Schede](../catalog/README.md)
- [Workflow di modulo](../../workflows/20-module-workflow.md) · [Prompt: aggiungi un modulo](../../prompts/library/add-module.md)
- [Sistema modulare](../../architecture/10-modular-system.md) · [Contratto di modulo](../../architecture/11-module-contract.md)
- [Foundation — Moduli](../../foundation/docs/05-moduli.md)
- [Templates](../../templates/README.md)
