# Regole — Migration, seeder, factory

> Come si evolve lo schema quando esistono N database, e come si popolano i dati obbligatori.

---

## Indice

1. [Descrizione](#descrizione)
2. [Migration](#migration)
3. [Trasformazioni di dati](#trasformazioni-di-dati)
4. [Seeder](#seeder)
5. [Factory](#factory)
6. [Transazioni](#transazioni)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Con un database per tenant, una migration non si esegue una volta: si esegue N volte, su database
che possono trovarsi in stati leggermente diversi. Ogni regola di questo documento discende da
questo fatto.

---

## Migration

**R1.** Le migration tenant stanno in `database/migrations/tenant/`, quelle di piattaforma in
`landlord/`.
*Verifica:* script di verifica. *Livello: vincolante.*

**R2.** Ogni migration implementa `down()`.
*Motivo:* senza, il rollback su N tenant è impossibile. *Verifica:* script di verifica.
*Livello: vincolante.*

**R3.** Una migration, una modifica logica.
*Motivo:* permette il rollback selettivo. *Verifica:* revisione.

**R4.** Nessuna scrittura o trasformazione di **dati** in una migration di schema.
*Motivo:* rende il deploy lentissimo su N database e il rollback impossibile.
*Verifica:* revisione. *Livello: vincolante.*

**R5.** La migration deve funzionare su un database **vuoto** (provisioning) e su uno pieno
(aggiornamento).
*Verifica:* provisioning di un tenant in CI.

**R6.** La migration non dipende dai dati esistenti: un tenant può non avere righe.
*Verifica:* provisioning in CI.

**R7.** Nessun riferimento a model applicativi dentro una migration.
*Motivo:* il model può cambiare o sparire; la migration resta nella storia per sempre.
*Verifica:* test di architettura.

**R8.** Le migration di **espansione** (aggiunta di colonne nullable, nuove tabelle, nuovi indici)
precedono il deploy del codice; quelle di **contrazione** (rimozione, rinomina) lo seguono di
almeno un rilascio.
*Motivo:* permette il rilascio senza interruzione. *Verifica:* revisione del piano di rilascio.

**R9.** Ogni migration è verificata su **MySQL**, non solo su SQLite.
*Verifica:* pipeline.

**R10.** In produzione le migration tenant girano a lotti (`--chunk`).
*Verifica:* script di deploy.

---

## Trasformazioni di dati

**R11.** Le trasformazioni di dati si eseguono con **comandi Artisan dedicati**, non con migration.

```bash
php artisan data:migrate-vat-numbers --chunk=1000
```

*Verifica:* revisione.

**R12.** Le modifiche che richiedono trasformazione seguono il pattern in **tre rilasci**.

```
v1  aggiungi la nuova colonna, scrivi su entrambe, leggi dalla vecchia
v2  comando di migrazione dei dati storici, leggi dalla nuova
v3  rimuovi la vecchia colonna
```

*Motivo:* nessuna finestra di indisponibilità, rollback possibile ad ogni passo.
*Verifica:* revisione. *Livello: vincolante* per le tabelle con dati in produzione.

**R13.** I comandi di trasformazione sono **riprendibili** e idempotenti.
*Verifica:* revisione, test.

---

## Seeder

**R14.** I seeder si dividono in `seeders/System/` (obbligatori) e `seeders/Demo/` (dati di prova).
*Verifica:* struttura del progetto. *Livello: vincolante.*

**R15.** I seeder di sistema sono **idempotenti**: girano ad ogni deploy e ad ogni provisioning.

```php
Permission::firstOrCreate(['name' => $name, 'guard_name' => 'tenant']);
```

*Motivo:* un seeder che fallisce alla seconda esecuzione blocca il deploy.
*Verifica:* esecuzione doppia in CI. *Livello: vincolante.*

**R16.** Il seeder dei permessi assegna i nuovi permessi al ruolo amministratore.

```php
Role::firstOrCreate(['name' => 'tenant_admin', 'guard_name' => 'tenant'])
    ->syncPermissions(Permission::all());
```

*Motivo:* senza, ogni nuova funzionalità è invisibile a tutti dopo il deploy.
*Verifica:* test di feature dopo il seeding.

**R17.** I seeder di prova non girano mai in produzione.
*Verifica:* verifica dell'ambiente nel seeder.

**R18.** Nessun dato realistico di persone reali nei seeder di prova.
*Motivo:* i dati di prova finiscono in ambienti condivisi. *Verifica:* revisione.

---

## Factory

**R19.** Ogni model ha una factory.
*Verifica:* script di verifica.

**R20.** La factory produce entità **valide secondo il dominio**, non solo secondo lo schema.
*Motivo:* una factory che genera dati che il dominio rifiuterebbe rende i test inaffidabili.
*Verifica:* revisione.

**R21.** I casi limite si esprimono con **stati nominati**.

```php
public function expired(): static
{
    return $this->state(fn (): array => [
        'expiry_date' => fake()->dateTimeBetween('-1 year', '-1 day'),
        'status' => BatchStatus::Expired,
    ]);
}
```

*Motivo:* i test diventano leggibili e i casi limite documentati.
*Verifica:* revisione.

**R22.** La factory non crea relazioni non necessarie.
*Motivo:* alberi di relazioni rendono i test lenti. *Verifica:* revisione.

---

## Transazioni

**R23.** Ogni scrittura che interessa più righe o tabelle è in transazione.
*Verifica:* revisione.

**R24.** Nessuna chiamata esterna dentro una transazione.
*Verifica:* revisione.

**R25.** Lock pessimistico (`lockForUpdate()`) sulle risorse contese.
*Motivo:* senza, due operazioni simultanee possono entrambe superare una verifica di disponibilità.
*Verifica:* test di concorrenza.

**R26.** Nessuna transazione annidata gestita a mano: si usa il supporto del framework.
*Verifica:* revisione.

---

## Esempi

### Esempio 1 — migration conforme

```php
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
            $table->string('status', 20)->default('available');
            $table->timestamps();

            $table->unique(['article_id', 'number']);
            $table->index(['status', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
```

### Esempio 2 — violazioni

```php
public function up(): void
{
    Schema::table('suppliers', function (Blueprint $table): void {
        $table->string('vat_number', 20);          // non nullable su tabella con dati esistenti
    });

    // ✗ R4, R7: trasformazione di dati e uso di un model nella migration
    Supplier::all()->each(function (Supplier $s): void {
        $s->update(['vat_number' => 'IT' . $s->fiscal_code]);
    });
}

// ✗ R2: nessun down()
```

---

## Best practice

- Provare `down()` almeno una volta in locale, non scriverlo per formalità.
- Usare il pattern in tre rilasci per ogni modifica su tabelle con dati in produzione.
- Verificare il provisioning di un tenant nuovo in CI: intercetta le migration che dipendono dai
  dati.
- Stati nominati nelle factory per ogni caso limite del dominio.
- Misurare la durata delle migration tenant prima del deploy in produzione.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Migration senza `down()` | Rollback impossibile su N tenant | Sempre reversibile |
| Trasformazione dati in migration | Deploy lentissimo, rollback impossibile | Comando dedicato |
| Model usato in migration | Si rompe quando il model cambia | Query builder |
| Migration che dipende dai dati | Fallisce sui tenant vuoti | Indipendenza dai dati |
| Colonna `NOT NULL` aggiunta a tabella con dati | Migration fallita | Nullable, poi popolamento, poi vincolo |
| Seeder non idempotente | Deploy fallito alla seconda esecuzione | `firstOrCreate` |
| Permessi non assegnati al ruolo | Funzionalità invisibile | `syncPermissions` |
| Factory che genera dati non validi | Test che verificano l'impossibile | Factory conformi al dominio |
| Nessun lock su risorsa contesa | Giacenze negative | `lockForUpdate()` |

---

## Checklist

- [ ] Migration nella cartella corretta, con `down()` provato.
- [ ] Una modifica logica per migration.
- [ ] Nessuna trasformazione di dati, nessun model usato.
- [ ] Funziona su database vuoto e pieno.
- [ ] Verificata su MySQL.
- [ ] Trasformazioni di dati in comandi riprendibili, con pattern in tre rilasci.
- [ ] Seeder divisi tra System e Demo, quelli di sistema idempotenti.
- [ ] Nuovi permessi assegnati al ruolo amministratore.
- [ ] Ogni model ha una factory con stati per i casi limite.
- [ ] Transazioni su scritture multiple, lock dove c'è contesa.

---

## Riferimenti

- [Regole SQL](sql.md) · [Testing](testing.md)
- [Database tenant](../architecture/05-tenant-databases.md)
- [Workflow del database](../docs/03-development/03-database-workflow.md)
- [Template migration e seeder](../templates/backend/README.md)
