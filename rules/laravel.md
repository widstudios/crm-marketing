# Regole Laravel

> Come si usa il framework: cosa è ammesso, cosa è vietato, e dove va ciò che il framework
> permetterebbe di mettere ovunque.

---

## Indice

1. [Descrizione](#descrizione)
2. [Struttura](#struttura)
3. [Controller](#controller)
4. [Model](#model)
5. [Query](#query)
6. [Facade e helper](#facade-e-helper)
7. [Provider e binding](#provider-e-binding)
8. [Rotte](#rotte)
9. [Configurazione](#configurazione)
10. [Comandi](#comandi)
11. [Cosa è vietato](#cosa-è-vietato)
12. [Esempi](#esempi)
13. [Best practice](#best-practice)
14. [Errori comuni](#errori-comuni)
15. [Checklist](#checklist)
16. [Riferimenti](#riferimenti)

---

## Descrizione

Laravel è un framework permissivo: la stessa cosa si può fare in cinque modi, e tutti funzionano.
Questo è utile per prototipare e dannoso per un software che vive dieci anni: senza vincoli, ogni
progetto sceglie un modo diverso e nessuno si orienta più.

Queste regole restringono deliberatamente le possibilità del framework.

---

## Struttura

**R1.** `app/` è organizzata per **livello architetturale**, non per tipo tecnico:
`Domain/`, `Application/`, `Infrastructure/`, `Http/`, `Filament/`, `Console/`.
*Motivo:* la collocazione per tipo non esprime le dipendenze ammesse.
*Verifica:* test di architettura. Vedi [layout di progetto](../docs/02-conventions/02-project-layout.md).

**R2.** Nessuna cartella `Helpers/`, `Utils/`, `Common/`, `Traits/` generica.
*Motivo:* diventano la discarica del progetto. *Verifica:* ricerca in CI.

**R3.** Le migration tenant stanno in `database/migrations/tenant/`, quelle di piattaforma in
`landlord/`.
*Motivo:* la separazione è strutturale, non convenzionale. *Verifica:* script di verifica.

---

## Controller

**R4.** Un controller fa quattro cose: autorizza, traduce l'input, invoca, risponde.
*Verifica:* revisione, test di architettura sulle dipendenze.

**R5.** Il corpo di un metodo di controller non deve superare le **10 righe**.
*Motivo:* oltre, contiene logica che appartiene a un altro livello.
*Verifica:* test di architettura.

**R6.** I controller non accedono direttamente ai repository né all'infrastruttura.
*Verifica:* test di architettura.

**R7.** L'autorizzazione è **esplicita** in ogni metodo: `$this->authorize(...)` o middleware
dichiarato.
*Motivo:* l'assenza di controllo non è un default sicuro. *Verifica:* revisione + test di rifiuto.

**R8.** I controller ritornano API Resource o viste, mai model.
*Motivo:* ritornare un model espone ogni colonna aggiunta in futuro.
*Verifica:* test di architettura.

**R9.** Nessun controller `__invoke` con più di un'operazione; nessun controller «tuttofare».
*Verifica:* revisione.

---

## Model

**R10.** I model dichiarano sempre `$fillable`, mai `$guarded = []`.
*Motivo:* `$guarded = []` permette l'assegnazione massiva di qualunque colonna, compresi i campi
di stato e i riferimenti. *Verifica:* ricerca in CI. *Livello: vincolante.*

**R11.** I cast sono dichiarati per ogni colonna non stringa, in particolare enum, date e decimali.

```php
protected function casts(): array
{
    return [
        'expiry_date' => 'immutable_date',
        'quantity' => 'decimal:3',
        'status' => BatchStatus::class,
        'metadata' => 'array',
    ];
}
```

*Motivo:* senza cast, un enum torna come stringa e una data come stringa.
*Verifica:* revisione.

**R12.** Nessuna logica di business nei model, oltre ai metodi che esprimono regole del dominio
sull'entità stessa.
*Ammesso:* `$batch->isExpired()`, `$batch->canBePicked()`.
*Vietato:* transazioni, invio di notifiche, chiamate esterne, coordinamento di altri aggregati.
*Verifica:* revisione.

**R13.** Nessun Observer.
*Motivo:* effetti collaterali impliciti, ordine di esecuzione non evidente, test difficili. Le
conseguenze di una mutazione si esprimono con eventi di dominio emessi dalle Action.
*Verifica:* ricerca in CI. *Livello: vincolante.*

**R14.** Global scope solo per la tenancy. Nessun altro scope globale.
*Motivo:* uno scope globale modifica silenziosamente ogni query, comprese quelle che non lo
prevedono. *Verifica:* revisione.

**R15.** I soft delete si usano solo dove il dominio richiede la cancellazione logica, non per
default.
*Motivo:* ogni query deve poi ricordarsi dei record cancellati. *Verifica:* revisione.

**R16.** Le relazioni dichiarano i tipi di ritorno.

```php
/** @return BelongsTo<Article, Batch> */
public function article(): BelongsTo
{
    return $this->belongsTo(Article::class);
}
```

*Verifica:* PHPStan livello 8.

---

## Query

**R17.** Nessuna query nelle viste o nei template.
*Verifica:* revisione, conteggio delle query nei test.

**R18.** Eager loading esplicito su ogni relazione usata in un elenco.
*Motivo:* è la causa più comune di lentezza. *Verifica:* test sul numero di query.

**R19.** Le collezioni sono sempre paginate o elaborate a blocchi. Mai `all()` su una tabella di
dominio.
*Verifica:* revisione, test.

**R20.** Le query grezze sono ammesse solo con parametri legati, mai con concatenazione.

```php
// ✗ Injection
DB::select("SELECT * FROM batches WHERE number = '{$number}'");

// ✓ Parametri legati
DB::select('SELECT * FROM batches WHERE number = ?', [$number]);
```

*Verifica:* ricerca in CI. *Livello: assoluto.*

**R21.** Le letture complesse per la presentazione stanno in Query object, non nei repository.
*Verifica:* revisione. Vedi [repository-pattern.md](repository-pattern.md).

---

## Facade e helper

**R22.** Nessuna Facade nel livello di dominio.
*Motivo:* il dominio non conosce il framework. *Verifica:* test di architettura.

**R23.** Nel livello applicativo le Facade sono ammesse solo per `DB` (transazioni) e `Event`.
*Motivo:* le altre nascondono dipendenze che vanno iniettate. *Verifica:* revisione.

**R24.** Nessun helper globale definito dal progetto, salvo quelli della Foundation.
*Motivo:* funzioni globali non sostituibili nei test, non tracciabili.
*Verifica:* ricerca in CI.

**R25.** `auth()` e `request()` non compaiono fuori dalla presentazione.
*Motivo:* rendono il codice dipendente dal contesto HTTP. Utente e dati arrivano nel DTO.
*Verifica:* test di architettura.

---

## Provider e binding

**R26.** Ogni contratto ha un binding esplicito in un service provider.
*Verifica:* test di risoluzione dal contenitore.

**R27.** I provider non contengono logica di business.
*Verifica:* revisione.

**R28.** I binding condizionali per ambiente sono ammessi e documentati.

```php
$this->app->bind(VatValidator::class, fn (Application $app): VatValidator =>
    config('services.vies.enabled')
        ? $app->make(ViesVatValidator::class)
        : $app->make(AlwaysValidVatValidator::class));
```

*Motivo:* permette a sviluppo e test di funzionare senza accesso esterno.
*Verifica:* revisione.

---

## Rotte

**R29.** Quattro file di rotte: `web.php`, `tenant.php`, `api.php`, `api-tenant.php`.
*Verifica:* struttura del progetto.

**R30.** Ogni rotta che tocca dati di dominio ha il middleware `tenant`.
*Verifica:* script di verifica. *Livello: assoluto.*

**R31.** Ogni rotta ha un nome.
*Motivo:* gli URL non si scrivono a mano. *Verifica:* revisione.

**R32.** Nessuna closure nelle rotte, salvo health check e redirect banali.
*Motivo:* le closure non sono cacheabili con `route:cache`. *Verifica:* `route:cache` in CI.

**R33.** Il binding implicito dei model è ammesso: la risoluzione avviene già sul database del
tenant.
*Verifica:* test di isolamento.

---

## Configurazione

**R34.** `env()` si usa **solo** dentro `config/`.
*Motivo:* con `config:cache` (sempre attivo in produzione) ritorna `null` altrove.
*Verifica:* ricerca in CI. *Livello: assoluto.*

**R35.** Ogni nuova variabile d'ambiente va aggiunta a `.env.example` nello stesso commit.
*Verifica:* revisione, script di confronto.

**R36.** Nessun segreto nel repository, nemmeno di esempio realistico: si usano placeholder
evidenti (`your-api-key-here`).
*Verifica:* scansione dei segreti in CI. *Livello: assoluto.*

---

## Comandi

**R37.** I comandi che operano su tutti i tenant devono essere **riprendibili** e supportare
`--chunk`.
*Motivo:* un fallimento a metà non deve costringere a ricominciare. *Verifica:* revisione.

**R38.** I comandi elaborano a blocchi (`chunkById`), mai caricando tutto in memoria.
*Verifica:* revisione.

**R39.** Ogni comando ritorna un codice di uscita esplicito.
*Verifica:* revisione.

**R40.** Nessun comando distruttivo senza conferma esplicita, salvo `--force` in ambiente non
produttivo.
*Verifica:* revisione.

---

## Cosa è vietato

| Elemento | Motivo del divieto | Alternativa |
|---|---|---|
| Observer | effetti impliciti, test difficili | eventi di dominio dalle Action |
| `$guarded = []` | assegnazione massiva di ogni colonna | `$fillable` esplicito |
| Global scope non di tenancy | modifica silenziosa di ogni query | scope locali espliciti |
| Facade nel dominio | dipendenza dal framework | iniezione |
| Helper globali di progetto | non sostituibili, non tracciabili | classi con contratto |
| Closure nelle rotte | impediscono `route:cache` | controller |
| `env()` fuori da `config/` | `null` in produzione | `config()` |
| Query concatenate | injection | parametri legati |
| Logica nei model oltre le regole di entità | non riutilizzabile, non testabile | Action |
| `all()` su tabelle di dominio | memoria esaurita | paginazione, `chunkById` |

---

## Esempi

### Esempio 1 — controller conforme

```php
final class MovementController extends Controller
{
    public function store(
        StoreMovementRequest $request,
        RegisterMovementAction $action,
    ): JsonResponse {
        $this->authorize('create', StockMovement::class);

        $movement = $action->execute(MovementData::fromRequest($request));

        return MovementResource::make($movement)->response()->setStatusCode(201);
    }
}
```

### Esempio 2 — model conforme

```php
final class Batch extends Model
{
    protected $fillable = ['article_id', 'number', 'expiry_date', 'quantity'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'immutable_date',
            'quantity' => 'decimal:3',
            'status' => BatchStatus::class,
        ];
    }

    /** @return BelongsTo<Article, Batch> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    // Regola di dominio sull'entità: ammessa
    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }
}
```

### Esempio 3 — violazioni

```php
final class Batch extends Model
{
    protected $guarded = [];                    // ✗ R10

    protected static function booted(): void
    {
        static::updated(function (Batch $batch): void {   // ✗ R13 (observer di fatto)
            Mail::to('qualita@example.com')->send(new BatchChanged($batch));   // ✗ R12
        });
    }

    public function scopeVisible(Builder $q): void { /* … */ }   // scope locale: ammesso
}
```

---

## Best practice

- Partire sempre dal template dell'artefatto: contiene le parti obbligatorie.
- Iniettare le dipendenze invece di usare Facade e helper.
- Contare le query sugli elenchi con dati realistici.
- Preferire i cast espliciti alla conversione manuale.
- Usare i comandi `make:*` della Foundation.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `$guarded = []` | Assegnazione massiva di colonne di stato | `$fillable` |
| Observer per gli effetti collaterali | Comportamento implicito, test fragili | Eventi dalle Action |
| Logica nel controller | Non vale per API, CLI, import | Action |
| Eager loading dimenticato | N+1 sugli elenchi | `with()` |
| `env()` in un provider | Funzionalità disattivata in produzione | `config()` |
| Closure nelle rotte | `route:cache` fallisce | Controller |
| Ritornare il model dall'API | Colonne interne esposte | API Resource |
| Rotta di dominio senza middleware `tenant` | Accesso fuori contesto | Middleware obbligatorio |

---

## Checklist

- [ ] `app/` organizzata per livello.
- [ ] Controller sotto le 10 righe, con autorizzazione esplicita.
- [ ] Model con `$fillable` e cast dichiarati.
- [ ] Nessun Observer.
- [ ] Nessun global scope oltre la tenancy.
- [ ] Eager loading su tutti gli elenchi.
- [ ] Nessuna query concatenata.
- [ ] Nessuna Facade nel dominio.
- [ ] `env()` solo in `config/`.
- [ ] Ogni rotta ha nome e, se di dominio, middleware `tenant`.
- [ ] Nessuna closure nelle rotte; `route:cache` funziona.
- [ ] Comandi riprendibili, a blocchi, con codice di uscita.

---

## Riferimenti

- [Regole PHP](php.md) · [Naming](naming.md) · [Action Pattern](action-pattern.md)
- [Livello di presentazione](../architecture/15-presentation-layer.md)
- [Struttura di progetto](../docs/02-conventions/02-project-layout.md)
- [Template](../templates/README.md)
