# Livello di presentazione

> Controller, Filament, Livewire e comandi: i punti di ingresso che traducono un protocollo in
> un'invocazione applicativa.

---

## Indice

1. [Descrizione](#descrizione)
2. [I punti di ingresso](#i-punti-di-ingresso)
3. [Controller](#controller)
4. [Form Request](#form-request)
5. [API Resource](#api-resource)
6. [Filament](#filament)
7. [Livewire](#livewire)
8. [Comandi Artisan](#comandi-artisan)
9. [Traduzione degli errori](#traduzione-degli-errori)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Il livello di presentazione è **sottile per costruzione**. Ogni punto di ingresso fa quattro cose,
sempre le stesse:

1. autorizza;
2. traduce l'input in un DTO;
3. invoca un'Action o una Query;
4. traduce il risultato nel formato del protocollo.

Se un punto di ingresso fa una quinta cosa, quella cosa appartiene a un altro livello.

---

## I punti di ingresso

| Punto | Protocollo | Cartella |
|---|---|---|
| Controller web | HTTP, risposta HTML | `Http/Controllers/` |
| Controller API | HTTP, risposta JSON | `Http/Controllers/Api/` |
| Filament Resource | interfaccia amministrativa | `Filament/Resources/` |
| Componente Livewire | interfaccia reattiva | `Livewire/` |
| Comando Artisan | riga di comando | `Console/Commands/` |

**Tutti** invocano le stesse Action. È questo che garantisce comportamento identico
indipendentemente dall'origine.

---

## Controller

```php
final class MovementController extends Controller
{
    public function store(
        StoreMovementRequest $request,
        RegisterMovementAction $action,
    ): JsonResponse {
        $this->authorize('create', StockMovement::class);

        $movement = $action->execute(MovementData::fromRequest($request));

        return MovementResource::make($movement)
            ->response()
            ->setStatusCode(201);
    }

    public function index(Request $request, ListMovementsQuery $query): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockMovement::class);

        return MovementResource::collection(
            $query->execute(
                batchId: $request->integer('batch_id') ?: null,
                perPage: min($request->integer('per_page', 25), 100),
            )
        );
    }
}
```

| Regola | Verifica |
|---|---|
| Corpo del metodo sotto le 10 righe | test di architettura |
| Autorizzazione esplicita | revisione + test |
| Nessuna logica di business | test di architettura |
| Nessun accesso diretto ai repository | test di architettura |
| Dipendenze iniettate nel metodo | revisione |
| Ritorna sempre una Resource, mai un model | revisione |

---

## Form Request

```php
final class StoreMovementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'type' => ['required', Rule::enum(MovementType::class)],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'reason' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => __('inventory.validation.quantity_positive'),
        ];
    }
}
```

Il Form Request verifica la **forma**; le regole di business restano nell'Action. Non è
duplicazione: la forma protegge dai dati malformati, il dominio dalle operazioni non ammesse, e i
due controlli servono percorsi diversi.

`exists:batches,id` funziona senza filtro sul tenant perché la query gira già sul database del
tenant corrente.

---

## API Resource

```php
final class MovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'quantity' => (string) $this->quantity,
            'reason' => $this->reason,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'batch' => BatchResource::make($this->whenLoaded('batch')),
            'operator' => UserResource::make($this->whenLoaded('operator')),
        ];
    }
}
```

| Regola | Motivo |
|---|---|
| Elenco esplicito dei campi | una colonna aggiunta domani non viene esposta per errore |
| `whenLoaded()` per le relazioni | evita N+1 |
| Date in ISO 8601 con fuso | interoperabilità |
| Decimali come stringa | evita perdite di precisione in JSON |
| Enum come valore, non come etichetta tradotta | il client traduce |

---

## Filament

Una Resource **dichiara** l'interfaccia; le azioni delegano.

```php
Action::make('register')
    ->authorize(fn (Batch $record): bool => auth()->user()->can('register', $record))
    ->form([/* campi */])
    ->action(function (Batch $record, array $data): void {
        try {
            app(RegisterMovementAction::class)->execute(MovementData::fromArray([
                ...$data,
                'batch_id' => $record->id,
                'operator_id' => auth()->id(),
            ]));

            Notification::make()->success()->title(__('inventory.movement.registered'))->send();
        } catch (InsufficientStock $e) {
            Notification::make()->danger()->title(__('inventory.error.insufficient_stock'))->send();
        }
    });
```

Il corpo di `->action()` invoca, notifica, eventualmente reindirizza. Un `if` su una regola di
dominio dentro questo blocco è la regola nel posto sbagliato.

---

## Livewire

```php
final class MovementForm extends Component
{
    public int $batchId;
    public string $type = 'outbound';
    public float $quantity = 0;

    public function save(RegisterMovementAction $action): void
    {
        // Ogni metodo pubblico è un endpoint: l'autorizzazione è obbligatoria.
        $this->authorize('create', StockMovement::class);

        $validated = $this->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $action->execute(MovementData::fromArray([...$validated, 'batch_id' => $this->batchId]));

        $this->dispatch('movement-registered');
    }
}
```

| Regola | Motivo |
|---|---|
| Ogni metodo pubblico autorizza | è raggiungibile dal browser |
| Nessun dato sensibile nelle proprietà pubbliche | vengono inviate al client |
| Proprietà semplici, non oggetti complessi | serializzate ad ogni richiesta |
| Nessuna logica di business | delegare all'Action |

---

## Comandi Artisan

```php
final class RecalculateStockCommand extends Command
{
    protected $signature = 'stock:recalculate {--tenant=} {--chunk=500}';

    public function handle(RecalculateStockAction $action): int
    {
        $this->info('Ricalcolo delle giacenze…');

        Batch::query()->chunkById((int) $this->option('chunk'), function ($batches) use ($action): void {
            $batches->each(fn (Batch $batch) => $action->execute($batch));
            $this->output->write('.');
        });

        $this->newLine();
        $this->info('Completato.');

        return self::SUCCESS;
    }
}
```

| Regola | Motivo |
|---|---|
| Elaborazione a blocchi | memoria costante su grandi volumi |
| Codice di uscita esplicito | usabile in pipeline e scheduler |
| Avanzamento visibile | i comandi lunghi devono dire cosa stanno facendo |
| Contesto tenant esplicito | mai assumere il contesto |
| Riprendibile | un fallimento a metà non deve costringere a ricominciare |

---

## Traduzione degli errori

| Eccezione di dominio | HTTP | Filament | CLI |
|---|---|---|---|
| `InsufficientStock` | `422` | notifica di errore | messaggio + codice 1 |
| `BatchExpired` | `422` | notifica di errore | messaggio + codice 1 |
| `TransitionNotAllowed` | `409` | azione disabilitata | messaggio + codice 1 |
| `EntityNotFound` | `404` | pagina non trovata | messaggio + codice 1 |
| `AuthorizationException` | `403` | azione nascosta | messaggio + codice 126 |
| `ValidationException` | `422` con dettagli | errori sui campi | elenco degli errori |

La traduzione avviene nel gestore delle eccezioni, in un punto solo, non ripetuta in ogni
controller.

---

## Esempi

### Esempio 1 — controller conforme

Cinque righe: autorizza, traduce, invoca, risponde. Nessuna condizione di dominio, nessun accesso
al repository, nessuna formattazione manuale.

### Esempio 2 — logica da spostare

```php
// ✗ Regola di business nel controller
public function store(Request $request): RedirectResponse
{
    $batch = Batch::find($request->batch_id);

    if ($batch->expiry_date->isPast()) {
        return back()->withErrors('Lotto scaduto.');
    }

    if ($batch->quantity < $request->quantity) {
        return back()->withErrors('Quantità insufficiente.');
    }

    StockMovement::create([...]);

    return redirect()->route('movements.index');
}
```

Le due regole non valgono per le API né per le importazioni. Vanno nell'Action.

---

## Best practice

- Il punto di ingresso fa quattro cose: autorizza, traduce, invoca, risponde.
- Tutti i punti di ingresso invocano le stesse Action.
- Autorizzazione esplicita ovunque, comprese azioni Filament e metodi Livewire.
- Serializzazione con campi espliciti.
- Traduzione degli errori centralizzata.
- Comandi riprendibili, a blocchi, con avanzamento visibile.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Logica di business nel controller | Non vale per gli altri punti di ingresso | Spostare nell'Action |
| Ritornare il model invece della Resource | Esposizione di colonne interne | API Resource |
| Azione Filament senza `authorize()` | Aperta a chiunque veda la pagina | Autorizzazione esplicita |
| Metodo Livewire senza autorizzazione | Endpoint aperto | Autorizzazione esplicita |
| Dati sensibili in proprietà pubbliche Livewire | Esposti nel browser | Solo i dati necessari |
| Comando senza elaborazione a blocchi | Memoria esaurita | `chunkById` |
| Traduzione degli errori ripetuta ovunque | Comportamento incoerente | Gestore centralizzato |

---

## Checklist

- [ ] Ogni controller autorizza esplicitamente.
- [ ] Il corpo dei metodi resta sotto le 10 righe.
- [ ] Nessun accesso diretto ai repository dalla presentazione.
- [ ] Le risposte passano da API Resource con campi espliciti.
- [ ] Ogni azione Filament e ogni metodo Livewire autorizzano.
- [ ] Nessun dato sensibile nelle proprietà pubbliche Livewire.
- [ ] I comandi elaborano a blocchi e sono riprendibili.
- [ ] La traduzione delle eccezioni è centralizzata.

---

## Riferimenti

- [Livelli](02-layers.md) · [Livello applicativo](13-application-layer.md)
- [Regole Laravel](../rules/laravel.md) · [Filament](../rules/filament.md) · [Livewire](../rules/livewire.md)
- [REST API](../rules/rest-api.md) · [Validazione](../rules/validation.md)
- [Template](../templates/README.md)
