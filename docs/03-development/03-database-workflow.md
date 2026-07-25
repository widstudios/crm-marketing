# Workflow del database

> Come si modifica lo schema in un'applicazione con un database per tenant, senza fermare il
> servizio e senza lasciare tenant indietro.

---

## Indice

1. [Descrizione](#descrizione)
2. [Landlord e tenant](#landlord-e-tenant)
3. [Scrivere una migration](#scrivere-una-migration)
4. [Eseguire le migration](#eseguire-le-migration)
5. [Modifiche che richiedono trasformazione di dati](#modifiche-che-richiedono-trasformazione-di-dati)
6. [Seeder](#seeder)
7. [Factory](#factory)
8. [Indici](#indici)
9. [Tenant disallineati](#tenant-disallineati)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Con un database per tenant, una migration non si esegue una volta: si esegue N volte, su N
database che possono trovarsi in stati leggermente diversi. Questo cambia tre cose rispetto a
un'applicazione a database singolo:

1. Una migration lenta diventa N volte lenta.
2. Una migration non reversibile è un problema N volte più grave.
3. Un tenant che fallisce lascia il sistema in stato **misto**, con versioni diverse dello schema.

Tutto ciò che segue discende da questi tre fatti.

---

## Landlord e tenant

| | Landlord | Tenant |
|---|---|---|
| Numero di database | 1 | N |
| Cartella | `database/migrations/landlord/` | `database/migrations/tenant/` |
| Contenuto | tenant, domini, piani, utenti di piattaforma, metriche aggregate | tutte le entità di dominio |
| Comando | `php artisan migrate --database=landlord` | `php artisan tenants:migrate` |
| Quando si esegue | al deploy | al deploy e al provisioning di ogni nuovo tenant |

**Criterio di collocazione:** il dato descrive *il cliente* (landlord) o è *del cliente* (tenant)?

- Il piano tariffario del cliente → landlord.
- I fornitori del cliente → tenant.
- Gli utenti che accedono al tenant → tenant (con eventuale riferimento nel landlord per il login).

---

## Scrivere una migration

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->restrictOnDelete();
            $table->string('number', 50);
            $table->date('expiry_date');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['article_id', 'number']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
```

Regole vincolanti:

| Regola | Motivo |
|---|---|
| `down()` sempre implementato | senza, il rollback su N tenant è impossibile |
| Una migration, una modifica logica | rollback selettivo |
| Nessuna scrittura di dati dentro una migration di schema | i dati stanno nei seeder o in comandi dedicati |
| `decimal` per quantità e importi | `float` introduce errori di arrotondamento |
| Nomi di indice espliciti sulle tabelle grandi | evita nomi generati troppo lunghi |
| Nessun `tenant_id` nelle tabelle tenant | il tenant è il database |
| Nessuna query dipendente dai dati | la migration deve funzionare su un tenant vuoto |

---

## Eseguire le migration

```bash
# Sviluppo
php artisan migrate --database=landlord
php artisan tenants:migrate
php artisan tenants:migrate --tenant=acme       # un solo tenant

# Ricostruzione completa (solo locale)
php artisan migrate:fresh --database=landlord --seed
php artisan tenants:migrate --fresh --seed

# Stato
php artisan tenants:migrate:status
```

In produzione l'ordine è vincolato:

```
1. migration landlord      (aggiungono, non rimuovono)
2. deploy del codice       (compatibile con entrambi gli schemi)
3. migration tenant        (a lotti, con monitoraggio)
4. verifica dell'allineamento
```

Le migration tenant si eseguono **a lotti** quando i tenant sono molti, con un intervallo tra i
lotti: un `ALTER TABLE` simultaneo su cento database satura le risorse del server.

---

## Modifiche che richiedono trasformazione di dati

Il pattern in tre rilasci evita il fermo del servizio e mantiene il rollback possibile in ogni
momento.

### Rilascio 1 — espansione

```php
// Migration: aggiunge la nuova colonna, nullable
$table->string('vat_number', 20)->nullable()->after('name');
```

Il codice scrive su **entrambe** le colonne, legge dalla vecchia. Rollback: banale.

### Rilascio 2 — migrazione

```php
// Comando dedicato, non una migration
php artisan data:migrate-vat-numbers --chunk=1000
```

Il codice legge dalla nuova colonna con fallback sulla vecchia. Rollback: possibile.

### Rilascio 3 — contrazione

```php
// Migration: rimuove la vecchia colonna
$table->dropColumn('fiscal_code');
```

Solo dopo aver verificato che nessun tenant abbia più righe non migrate.

**Perché non tutto in una volta:** una migration che trasforma milioni di righe su cento database
può richiedere ore, durante le quali l'applicazione è ferma e il rollback è impossibile.

---

## Seeder

| Cartella | Contenuto | In produzione | Idempotente |
|---|---|---|---|
| `seeders/System/` | ruoli, permessi, stati, tipi, configurazioni obbligatorie | **sì** | **sì** |
| `seeders/Demo/` | dati di esempio | mai | non necessario |

I seeder di sistema devono essere **idempotenti**: vengono eseguiti ad ogni deploy e ad ogni
provisioning.

```php
final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('permissions.list') as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'tenant']);
        }
    }
}
```

`firstOrCreate`, mai `create`: un seeder che fallisce alla seconda esecuzione blocca il deploy.

---

## Factory

Le factory servono ai test e ai dati dimostrativi. Devono produrre entità **valide secondo il
dominio**, non solo secondo lo schema.

```php
final class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'number' => fake()->bothify('LOT-####'),
            'expiry_date' => fake()->dateTimeBetween('+1 month', '+2 years'),
            'quantity' => fake()->randomFloat(3, 1, 1000),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expiry_date' => fake()->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }
}
```

Gli stati nominati (`expired()`) rendono i test leggibili e documentano i casi limite del dominio.

---

## Indici

Un indice mancante si nota quando i dati crescono, cioè in produzione, cioè tardi.

| Colonna | Indicizzare |
|---|---|
| Chiavi esterne | sempre (non automatico in MySQL con InnoDB per tutti i casi) |
| Colonne nei `where` frequenti | sì |
| Colonne negli `order by` frequenti | sì, spesso in indice composto con il filtro |
| Colonne con pochi valori distinti (es. booleani) | solo in indice composto |
| Colonne di ricerca testuale | valutare FULLTEXT o un indice dedicato |

Ordine nell'indice composto: prima le colonne di uguaglianza, poi quelle di intervallo, infine
quelle di ordinamento.

```sql
-- Query: WHERE status = ? AND expiry_date < ? ORDER BY expiry_date
INDEX (status, expiry_date)
```

Ogni indice ha un costo in scrittura e in spazio: si aggiungono quelli che servono a query reali,
non quelli che «potrebbero servire».

---

## Tenant disallineati

Un tenant può restare indietro: fallimento della migration, tenant sospeso, provisioning
interrotto.

```bash
php artisan tenants:migrate:status        # elenco con la versione dello schema
php artisan tenants:migrate --tenant=acme # riallineamento singolo
```

Procedura quando una migration fallisce su un tenant:

1. **Fermare i lotti successivi**: non propagare un problema noto.
2. Diagnosticare sul tenant fallito (dati non conformi, vincolo violato, spazio esaurito).
3. Correggere i dati o la migration.
4. Riallineare il tenant.
5. Riprendere i lotti.

Il codice rilasciato deve tollerare la convivenza di due versioni di schema per il tempo del
riallineamento: è il motivo per cui le migration di espansione precedono il deploy del codice.

---

## Esempi

### Esempio 1 — rinominare una colonna senza fermo

```
v1.4.0  aggiungi `vat_number`, scrivi su `vat_number` e `piva`
v1.5.0  comando di migrazione dati, leggi da `vat_number`
v1.6.0  rimuovi `piva`
```

### Esempio 2 — migration che fallisce su un tenant

`ALTER TABLE` per aggiungere un vincolo di unicità su `suppliers.vat_number` fallisce sul tenant
`globex`: esistono due fornitori con la stessa partita IVA, inseriti prima che il vincolo
esistesse.

Procedura corretta: fermare i lotti, esportare i duplicati, decidere con il cliente quale
mantenere, correggere i dati, riallineare.

Procedura **sbagliata**: rimuovere il vincolo dalla migration per «sbloccare il deploy». Il
problema di dominio resta e si ripresenta.

---

## Best practice

- Scrivere `down()` sempre, e provarlo almeno una volta in locale.
- Una migration, una modifica logica.
- Trasformazioni di dati in comandi dedicati, mai nelle migration.
- Migration tenant a lotti in produzione, con monitoraggio.
- Seeder di sistema idempotenti.
- Factory con stati nominati per i casi limite.
- Indici derivati da query reali, non da ipotesi.
- Verificare l'allineamento dei tenant dopo ogni deploy.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Migration senza `down()` | Rollback impossibile su N tenant | Sempre reversibile |
| Trasformazione dati dentro una migration | Deploy lentissimo, rollback impossibile | Comando dedicato |
| Seeder non idempotente | Deploy che fallisce alla seconda esecuzione | `firstOrCreate` |
| Dati di prova in `System/` | Dati finti in produzione | Separazione delle cartelle |
| Colonna `tenant_id` in tabella tenant | Fraintendimento architetturale | Il tenant è il database |
| `float` per importi | Errori di arrotondamento | `decimal` |
| Migration tenant tutte insieme in produzione | Saturazione delle risorse | Esecuzione a lotti |
| Rimuovere un vincolo per sbloccare il deploy | Il problema di dominio resta | Correggere i dati |

---

## Checklist

- [ ] La migration è nella cartella corretta (landlord o tenant).
- [ ] `down()` implementato e provato.
- [ ] Nessuna scrittura di dati nella migration di schema.
- [ ] Indici sulle colonne dei filtri e degli ordinamenti dichiarati.
- [ ] Tipi `decimal` per quantità e importi.
- [ ] Seeder di sistema idempotente.
- [ ] Factory con stati per i casi limite.
- [ ] Migration verificata su MySQL, non solo su SQLite.
- [ ] Trasformazioni di dati pianificate in tre rilasci.
- [ ] Allineamento dei tenant verificato dopo il deploy.

---

## Riferimenti

- [Database landlord](../../architecture/04-landlord-database.md) · [Database tenant](../../architecture/05-tenant-databases.md)
- [Regole SQL](../../rules/sql.md) · [Regole database](../../rules/database.md)
- [Operazioni sui tenant](../05-operations/06-tenant-operations.md)
- [Checklist database](../../checklists/database-checklist.md)
