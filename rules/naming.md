# Regole di naming

> Come si chiamano classi, metodi, tabelle, colonne, permessi, rotte, file. Un nome sbagliato costa
> più di un'implementazione sbagliata, perché resta.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole generali](#regole-generali)
3. [Classi](#classi)
4. [Metodi e variabili](#metodi-e-variabili)
5. [Database](#database)
6. [Enum e stati](#enum-e-stati)
7. [Permessi e ruoli](#permessi-e-ruoli)
8. [Rotte](#rotte)
9. [Chiavi di traduzione](#chiavi-di-traduzione)
10. [File e cartelle](#file-e-cartelle)
11. [Parole vietate](#parole-vietate)
12. [Esempi](#esempi)
13. [Best practice](#best-practice)
14. [Errori comuni](#errori-comuni)
15. [Checklist](#checklist)
16. [Riferimenti](#riferimenti)

---

## Descrizione

Un nome è un'interfaccia: chi lo legge deduce cosa fa la cosa nominata, e agisce di conseguenza. Un
nome fuorviante produce codice sbagliato **a valle**, scritto da persone che si sono fidate del nome
invece di leggere l'implementazione.

I nomi sono anche l'unico aspetto del codice che gli strumenti automatici non possono correggere:
Pint sistema la formattazione, PHPStan i tipi, nessuno sistema un nome sbagliato.

---

## Regole generali

**R1.** Tutti gli identificatori di codice sono in **inglese**.
*Motivo:* l'ecosistema PHP/Laravel è in inglese; nomi italiani producono frasi ibride illeggibili.
*Verifica:* revisione. Vedi [politica linguistica](../docs/02-conventions/03-language-policy.md).

**R2.** Un concetto, **un solo** nome, in tutto il repository e in tutti i progetti.
*Motivo:* i sinonimi rendono inefficaci le ricerche e introducono ambiguità.
*Verifica:* glossario, revisione.

**R3.** I nomi non si abbreviano, salvo abbreviazioni universalmente riconosciute (`id`, `url`,
`vat`, `pdf`).
*Motivo:* `$sup` non è più veloce da scrivere di `$supplier` con il completamento automatico, ed è
molto più lento da capire. *Verifica:* revisione.

**R4.** Il nome descrive **cosa è** o **cosa fa**, non come è implementato.
*Esempio:* `SupplierRepository`, non `SupplierMysqlDataAccess`.
*Verifica:* revisione.

---

## Classi

| Tipo | Convenzione | Esempio |
|---|---|---|
| Entità / Model | `PascalCase`, singolare | `Supplier`, `StockMovement` |
| Action | `<Verbo><Oggetto>Action` | `RegisterMovementAction`, `ArchiveSupplierAction` |
| Query | `<Oggetto><Descrizione>Query` | `ExpiringBatchesQuery` |
| DTO | `<Oggetto>Data` | `MovementData`, `SupplierData` |
| Service | `<Oggetto><Funzione>Service` | `StockTransferService` |
| Repository | `<Entità>Repository` (contratto), `Eloquent<Entità>Repository` (impl.) | `BatchRepository`, `EloquentBatchRepository` |
| Value object | `PascalCase`, sostantivo | `VatNumber`, `Quantity`, `ExpiryDate` |
| Enum | `PascalCase`, singolare | `MovementType`, `SupplierStatus` |
| Evento | `<Oggetto><ParticipioPassato>` | `MovementRegistered`, `SupplierArchived` |
| Listener | `<Verbo><Oggetto>On<Evento>` | `RecalculateStockOnMovement` |
| Job | `<Verbo><Oggetto>Job` | `RecalculateStockJob` |
| Policy | `<Entità>Policy` | `SupplierPolicy` |
| Controller | `<Entità>Controller` | `SupplierController` |
| Form Request | `<Verbo><Entità>Request` | `StoreSupplierRequest` |
| API Resource | `<Entità>Resource` | `SupplierResource` |
| Eccezione | descrive il problema | `InsufficientStock`, `BatchExpired` |
| Middleware | `<Verbo><Oggetto>` | `ResolveTenant`, `EnsureTenantIsActive` |
| Notification | `<Oggetto><Evento>Notification` | `BatchExpiringNotification` |
| Comando | `<Verbo><Oggetto>Command` | `RecalculateStockCommand` |
| Test | `<ClasseTestata>Test` | `RegisterMovementActionTest` |

**R5.** Le Action hanno un nome **imperativo** che descrive l'operazione.
*Motivo:* l'elenco delle Action è l'inventario delle operazioni: deve leggersi come tale.
*Verifica:* test di architettura sul suffisso.

**R6.** Gli eventi hanno un nome al **passato**.
*Motivo:* un evento descrive un fatto accaduto; un imperativo descrive un comando.
*Verifica:* revisione.

---

## Metodi e variabili

| Elemento | Convenzione | Esempio |
|---|---|---|
| Metodo | `camelCase`, verbo | `registerMovement()`, `isExpired()` |
| Metodo booleano | `is`, `has`, `can`, `should` | `isExpired()`, `canBePicked()` |
| Metodo di Action | sempre `execute()` | `execute()` |
| Costruttore nominato | `from<Origine>` | `fromRequest()`, `fromArray()` |
| Variabile | `camelCase`, sostantivo | `$expiryDate`, `$availableQuantity` |
| Collezione | plurale | `$batches`, `$movements` |
| Booleano | prefisso `is`/`has` | `$isExpired`, `$hasMovements` |
| Costante | `SCREAMING_SNAKE_CASE` | `MAX_QUANTITY` |

**R7.** I metodi booleani devono iniziare con `is`, `has`, `can` o `should`.
*Motivo:* al punto di chiamata si legge come una condizione. *Verifica:* revisione.

**R8.** Nessun nome di metodo generico: `handle`, `process`, `execute` (fuori dalle Action), `run`,
`doWork`, `manage`.
*Motivo:* non dicono cosa fa il metodo. *Verifica:* revisione.

**R9.** Nessuna variabile a lettera singola, salvo indici di ciclo brevi (`$i`) e closure di una
riga.
*Verifica:* revisione.

---

## Database

| Elemento | Convenzione | Esempio |
|---|---|---|
| Tabella | `snake_case`, **plurale** | `suppliers`, `stock_movements` |
| Tabella pivot | nomi singolari in ordine alfabetico | `article_warehouse` |
| Colonna | `snake_case` | `vat_number`, `expiry_date` |
| Chiave primaria | `id` | `id` |
| Chiave esterna | `<tabella_singolare>_id` | `article_id`, `operator_id` |
| Booleano | `is_`, `has_` | `is_active`, `has_attachments` |
| Data | `_at` per momenti, `_date` per date | `created_at`, `expiry_date` |
| Contatore | `_count` | `movements_count` |
| Importo | `_amount` | `total_amount` |
| Quantità | `_quantity` | `available_quantity` |
| Indice | `<tabella>_<colonne>_index` | `batches_status_expiry_date_index` |
| Vincolo di unicità | `<tabella>_<colonne>_unique` | `suppliers_vat_number_unique` |

**R10.** Nessuna colonna `tenant_id` nelle tabelle tenant.
*Motivo:* il tenant è il database. *Verifica:* script di verifica. *Livello: assoluto.*

**R11.** Le colonne booleane sono affermative, non negative: `is_active`, non `is_not_active`.
*Motivo:* le doppie negazioni nelle condizioni sono fonte di errori.
*Verifica:* revisione.

**R12.** I nomi delle colonne non ripetono il nome della tabella: `suppliers.name`, non
`suppliers.supplier_name`.
*Verifica:* revisione.

---

## Enum e stati

**R13.** Il nome dell'enum è al **singolare**, i casi sono in `PascalCase`, i valori in
`snake_case`.

```php
enum MovementType: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Adjustment = 'adjustment';
}
```

*Verifica:* revisione.

**R14.** Gli stati descrivono una condizione, non un'azione: `Archived`, non `Archive`.
*Verifica:* revisione.

---

## Permessi e ruoli

| Elemento | Convenzione | Esempio |
|---|---|---|
| Permesso | `<risorsa>.<azione>`, singolare, minuscolo | `supplier.archive`, `movement.create` |
| Ruolo | `snake_case`, descrive una funzione | `warehouse_manager`, `tenant_admin` |
| Abilità di token | come i permessi | `movement.create` |

**R15.** I permessi usano il nome della risorsa al **singolare**.
*Motivo:* coerenza: `supplier.view` vale per uno e per molti. *Verifica:* script di verifica.

**R16.** Le azioni standard sono: `view`, `create`, `update`, `delete`. Le azioni specifiche del
dominio usano il verbo del dominio: `archive`, `suspend`, `approve`.
*Verifica:* revisione.

---

## Rotte

| Elemento | Convenzione | Esempio |
|---|---|---|
| Percorso | `kebab-case`, plurale | `/api/v1/stock-movements` |
| Nome di rotta | `<contesto>.<risorsa>.<azione>` | `api.v1.suppliers.store` |
| Parametro | `camelCase` | `{supplier}`, `{stockMovement}` |

**R17.** Ogni rotta ha un nome; gli URL non si scrivono a mano.
*Verifica:* revisione.

**R18.** I percorsi usano sostantivi al plurale; il verbo è il metodo HTTP.
*Esempio:* `POST /suppliers`, non `POST /create-supplier`.
*Verifica:* revisione.

---

## Chiavi di traduzione

| Elemento | Convenzione | Esempio |
|---|---|---|
| Struttura | `<modulo>::<ambito>.<chiave>` | `inventory::batch.expiry_date` |
| Chiave | `snake_case`, in inglese | `expiry_date`, `insufficient_stock` |
| Ambito | riflette il dominio, non la pagina | `batch`, non `batch_edit_page` |

**R19.** Le chiavi sono in inglese e gerarchiche, mai in italiano.
*Motivo:* una chiave italiana diventa assurda quando si aggiunge la traduzione inglese.
*Verifica:* revisione.

**R20.** L'ambito della chiave riflette il **dominio**, non la posizione nell'interfaccia.
*Motivo:* se la pagina cambia, la chiave resta valida. *Verifica:* revisione.

---

## File e cartelle

| Elemento | Convenzione | Esempio |
|---|---|---|
| File di classe | `PascalCase.php` | `RegisterMovementAction.php` |
| File di migration | `AAAA_MM_GG_NNNNNN_verbo_oggetto.php` | `2026_07_25_000001_create_batches_table.php` |
| Vista Blade | `kebab-case.blade.php` | `movement-form.blade.php` |
| Componente Blade | `kebab-case.blade.php` | `status-badge.blade.php` |
| File di traduzione | `snake_case.php` | `stock_movements.php` |
| Documento Markdown | `kebab-case.md`, prefisso numerico se l'ordine conta | `03-multitenancy-overview.md` |
| Cartella | `PascalCase` nel codice, `kebab-case` altrove | `Application/`, `docs/` |
| Modulo | `kebab-case` | `stock-management` |

---

## Parole vietate

| Vietato | Perché | Usare invece |
|---|---|---|
| `Manager` | non dice cosa fa | `Service`, `Action`, `Repository` |
| `Helper`, `Util`, `Utility` | responsabilità indefinita | nome che dichiara il compito |
| `Handler` (fuori da listener e job) | generico | verbo specifico |
| `Data` (fuori dai DTO) | generico | nome del concetto |
| `Info`, `Object`, `Item`, `Element` | non significano nulla | nome del concetto |
| `Base` (fuori dalle classi astratte) | non dice cosa contiene | nome della responsabilità |
| `Temp`, `Tmp`, `Old`, `New`, `V2` | destinati a restare | nome definitivo |
| `process`, `handle`, `doWork`, `run` come nomi di metodo | non dicono nulla | verbo del dominio |
| `flag` | non dice cosa indica | `is_active`, `has_attachments` |
| `misc`, `various`, `other` | discarica | categoria precisa |

---

## Esempi

### Esempio 1 — insieme coerente

```php
// Dominio
final class Batch extends Model { /* … */ }
enum BatchStatus: string { case Available = 'available'; }
final readonly class ExpiryDate { /* … */ }
final readonly class BatchExpired extends DomainException { /* … */ }
final readonly class MovementRegistered { /* … */ }

// Applicazione
final readonly class RegisterMovementAction { public function execute(MovementData $data): StockMovement }
final readonly class ExpiringBatchesQuery { public function execute(int $days): Collection }
final readonly class MovementData { /* … */ }

// Infrastruttura
final readonly class EloquentBatchRepository implements BatchRepository { /* … */ }

// Presentazione
final class StockMovementController extends Controller { /* … */ }
final class StoreStockMovementRequest extends FormRequest { /* … */ }
final class StockMovementResource extends JsonResource { /* … */ }
```

Tabelle: `batches`, `stock_movements`.
Permessi: `batch.view`, `movement.create`.
Rotte: `api.v1.stock-movements.store`.
Traduzioni: `inventory::movement.registered`.

### Esempio 2 — nomi da correggere

| Errato | Corretto | Regola |
|---|---|---|
| `MovementManager` | `RegisterMovementAction` | parola vietata |
| `getData()` | `availableQuantity()` | R8 |
| `$supp` | `$supplier` | R3 |
| `movimenti` (tabella) | `stock_movements` | R1 |
| `suppliers.supplier_name` | `suppliers.name` | R12 |
| `is_not_deleted` | `is_active` | R11 |
| `POST /create-supplier` | `POST /suppliers` | R18 |
| `fornitori.stato` (chiave) | `suppliers::status.label` | R19 |
| `SupplierHelper` | `SupplierRepository` o Action | parola vietata |
| `UpdateStock` (evento) | `StockUpdated` | R6 |

---

## Best practice

- Prendere il nome dal **glossario** prima di inventarlo.
- Chiedersi: chi legge solo questo nome capisce cosa fa la cosa?
- Preferire il termine del dominio a quello tecnico quando entrambi sono corretti.
- Rinominare appena si nota un nome fuorviante: il costo cresce con il tempo.
- Se non si trova un nome, spesso il concetto non è chiaro: chiarirlo prima.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Identificatori in italiano | Codice ibrido illeggibile | Inglese |
| Sinonimi dello stesso concetto | Ricerche inefficaci, ambiguità | Un concetto, un nome |
| Abbreviazioni | Codice più lento da leggere | Nomi completi |
| `Manager`, `Helper`, `Utils` | Responsabilità indefinita, classi che crescono | Nome che dichiara il compito |
| Nome che descrive l'implementazione | Cambia quando cambia la tecnologia | Nome del concetto |
| Evento all'imperativo | Confusione tra evento e comando | Participio passato |
| Chiave di traduzione italiana | Assurda con la seconda lingua | Chiave inglese |
| `V2`, `New`, `Temp` nei nomi | Restano per anni | Nome definitivo |

---

## Checklist

- [ ] Tutti gli identificatori sono in inglese.
- [ ] I nomi provengono dal glossario, senza sinonimi introdotti.
- [ ] Nessuna abbreviazione non universale.
- [ ] Le Action hanno nome imperativo, gli eventi al passato.
- [ ] I metodi booleani iniziano con `is`, `has`, `can`, `should`.
- [ ] Tabelle al plurale, colonne senza ripetizione del nome della tabella.
- [ ] Nessuna colonna `tenant_id`.
- [ ] Permessi nel formato `<risorsa>.<azione>`.
- [ ] Rotte con nome, percorsi con sostantivi al plurale.
- [ ] Chiavi di traduzione in inglese, gerarchiche, per dominio.
- [ ] Nessuna parola dell'elenco dei vietati.

---

## Riferimenti

- [Glossario](../docs/00-introduction/06-glossary.md)
- [Politica linguistica](../docs/02-conventions/03-language-policy.md)
- [Regole PHP](php.md) · [Laravel](laravel.md) · [SQL](sql.md) · [i18n](i18n.md)
