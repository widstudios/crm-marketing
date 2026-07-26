# Regole SQL

> Schema, tipi, indici e query in un contesto con un database per tenant.

---

## Indice

1. [Descrizione](#descrizione)
2. [Schema](#schema)
3. [Tipi di colonna](#tipi-di-colonna)
4. [Vincoli](#vincoli)
5. [Indici](#indici)
6. [Query](#query)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Lo schema è la parte più costosa da correggere: il codice si riscrive, i dati vanno migrati. Queste
regole prevengono gli errori che, una volta in produzione con dati reali, diventano progetti di
migrazione.

---

## Schema

**R1.** Nomi secondo [naming.md](naming.md): tabelle al plurale in `snake_case`, colonne in
`snake_case`.
*Verifica:* script di verifica.

**R2.** Nessuna colonna `tenant_id` nelle tabelle tenant.
*Motivo:* il tenant è il database. *Verifica:* script di verifica. *Livello: assoluto.*

**R3.** Charset `utf8mb4`, collation `utf8mb4_unicode_ci`, engine InnoDB.
*Motivo:* supporto completo di Unicode, comprese le emoji e i caratteri di quattro byte.
*Verifica:* verifica dello schema in CI.

**R4.** Ogni tabella ha `id` come chiave primaria auto-incrementale, salvo tabelle pivot pure.
*Verifica:* revisione.

**R5.** Ogni tabella ha `created_at` e `updated_at`, salvo tabelle immutabili di log.
*Verifica:* revisione.

**R6.** I fusi orari: tutto in **UTC** nel database, conversione in presentazione.
*Motivo:* un cambio di fuso o l'ora legale non deve alterare i dati storici.
*Verifica:* configurazione, revisione.

---

## Tipi di colonna

| Dato | Tipo | Mai |
|---|---|---|
| Quantità, importi | `decimal(p, s)` | `float`, `double` |
| Percentuali | `decimal(5, 2)` | `float` |
| Identificatori | `bigint unsigned` | `int` su tabelle con crescita |
| Stati, enum | `varchar(20-30)` con cast a enum PHP | `enum` MySQL |
| Testo breve | `varchar(n)` con `n` giustificato | `text` |
| Testo lungo | `text` / `longtext` | `varchar(65535)` |
| Booleani | `boolean` | `tinyint` manuale, `varchar('Y'/'N')` |
| Date senza ora | `date` | `datetime` |
| Momenti | `timestamp` o `datetime` | `varchar` |
| JSON | `json` | `text` con serializzazione manuale |
| Denaro | `decimal(13, 2)` + colonna valuta | tipo unico senza valuta |

**R7.** Mai `float` o `double` per quantità e importi.
*Motivo:* errori di arrotondamento non recuperabili nei totali.
*Verifica:* analisi dello schema. *Livello: vincolante.*

**R8.** Mai il tipo `enum` di MySQL: si usa `varchar` con cast a enum PHP.
*Motivo:* modificare un `enum` MySQL richiede un `ALTER TABLE` su tutti i tenant.
*Verifica:* analisi dello schema.

**R9.** Le colonne `varchar` dichiarano una lunghezza giustificata dal dominio, non `255` per
abitudine.
*Verifica:* revisione.

---

## Vincoli

**R10.** Ogni chiave esterna ha un vincolo di integrità referenziale, con comportamento esplicito.

| Comportamento | Quando |
|---|---|
| `restrictOnDelete()` | default: impedisce la cancellazione di un riferimento in uso |
| `cascadeOnDelete()` | solo per entità figlie che non hanno senso senza il padre |
| `nullOnDelete()` | riferimenti facoltativi |

*Motivo:* senza vincoli, i dati orfani si accumulano silenziosamente.
*Verifica:* analisi dello schema.

**R11.** I vincoli di unicità sono espressi nel database, non solo nella validazione applicativa.
*Motivo:* la validazione applicativa ha una condizione di corsa tra verifica e inserimento.
*Verifica:* analisi dello schema.

**R12.** Le colonne obbligatorie sono `NOT NULL`. `nullable` significa che l'assenza è un caso
previsto del dominio.
*Verifica:* revisione.

**R13.** I valori predefiniti sono dichiarati nel database quando esiste un default di dominio.
*Verifica:* revisione.

---

## Indici

**R14.** Ogni chiave esterna è indicizzata.
*Verifica:* analisi dello schema.

**R15.** Ogni colonna usata in un filtro o ordinamento dichiarato nel brief è indicizzata.
*Verifica:* revisione con i requisiti alla mano.

**R16.** Nell'indice composto l'ordine è: colonne di **uguaglianza**, poi di **intervallo**, poi di
**ordinamento**.

```sql
-- Query: WHERE status = ? AND expiry_date < ? ORDER BY expiry_date
INDEX (status, expiry_date)
```

*Motivo:* un ordine sbagliato rende l'indice inutile per quella query.
*Verifica:* `EXPLAIN` sulle query principali.

**R17.** Nessun indice «preventivo»: ogni indice risponde a una query reale.
*Motivo:* ogni indice rallenta le scritture e occupa spazio, moltiplicato per il numero di tenant.
*Verifica:* revisione.

**R18.** Le colonne con pochi valori distinti (booleani, stati con tre casi) si indicizzano solo
dentro un indice composto.
*Verifica:* revisione.

**R19.** Gli indici sulle tabelle grandi hanno un nome esplicito.
*Motivo:* i nomi generati possono superare il limite di lunghezza.
*Verifica:* revisione.

---

## Query

**R20.** Nessuna concatenazione di stringhe nelle query: solo parametri legati.
*Verifica:* ricerca in CI. *Livello: assoluto.*

**R21.** Nessun `SELECT *` nelle query di lettura per la presentazione.
*Motivo:* trasferimento inutile, e una colonna aggiunta domani cambia la risposta.
*Verifica:* revisione.

**R22.** Nessuna query dentro un ciclo: eager loading o query aggregata.
*Verifica:* test sul numero di query.

**R23.** `EXPLAIN` verificato sulle query che interessano tabelle oltre le 100.000 righe.
*Verifica:* revisione in fase di ottimizzazione.

**R24.** Nessuna query cross-tenant, in nessuna circostanza.
*Verifica:* test di architettura, revisione. *Livello: assoluto.*

**R25.** Le query grezze sono ammesse per aggregazioni complesse, con parametri legati e un
commento che spiega perché non si usa il query builder.
*Verifica:* revisione.

---

## Esempi

### Esempio 1 — tabella conforme

```php
Schema::create('stock_movements', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('batch_id')->constrained()->restrictOnDelete();
    $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
    $table->string('type', 20);                       // cast a enum PHP
    $table->decimal('quantity', 12, 3);               // mai float
    $table->string('reason', 255)->nullable();
    $table->timestamp('occurred_at');
    $table->timestamps();

    $table->index(['batch_id', 'occurred_at'], 'movements_batch_occurred_index');
    $table->index(['type', 'occurred_at'], 'movements_type_occurred_index');
});
```

### Esempio 2 — violazioni

```php
Schema::create('movimenti', function (Blueprint $table): void {   // ✗ R1: italiano
    $table->id();
    $table->unsignedBigInteger('tenant_id');          // ✗ R2
    $table->unsignedBigInteger('batch_id');           // ✗ R10: nessun vincolo, ✗ R14: nessun indice
    $table->enum('type', ['inbound', 'outbound']);    // ✗ R8
    $table->float('quantity');                        // ✗ R7
    $table->string('reason');                         // ✗ R9: lunghezza non giustificata
});
```

---

## Best practice

- Progettare lo schema dal modello di dominio, non dalle schermate.
- Verificare gli indici con `EXPLAIN` su volumi realistici, non su dieci righe.
- Preferire `restrictOnDelete()` come default: la cancellazione a cascata è raramente ciò che si
  vuole.
- Dichiarare i vincoli nel database anche quando la validazione applicativa esiste.
- Verificare ogni migration su MySQL, non solo su SQLite.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `float` per importi | Errori di arrotondamento nei totali | `decimal` |
| Colonna `tenant_id` | Fraintendimento del modello | Il tenant è il database |
| `enum` MySQL | `ALTER TABLE` su N tenant per aggiungere un valore | `varchar` + enum PHP |
| Chiave esterna senza vincolo | Dati orfani accumulati | Vincolo esplicito |
| Chiave esterna senza indice | Join e cancellazioni lente | Indice |
| Unicità solo applicativa | Condizione di corsa, duplicati | Vincolo nel database |
| Indici preventivi | Scritture rallentate su N tenant | Solo per query reali |
| Ordine sbagliato nell'indice composto | Indice inutile | Uguaglianza, intervallo, ordinamento |
| `SELECT *` negli elenchi | Trasferimento inutile | Colonne esplicite |
| Query concatenate | Injection | Parametri legati |

---

## Checklist

- [ ] Nomi conformi, nessuna colonna `tenant_id`.
- [ ] `utf8mb4`, InnoDB, UTC.
- [ ] `decimal` per quantità e importi.
- [ ] Nessun `enum` MySQL.
- [ ] Ogni chiave esterna ha vincolo e indice.
- [ ] Unicità dichiarata nel database.
- [ ] Indici su tutte le colonne di filtro e ordinamento dichiarate.
- [ ] Ordine corretto negli indici compositi.
- [ ] Nessuna query concatenata, nessun `SELECT *` per la presentazione.
- [ ] `EXPLAIN` verificato sulle tabelle grandi.
- [ ] Migration verificata su MySQL.

---

## Riferimenti

- [Regole database](database.md) · [Naming](naming.md) · [Performance](performance.md)
- [Database tenant](../architecture/05-tenant-databases.md)
- [Workflow del database](../docs/03-development/03-database-workflow.md)
