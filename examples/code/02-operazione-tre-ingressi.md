# Un'operazione da tre ingressi

> Perché un'Action riceve un DTO e non una `Request`: la stessa operazione da controller,
> importazione e comando. **Autorità: nulla.**

---

## Indice

1. [Descrizione](#descrizione) 2. [Il punto di partenza](#il-punto-di-partenza)
3. [Il secondo ingresso](#il-secondo-ingresso) 4. [La forma corretta](#la-forma-corretta)
5. [I tre ingressi](#i-tre-ingressi) 6. [Il confronto](#il-confronto) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

«Un'Action riceve un DTO, mai una `Request`» è una delle regole che sembrano più formali della
Factory. Passare la `Request` funziona, è più corto, e il primo giorno non ha alcuno svantaggio
visibile.

Questo frammento mostra il momento esatto in cui lo svantaggio compare, e perché a quel punto la
correzione costa molto più della disciplina iniziale.

---

## Il punto di partenza

Un solo ingresso: il controller. La `Request` è lì, l'Action ne ha bisogno, e passarla è naturale.

```php
final class RegisterMovementAction extends BaseAction
{
    public function execute(Request $request): StockMovement
    {
        return $this->transaction(function () use ($request): StockMovement {
            $batch = $this->batches->findForUpdateOrFail($request->integer('batch_id'));

            $batch->assertUsable($this->clock);

            $movement = $batch->release(
                new Quantity($request->string('quantity')->toString()),
                $request->string('reason')->toString() ?: null,
            );

            $this->batches->save($batch);

            // L'operatore viene preso dalla sessione: è comodo, e lega
            // l'operazione a un contesto web.
            $movement->operator_id = auth()->id();

            return $movement;
        });
    }
}
```

Funziona. Il test passa. Il codice è più corto della versione con il DTO.

---

## Il secondo ingresso

Tre mesi dopo, il committente chiede l'importazione dei movimenti da un file del fornitore.

```php
final class ImportMovementsCommand extends Command
{
    public function handle(RegisterMovementAction $action): int
    {
        foreach ($this->rows() as $row) {
            $action->execute(/* … e adesso? */);
        }

        return self::SUCCESS;
    }
}
```

Non c'è una `Request`. Le strade disponibili sono tre, e sono tutte cattive.

### Costruire una `Request` finta

```php
$request = Request::create('/', 'POST', [
    'batch_id' => $row['id_lotto'],
    'quantity' => $row['quantita'],
]);

$action->execute($request);
```

Funziona finché l'Action non tocca `auth()`, `session()` o un'intestazione. `auth()->id()` restituisce
`null`, e `operator_id` finisce a `null` su tutti i movimenti importati — su una tabella che il
requisito normativo voleva tracciata.

Il difetto non produce errori: produce un registro incompleto, e si scopre durante un'ispezione.

### Duplicare l'Action

```php
final class ImportMovementAction extends BaseAction
{
    public function execute(array $row): StockMovement
    {
        // Le stesse regole, riscritte.
    }
}
```

Due copie della stessa operazione. Quando la fermata di fase 1 aggiunge la terza condizione, va
aggiunta in due punti — e il secondo si dimentica, perché il test dell'interfaccia continua a
passare.

### Aggiungere parametri all'Action

```php
public function execute(Request $request, ?int $operatorId = null, bool $skipAudit = false): StockMovement
```

L'Action comincia ad accumulare parametri che descrivono **da dove** viene chiamata. Al terzo
ingresso ce ne sono cinque, e due sono booleani che selezionano comportamenti diversi.

---

## La forma corretta

```php
final readonly class RegisterMovementData extends BaseData
{
    public function __construct(
        public int $batchId,
        public MovementType $type,
        public Quantity $quantity,
        public int $operatorId,
        public ?string $reason = null,
    ) {}

    public static function fromRequest(StoreMovementRequest $request): self
    {
        return new self(
            batchId: $request->integer('batch_id'),
            type: MovementType::from($request->string('type')->toString()),
            quantity: new Quantity($request->string('quantity')->toString()),
            // L'utente si legge QUI, dove esiste, non dentro l'Action.
            operatorId: $request->user()->getKey(),
            reason: $request->string('reason')->toString() ?: null,
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    public static function fromImportRow(array $row, int $operatorId): self
    {
        return new self(
            batchId: (int) $row['id_lotto'],
            type: MovementType::Outbound,
            quantity: new Quantity($row['quantita']),
            operatorId: $operatorId,
            reason: 'Importazione fornitore',
        );
    }
}
```

```php
final class RegisterMovementAction extends BaseAction
{
    public function execute(RegisterMovementData $data): StockMovement
    {
        return $this->transaction(function () use ($data): StockMovement {
            $batch = $this->batches->findForUpdateOrFail($data->batchId);

            $batch->assertUsable($this->clock);

            $movement = $batch->release($data->quantity, $data->reason);
            $movement->operator_id = $data->operatorId;

            $this->batches->save($batch);

            return $movement;
        });
    }
}
```

L'Action non sa più da dove arriva la chiamata. È l'intera differenza.

`operatorId` è **obbligatorio** e non ha valore predefinito: un movimento senza operatore non è
rappresentabile, e il tipo lo impedisce invece di lasciarlo alla disciplina.

---

## I tre ingressi

```php
// Controller
public function store(StoreMovementRequest $request, RegisterMovementAction $action): JsonResponse
{
    $this->authorize('create', StockMovement::class);

    $movement = $action->execute(RegisterMovementData::fromRequest($request));

    return StockMovementResource::make($movement)->response()->setStatusCode(201);
}
```

```php
// Importazione
public function handle(RegisterMovementAction $action): int
{
    $operatorId = $this->systemOperator()->getKey();
    $failed = [];

    foreach ($this->rows() as $number => $row) {
        try {
            $action->execute(RegisterMovementData::fromImportRow($row, $operatorId));
        } catch (DomainException $exception) {
            // Una riga scartata non ferma le altre, e non sparisce in silenzio.
            $failed[$number] = $exception->getMessage();
        }
    }

    $this->reportFailures($failed);

    return $failed === [] ? self::SUCCESS : self::FAILURE;
}
```

```php
// Comando di rettifica
$action->execute(new RegisterMovementData(
    batchId: $batchId,
    type: MovementType::Outbound,
    quantity: new Quantity('1.000'),
    operatorId: $operator->getKey(),
    reason: 'Rettifica inventariale del 26/07',
));
```

Tre ingressi, una sola implementazione delle regole. Aggiungerne un quarto — un webhook, una coda,
un'azione Filament — costa un costruttore nominato.

---

## Il confronto

| | `Request` | DTO |
|---|---|---|
| Invocabile da CLI | no | sì |
| Invocabile da un job | no | sì |
| Invocabile da un'importazione | solo con una `Request` finta | sì |
| Test unitario senza HTTP | no | sì |
| Il tipo dice cosa serve | no: qualunque campo, forse presente | sì: firma esplicita |
| Campo obbligatorio garantito | no | sì, dal costruttore |
| Aggiungere un ingresso | duplicazione o parametri | un costruttore nominato |

La riga sul tipo è quella meno citata e la più utile nel tempo. Con la `Request`, per sapere che cosa
serve all'operazione bisogna leggerne il corpo e cercare ogni `$request->`. Con il DTO, la firma lo
dice.

---

## Esempi

### Il segnale che l'Action è legata al web

```php
// Dentro un'Action, ognuna di queste è un difetto:
auth()->id()
auth()->user()
request()->ip()
session()->get(…)
$request->hasFile(…)
```

Un test di architettura le intercetta tutte:

```php
arch('le action non dipendono dal contesto web')
    ->expect('App\Application')
    ->not->toUse([
        'Illuminate\Support\Facades\Auth',
        'Illuminate\Support\Facades\Session',
        'Illuminate\Http\Request',
    ]);
```

Una riga, e il difetto non può più entrare.

---

## Best practice

- Un DTO per ogni Action, dal primo giorno: costa un file, e non si paga mai due volte.
- Un costruttore nominato per ogni sorgente, con la traduzione in un punto solo.
- Campi obbligatori senza valore predefinito: il tipo impedisce ciò che la disciplina dimenticherebbe.
- Leggere l'utente nel punto di ingresso, dove esiste.
- Un test di architettura che vieta il contesto web nel livello applicativo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Action che riceve `Request` | Inutilizzabile da CLI, coda, importazioni | DTO |
| `Request` finta costruita a mano | `auth()` restituisce `null`, campi silenziosamente vuoti | DTO |
| Action duplicata per un secondo ingresso | Le regole divergono | Costruttore nominato |
| Parametri che dicono da dove arriva la chiamata | Firma che cresce a ogni ingresso | DTO |
| `auth()->id()` dentro l'Action | Il processo di sistema scrive `null` | Nel DTO |
| Campo obbligatorio con predefinito | Passa vuoto senza che nulla lo segnali | Nessun predefinito |

---

## Checklist

- [ ] Ogni Action riceve un DTO.
- [ ] Ogni sorgente ha il proprio costruttore nominato.
- [ ] I campi obbligatori non hanno valore predefinito.
- [ ] Nessun `auth()`, `request()` o `session()` nel livello applicativo.
- [ ] Esiste il test di architettura che lo verifica.

---

## Riferimenti

- [Frammenti](README.md) · [Dal requisito all'entità](01-dal-requisito-allentita.md)
- [Action Pattern](../../rules/action-pattern.md) · [DTO](../../rules/dto.md)
- [ADR-0005](../../architecture/decisions/0005-action-pattern.md)
- [Foundation — Action e DTO](../../foundation/docs/03-action-e-dto.md)
