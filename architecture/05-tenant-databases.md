# Database tenant

> Come è fatto il database di un cliente, come si crea, come si mantiene allineato e come si
> gestiscono N database con una sola base di codice.

---

## Indice

1. [Descrizione](#descrizione)
2. [Struttura e denominazione](#struttura-e-denominazione)
3. [Contenuto](#contenuto)
4. [Connessione dinamica](#connessione-dinamica)
5. [Migration](#migration)
6. [Seeder](#seeder)
7. [Allineamento dello schema](#allineamento-dello-schema)
8. [Dimensionamento](#dimensionamento)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Ogni cliente ha un database dedicato con **lo stesso schema** di tutti gli altri. È la stessa
applicazione che opera su database diversi: il codice non sa su quale sta lavorando, e non deve
saperlo.

Questa proprietà — il codice che ignora il tenant — è ciò che rende l'isolamento robusto: non
dipende dall'attenzione di chi scrive le query.

---

## Struttura e denominazione

```
MySQL
├── landlord                    database di piattaforma
├── tenant_acme                 cliente ACME
├── tenant_globex               cliente Globex
└── tenant_initech              cliente Initech
```

| Aspetto | Regola |
|---|---|
| Nome | `<prefisso><slug>`, dove il prefisso è configurabile (`tenant_`) |
| Charset / collation | `utf8mb4` / `utf8mb4_unicode_ci` |
| Engine | InnoDB |
| Creazione | automatica, durante il provisioning |
| Utente MySQL | dedicato per tenant, con privilegi solo sul proprio database |
| Slug | immutabile dopo la creazione |

L'utente MySQL dedicato è la seconda linea di difesa: anche in caso di difetto applicativo, le
credenziali del tenant non permettono di leggere altri database.

---

## Contenuto

Tutto ciò che è del cliente:

| Categoria | Esempi |
|---|---|
| Utenti e accessi | utenti, ruoli, permessi assegnati, sessioni |
| Entità di dominio | articoli, lotti, movimenti, fornitori, documenti |
| Configurazione applicativa | preferenze, personalizzazioni, template |
| Tracciabilità | audit log, activity log |
| Notifiche | notifiche in archivio, preferenze |
| File (riferimenti) | metadati; i contenuti sul disco del tenant |

Ciò che **non** vi appartiene: dati di piattaforma, contenuti pubblici del sito, metriche
aggregate.

Nessuna tabella tenant ha una colonna `tenant_id`: sarebbe ridondante e induce query filtrate che
danno una falsa impressione di sicurezza.

---

## Connessione dinamica

La connessione `tenant` è definita come modello, con i parametri di collegamento sostituiti a
runtime.

```php
// config/database.php
'tenant' => [
    'driver' => 'mysql',
    'host' => env('TENANT_DB_HOST', '127.0.0.1'),
    'port' => env('TENANT_DB_PORT', '3306'),
    'database' => null,              // impostato dal bootstrapper
    'username' => env('TENANT_DB_USERNAME'),
    'password' => env('TENANT_DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'strict' => true,
],
```

```php
final class DatabaseBootstrapper implements TenantBootstrapper
{
    public function bootstrap(Tenant $tenant): void
    {
        config(['database.connections.tenant.database' => $tenant->database_name]);

        DB::purge('tenant');
        DB::reconnect('tenant');

        // La connessione predefinita diventa quella del tenant:
        // i model di dominio non devono dichiararla.
        config(['database.default' => 'tenant']);
    }

    public function revert(): void
    {
        config(['database.default' => 'landlord']);
        DB::purge('tenant');
    }
}
```

`DB::purge()` prima di `reconnect()` è indispensabile: senza, la connessione precedente resta in
cache e il tenant successivo lavorerebbe sul database del precedente. È il difetto più grave che
si possa introdurre in questo punto.

---

## Migration

| Aspetto | Landlord | Tenant |
|---|---|---|
| Cartella | `database/migrations/landlord/` | `database/migrations/tenant/` |
| Esecuzioni | 1 | N |
| Comando | `migrate --database=landlord` | `tenants:migrate` |
| Al provisioning | — | tutta la storia, dall'inizio |
| Reversibilità | obbligatoria | obbligatoria |

Vincoli specifici delle migration tenant:

1. **Devono funzionare su un database vuoto** (provisioning) e su uno pieno (aggiornamento).
2. **Non devono dipendere dai dati**: un tenant può non avere righe.
3. **Devono essere veloci**: il tempo si moltiplica per N.
4. **Non devono trasformare dati**: quello è compito di comandi dedicati.
5. **Devono essere idempotenti rispetto ai tenant**: rieseguire su un tenant allineato non
   produce errori.

```bash
php artisan tenants:migrate --chunk=10 --delay=5
```

L'esecuzione a lotti con pausa evita di saturare il server: cento `ALTER TABLE` simultanei su
tabelle grandi bloccano il database per tutti.

---

## Seeder

| Cartella | Contenuto | Al provisioning | Ad ogni deploy | In produzione |
|---|---|---|---|---|
| `System/` | ruoli, permessi, stati, tipi | sì | sì | sì |
| `Demo/` | dati di esempio | solo su richiesta | no | mai |

I seeder di sistema devono essere **idempotenti**: girano ad ogni deploy per aggiungere i nuovi
permessi introdotti dalle funzionalità.

```php
final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('permissions.list') as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'tenant']);
        }

        // Il ruolo amministratore riceve automaticamente i permessi nuovi
        Role::firstOrCreate(['name' => 'tenant_admin', 'guard_name' => 'tenant'])
            ->syncPermissions(Permission::all());
    }
}
```

Senza l'ultima riga, ogni nuova funzionalità risulta invisibile a tutti dopo il deploy: è l'errore
che si scopre dal cliente, non dai test.

---

## Allineamento dello schema

Con N database, il disallineamento è una condizione normale e va gestita, non evitata.

```bash
php artisan tenants:migrate:status
```

```
TENANT      SCHEMA VERSION    PENDING    STATUS
acme        2026_07_20_01     0          allineato
globex      2026_07_20_01     0          allineato
initech     2026_07_15_03     2          disallineato
```

Cause tipiche del disallineamento e rimedi:

| Causa | Rimedio |
|---|---|
| Migration fallita per dati non conformi | correggere i dati, riallineare |
| Tenant sospeso durante il deploy | riallineare alla riattivazione |
| Provisioning interrotto | completare o rigenerare |
| Spazio esaurito | liberare, riallineare |

Il codice rilasciato deve **tollerare** il disallineamento temporaneo: è la ragione per cui le
migration di espansione precedono il deploy del codice.

---

## Dimensionamento

| Grandezza | Impatto | Come si dimensiona |
|---|---|---|
| Numero di tenant | connessioni, durata delle migration | `max_connections`, esecuzione a lotti |
| Dimensione del tenant più grande | prestazioni delle query | indici, cache, aggregati |
| Connessioni simultanee | limite del server | pool per worker, connessioni riciclate |
| Finestra di backup | durata totale | parallelizzazione, incrementali |

Formula indicativa per le connessioni:

```
max_connections ≈ (worker web × pool) + (worker code × pool) + margine del 30%
```

Il dimensionamento segue il **tenant più grande**, non la media: la media descrive un cliente che
non esiste.

---

## Esempi

### Esempio 1 — provisioning di un tenant

```bash
php artisan tenant:create acme --domain=acme.gestionale.it --plan=professional
```

```
✓ record creato nel landlord
✓ database `tenant_acme` creato (utf8mb4)
✓ utente MySQL `tenant_acme` con privilegi limitati
✓ 47 migration eseguite (12,3 s)
✓ seeder di sistema: 64 permessi, 3 ruoli
✓ utente amministratore creato, invito inviato
✓ disco `acme/` inizializzato
✓ dominio associato e verificato
✓ verifica di integrità superata
✓ stato: active
```

### Esempio 2 — migration che fallisce su un tenant

L'aggiunta di un vincolo di unicità su `suppliers.vat_number` fallisce su `globex`: due fornitori
condividono la stessa partita IVA, inseriti prima che il vincolo esistesse.

Procedura corretta: fermare i lotti successivi, estrarre i duplicati, decidere con il cliente,
correggere i dati, riallineare, riprendere.

Procedura **sbagliata**: rimuovere il vincolo dalla migration per sbloccare il deploy. Il problema
di qualità dei dati resta e si ripresenterà.

---

## Best practice

- Un utente MySQL per tenant, con privilegi limitati al proprio database.
- `DB::purge()` prima di ogni riconnessione.
- Migration veloci, senza trasformazione di dati.
- Seeder di sistema idempotenti, che assegnano i nuovi permessi al ruolo amministratore.
- Esecuzione a lotti con pausa in produzione.
- Verifica dell'allineamento dopo ogni deploy.
- Dimensionare sul tenant più grande.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Colonna `tenant_id` nelle tabelle tenant | Fraintendimento del modello | Il tenant è il database |
| `reconnect()` senza `purge()` | Un tenant lavora sul database di un altro | Sempre `purge()` prima |
| Migration che dipende dai dati | Fallisce su tenant vuoti | Indipendenza dai dati |
| Trasformazione dati in una migration | Deploy lentissimo su N database | Comando dedicato |
| Seeder non idempotente | Deploy che fallisce | `firstOrCreate` |
| Permessi nuovi non assegnati al ruolo | Funzionalità invisibile | `syncPermissions` nel seeder |
| Migration tutte insieme in produzione | Database saturato | `--chunk` con pausa |
| Credenziali MySQL condivise tra tenant | Nessuna seconda linea di difesa | Utente dedicato |

---

## Checklist

- [ ] Ogni tenant ha un database dedicato con utente MySQL proprio.
- [ ] Nessuna colonna `tenant_id` nelle tabelle tenant.
- [ ] Il bootstrapper esegue `purge()` prima di `reconnect()`.
- [ ] Le migration tenant sono reversibili, veloci e indipendenti dai dati.
- [ ] I seeder di sistema sono idempotenti e assegnano i nuovi permessi.
- [ ] Le migration in produzione girano a lotti.
- [ ] L'allineamento è verificato dopo ogni deploy.
- [ ] `max_connections` è dimensionato sul carico reale.

---

## Riferimenti

- [Multitenancy](03-multitenancy-overview.md) · [Database landlord](04-landlord-database.md)
- [Ciclo di vita del tenant](07-tenant-lifecycle.md)
- [Workflow del database](../docs/03-development/03-database-workflow.md)
- [Regole SQL](../rules/sql.md) · [Regole database](../rules/database.md)
- [Operazioni sui tenant](../docs/05-operations/06-tenant-operations.md)
