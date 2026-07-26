# Foundation — Action e DTO

> `BaseAction` e `BaseData`: come si scrive un'operazione invocabile da qualunque punto di ingresso.

---

## Indice

1. [Descrizione](#descrizione)
2. [BaseAction](#baseaction)
3. [Che cosa non fa un'Action](#che-cosa-non-fa-unaction)
4. [Transazioni](#transazioni)
5. [Eventi e job dopo il commit](#eventi-e-job-dopo-il-commit)
6. [BaseData](#basedata)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Un'Action è **una operazione del dominio**, con un nome che corrisponde a ciò che accade:
`RegisterMovementAction`, `ArchiveDocumentAction`, `SuspendTenantAction`.

Il vincolo che la rende utile è uno solo: riceve un DTO, mai una `Request`. Sembra formale, e
invece è ciò che decide se la stessa operazione sarà utilizzabile da un comando, da un job e da
un'importazione massiva, oppure se andrà riscritta tre volte — con tre versioni delle stesse regole,
che divergeranno.

Decisione di riferimento: [ADR-0005](../../architecture/decisions/0005-action-pattern.md).

---

## BaseAction

```php
final class RegisterMovementAction extends BaseAction
{
    public function __construct(
        DatabaseManager $database,
        private readonly BatchRepository $batches,
    ) {
        parent::__construct($database);
    }

    public function execute(RegisterMovementData $data): StockMovement
    {
        // …
    }
}
```

Vincoli:

| Vincolo | Motivo |
|---|---|
| `final` | un'Action non si estende: una variante è un'altra operazione, con un altro nome |
| **un solo** metodo pubblico, `execute()` | due metodi pubblici significano due operazioni |
| riceve un DTO | rende l'operazione invocabile senza una richiesta HTTP |
| nessun `auth()`, `request()`, `session()` | dipendono dal contesto web, che in un job non esiste |

`BaseAction` non impone la firma di `execute()`: ogni operazione ha il proprio DTO e il proprio tipo
di ritorno, e un'interfaccia comune costringerebbe a un tipo generico che non aiuta nessuno.

---

## Che cosa non fa un'Action

Le tre esclusioni contano più di ciò che l'Action fa.

### Non autorizza

L'autorizzazione appartiene al **punto di ingresso**. La ragione è concreta: la stessa operazione
deve poter essere eseguita da un processo di sistema — un job notturno, un'importazione, un comando
di manutenzione — dove non esiste un utente da autorizzare.

```php
// ✓ Nel controller
$this->authorize('create', StockMovement::class);
$action->execute(RegisterMovementData::fromRequest($request));

// ✗ Dentro l'Action
if (! auth()->user()->can('movement.create')) { … }   // il job notturno non ha un utente
```

### Non valida il formato

Lo fa la Form Request. L'Action verifica le **precondizioni di dominio**, che sono un'altra cosa:
«la quantità è un numero positivo» è validazione, «il lotto ha disponibilità sufficiente» è dominio.

### Non contiene le regole

Le regole stanno nelle entità e nei value object. Un'Action che decide se un lotto è utilizzabile
duplica quella decisione nel momento in cui il lotto serve altrove — nell'importazione, in un job,
in un altro caso d'uso — e le due copie divergeranno.

```php
// ✗ La regola è nell'Action: l'importazione massiva non la applicherà
if ($batch->expiry_date < now()) {
    throw new BatchExpired();
}

// ✓ La regola è nell'entità: chiunque la usi la applica
$batch->assertUsable();
```

L'Action **orchestra**: verifica le precondizioni, apre la transazione, invoca il dominio, emette
gli eventi dopo il commit.

---

## Transazioni

```php
return $this->transaction(function (): StockMovement {
    $batch = $this->batches->findForUpdateOrFail($data->batchId);
    $batch->assertCanRelease($data->quantity);

    $movement = $batch->release($data->quantity);
    $this->batches->save($batch);

    return $movement;
});
```

Due regole, entrambe con conseguenze reali:

**Il lock prima della verifica.** `findForUpdateOrFail()` invece di `findOrFail()` quando
l'operazione legge un valore, decide in base a quello e poi scrive. Senza lock, due richieste
simultanee superano entrambe la verifica di disponibilità e producono una giacenza negativa —
esito che nessuna delle due, da sola, avrebbe consentito.

**Nessuna chiamata esterna dentro la transazione.** Una chiamata HTTP lenta tiene aperti i lock per
tutta la sua durata; una chiamata fallita lascia il sistema esterno in uno stato che il rollback non
annulla.

---

## Eventi e job dopo il commit

```php
$this->afterCommit(fn () => event(new MovementRegistered($movement->getKey())));
```

Un evento emesso dentro la transazione può essere gestito da un listener in coda che parte **prima**
del commit: il listener cerca la riga, non la trova, e fallisce. Il difetto dipende dal carico,
quindi non si riproduce in sviluppo e compare in produzione.

Per i job la forma equivalente è `dispatch(…)->afterCommit()`.

---

## BaseData

```php
final readonly class RegisterMovementData extends BaseData
{
    public function __construct(
        public int $batchId,
        public MovementType $type,
        public Quantity $quantity,
        public ?string $reason = null,
    ) {}

    public static function fromRequest(RegisterMovementRequest $request): self
    {
        return new self(
            batchId: $request->integer('batch_id'),
            type: MovementType::from($request->string('type')->toString()),
            quantity: new Quantity($request->string('quantity')->toString()),
            reason: $request->string('reason')->toString() ?: null,
        );
    }

    public static function fromImportRow(array $row): self
    {
        return new self(
            batchId: (int) $row['lotto_id'],
            type: MovementType::Outbound,
            quantity: new Quantity($row['quantita']),
        );
    }
}
```

`readonly` non è una preferenza stilistica: un oggetto che cambia mentre attraversa tre livelli non
trasporta più nulla di affidabile, e trovare **chi** lo ha cambiato costa una sessione di debug.

I costruttori nominati mettono in un punto solo la traduzione da una forma esterna a quella interna,
e permettono di aggiungere una sorgente — un'importazione, un webhook, un comando — senza toccare le
altre.

`toArray()` riduce enum e value object al loro valore scalare: un DTO serializzato deve essere
leggibile da chi non conosce le classi del dominio.

---

## Esempi

### Action completa

```php
final class RegisterMovementAction extends BaseAction
{
    use RecordsAudit;

    public function __construct(
        DatabaseManager $database,
        private readonly BatchRepository $batches,
    ) {
        parent::__construct($database);
    }

    public function execute(RegisterMovementData $data): StockMovement
    {
        return $this->transaction(function () use ($data): StockMovement {
            $batch = $this->batches->findForUpdateOrFail($data->batchId);

            $batch->assertUsable();

            $movement = match ($data->type) {
                MovementType::Inbound => $batch->receive($data->quantity, $data->reason),
                MovementType::Outbound => $batch->release($data->quantity, $data->reason),
            };

            $this->batches->save($batch);

            $this->audit('movement.registered', $movement->getKey(), [
                'batch_id' => $batch->getKey(),
                'type' => $data->type->value,
            ]);

            $this->afterCommit(function () use ($movement): void {
                event(new MovementRegistered($movement->getKey()));
                RecalculateStockJob::dispatch($movement->batch_id)->afterCommit();
            });

            return $movement;
        });
    }
}
```

La stessa Action, invocata da tre punti di ingresso diversi:

```php
// Controller
$action->execute(RegisterMovementData::fromRequest($request));

// Importazione
foreach ($rows as $row) {
    $action->execute(RegisterMovementData::fromImportRow($row));
}

// Comando di manutenzione
$action->execute(new RegisterMovementData(
    batchId: $batchId,
    type: MovementType::Outbound,
    quantity: new Quantity('1.000'),
    reason: 'Rettifica inventariale',
));
```

Se l'Action avesse ricevuto una `Request`, gli ultimi due sarebbero stati impossibili senza
costruirne una finta — che è il segnale che il progetto ha preso la strada sbagliata.

---

## Best practice

- Un'Action per operazione, con il nome dell'operazione.
- Il DTO si costruisce con un costruttore nominato, uno per sorgente.
- Lock pessimistico ogni volta che si legge, si decide e si scrive.
- Eventi e job sempre dopo il commit.
- Se un'Action supera le ~40 righe, quasi sempre contiene una regola che appartiene al dominio.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Action che riceve `Request` | Inutilizzabile da CLI, coda, importazioni | DTO |
| Autorizzazione dentro l'Action | I processi di sistema non possono eseguirla | Nel punto di ingresso |
| Regole di dominio nell'Action | Duplicate appena l'entità serve altrove | Nell'entità |
| Due metodi pubblici | Sono due operazioni | Due Action |
| Nessun lock su risorsa contesa | Giacenze negative sotto concorrenza | `findForUpdateOrFail()` |
| Chiamata esterna in transazione | Lock lunghi, rollback che non annulla nulla | Fuori dalla transazione |
| Evento dentro la transazione | Il listener non trova i dati | `afterCommit()` |
| DTO mutabile | Cambia mentre attraversa i livelli | `readonly` |

---

## Checklist

- [ ] Ogni mutazione ha la sua Action.
- [ ] Ogni Action è `final`, con un solo `execute()` pubblico.
- [ ] Ogni Action riceve un DTO.
- [ ] Nessuna autorizzazione dentro le Action.
- [ ] Le regole stanno nel dominio, non nell'Action.
- [ ] Lock pessimistico dove c'è contesa.
- [ ] Eventi e job emessi dopo il commit.
- [ ] I DTO sono `readonly`, con costruttori nominati.
- [ ] Copertura al 100% sulle Action.

---

## Riferimenti

- [ADR-0005](../../architecture/decisions/0005-action-pattern.md)
- [Action Pattern](../../rules/action-pattern.md) · [DTO](../../rules/dto.md) · [Events](../../rules/events.md)
- [Livello applicativo](../../architecture/13-application-layer.md)
- [Repository e Query](04-repository-e-query.md)
