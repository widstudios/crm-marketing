# Dal requisito all'entità

> «I lotti scaduti non si possono usare»: la stessa regola in tre collocazioni, con le tre
> conseguenze. **Autorità: nulla.**

---

## Indice

1. [Descrizione](#descrizione) 2. [Il requisito](#il-requisito)
3. [Collocazione A — nel controller](#collocazione-a--nel-controller)
4. [Collocazione B — nell'Action](#collocazione-b--nellaction)
5. [Collocazione C — nell'entità](#collocazione-c--nellentità)
6. [Il confronto](#il-confronto) 7. [Esempi](#esempi) 8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist) 11. [Riferimenti](#riferimenti)

---

## Descrizione

La regola «la logica sta nel dominio» è facile da enunciare e difficile da applicare, perché in ogni
momento specifico metterla altrove sembra ragionevole: il controller ce l'ha già sotto mano,
l'Action è dove sta l'operazione, e il dominio richiede una classe in più.

Questo frammento mostra la **stessa** regola nelle tre collocazioni possibili, e cosa succede a
ciascuna quando il sistema cresce.

Nomi dal [walkthrough del magazzino sanitario](../walkthroughs/01-magazzino-sanitario.md): non vanno
copiati, il criterio sì.

---

## Il requisito

Dal brief:

> «I lotti scaduti non si possono usare.»

Dopo la [fermata di fase 1](../walkthroughs/01-magazzino-sanitario.md#le-fermate), la regola completa
risulta:

> Un lotto non è prelevabile se è **scaduto**, se è in **quarantena**, o se ha **giacenza zero**.
> Resta visibile negli elenchi: serve per l'inventario e per la resa al fornitore.

---

## Collocazione A — nel controller

```php
final class StockMovementController
{
    public function store(StoreMovementRequest $request, RegisterMovementAction $action): JsonResponse
    {
        $this->authorize('create', StockMovement::class);

        $batch = Batch::findOrFail($request->integer('batch_id'));

        // La regola è qui.
        if ($batch->expiry_date < now()) {
            return response()->json(['message' => 'Il lotto è scaduto.'], 422);
        }

        if ($batch->status === BatchStatus::Quarantined) {
            return response()->json(['message' => 'Il lotto è in quarantena.'], 422);
        }

        $movement = $action->execute(RegisterMovementData::fromRequest($request));

        return StockMovementResource::make($movement)->response()->setStatusCode(201);
    }
}
```

**Che cosa succede quando il sistema cresce.**

Arriva l'importazione massiva dal fornitore: un comando che legge un CSV e registra i movimenti.
Non passa dal controller, quindi non applica la regola. Da quel momento i movimenti su lotti scaduti
**entrano dall'importazione** e vengono **rifiutati dall'interfaccia**.

Il difetto non produce errori. Produce giacenze che comprendono materiale non utilizzabile, e si
scopre quando qualcuno va a prendere fisicamente un lotto che il sistema dice disponibile.

Arriva poi l'azione Filament `adjust`, che rettifica le giacenze. Terza copia della regola, o terza
via che la salta.

---

## Collocazione B — nell'Action

```php
final class RegisterMovementAction extends BaseAction
{
    public function execute(RegisterMovementData $data): StockMovement
    {
        return $this->transaction(function () use ($data): StockMovement {
            $batch = $this->batches->findForUpdateOrFail($data->batchId);

            // La regola è qui.
            if ($batch->expiry_date < now()) {
                throw BatchNotUsable::expired($batch);
            }

            if ($batch->status === BatchStatus::Quarantined) {
                throw BatchNotUsable::quarantined($batch);
            }

            $movement = $batch->release($data->quantity);
            $this->batches->save($batch);

            return $movement;
        });
    }
}
```

**Meglio.** L'importazione usa la stessa Action, quindi applica la regola. Il controller, il comando
e Filament passano tutti di qui.

**Che cosa succede quando il sistema cresce.**

Arriva un secondo caso d'uso: `ReserveBatchAction`, che impegna un lotto per un ordine senza
scaricarlo. Anche lì un lotto scaduto non va bene, quindi la regola viene **copiata**.

Poi arriva `TransferBatchAction`, per lo spostamento tra ubicazioni. Terza copia.

Quando la fermata di fase 1 aggiunge la terza condizione — giacenza zero — bisogna ricordarsi di
tutte e tre. Nel walkthrough se n'è aggiunta una in due punti su tre, e la terza è stata trovata in
revisione.

C'è anche un problema più sottile: `now()` dentro l'Action rende il comportamento verificabile solo
nel momento giusto. Un test sul caso «lotto che scade oggi a mezzanotte» richiede di manipolare
l'orologio di sistema.

---

## Collocazione C — nell'entità

```php
final class Batch extends Model
{
    /**
     * Verifica che il lotto sia utilizzabile per un movimento.
     *
     * Riceve il Clock invece di chiamare now(): è ciò che permette di
     * verificare il comportamento a fine mese senza aspettare fine mese.
     *
     * @throws BatchNotUsable
     */
    public function assertUsable(Clock $clock): void
    {
        if ($this->status === BatchStatus::Quarantined) {
            throw BatchNotUsable::quarantined($this);
        }

        if ($this->expiry_date < $clock->today()) {
            throw BatchNotUsable::expired($this);
        }

        if ($this->quantity <= 0) {
            throw BatchNotUsable::depleted($this);
        }
    }

    public function release(Quantity $quantity, ?string $reason = null): StockMovement
    {
        if ($quantity->isGreaterThan($this->availableQuantity())) {
            throw InsufficientQuantity::for($this, $quantity);
        }

        $this->quantity = $this->quantity - $quantity->value();

        return $this->movements()->make([
            'type' => MovementType::Outbound,
            'quantity' => $quantity->value(),
            'reason' => $reason,
        ]);
    }
}
```

```php
final class RegisterMovementAction extends BaseAction
{
    public function __construct(
        DatabaseManager $database,
        private readonly BatchRepository $batches,
        private readonly Clock $clock,
    ) {
        parent::__construct($database);
    }

    public function execute(RegisterMovementData $data): StockMovement
    {
        return $this->transaction(function () use ($data): StockMovement {
            $batch = $this->batches->findForUpdateOrFail($data->batchId);

            // Lock prima, verifica dopo: senza lock, due prelievi simultanei
            // superano entrambi il controllo di disponibilità.
            $batch->assertUsable($this->clock);

            $movement = $batch->release($data->quantity, $data->reason);
            $this->batches->save($batch);

            return $movement;
        });
    }
}
```

**Che cosa succede quando il sistema cresce.**

`ReserveBatchAction` e `TransferBatchAction` invocano `assertUsable()`. La terza condizione si
aggiunge in un punto solo e vale ovunque, immediatamente.

Il test unitario non tocca il database:

```php
it('rifiuta un lotto scaduto', function (): void {
    $clock = new FrozenClock('2026-07-01');
    $batch = new Batch(['expiry_date' => new DateTimeImmutable('2026-06-30')]);

    expect(fn () => $batch->assertUsable($clock))->toThrow(BatchNotUsable::class);
});

it('accetta un lotto che scade oggi', function (): void {
    // Il caso limite: «scaduto» significa prima di oggi, non oggi.
    $clock = new FrozenClock('2026-07-01');
    $batch = new Batch(['expiry_date' => new DateTimeImmutable('2026-07-01'), 'quantity' => 10]);

    expect(fn () => $batch->assertUsable($clock))->not->toThrow(BatchNotUsable::class);
});
```

Il secondo test è quello che vale la collocazione: definisce che cosa significa «scaduto» al confine
esatto, ed è verificabile in qualunque giorno dell'anno.

---

## Il confronto

| | A — controller | B — Action | C — entità |
|---|---|---|---|
| L'importazione la applica | **no** | sì | sì |
| Un secondo caso d'uso la applica | no | **solo se copiata** | sì |
| Aggiungere una condizione | N punti | N punti | **1 punto** |
| Test senza database | no | no | **sì** |
| Verificabile a qualunque data | no | no | **sì** |
| Il caso limite è definito | implicitamente | implicitamente | **esplicitamente** |
| Costo iniziale | nessuno | nessuno | **un metodo** |

L'ultima riga è la ragione per cui A e B vengono scelte. Il costo di C si paga una volta; quello di
A e B si paga a ogni caso d'uso nuovo, e si paga in difetti, non in tempo.

---

## Esempi

### Il segnale che la regola è nel posto sbagliato

```php
// Se questa condizione compare in due file diversi, appartiene al dominio.
if ($batch->expiry_date < now()) {
```

Non serve una regola per riconoscerlo: basta cercare. Una condizione di business duplicata è una
condizione che sta nel posto sbagliato, e la seconda occorrenza è il momento in cui spostarla costa
meno che lasciarla.

---

## Best practice

- Quando una condizione di business compare la seconda volta, spostarla nel dominio.
- Iniettare `Clock` invece di chiamare `now()`: rende verificabile ciò che dipende dalla data.
- Definire i casi limite con un test esplicito: «scaduto» al confine è una decisione, non un
  dettaglio.
- Lock prima della verifica, sempre, quando l'operazione legge-decide-scrive.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Regola nel controller | L'importazione e la CLI non la applicano | Nel dominio |
| Regola nell'Action | Duplicata a ogni caso d'uso nuovo | Nel dominio |
| `now()` nell'entità | Verificabile solo nel momento giusto | `Clock` iniettato |
| Caso limite non testato | «Scaduto» al confine deciso per caso | Test esplicito |
| Verifica prima del lock | Due operazioni superano entrambe il controllo | Lock, poi verifica |

---

## Checklist

- [ ] Nessuna condizione di business compare in due file.
- [ ] Le regole del dominio stanno nelle entità.
- [ ] Il dominio riceve `Clock`, non chiama `now()`.
- [ ] I casi limite hanno un test esplicito.
- [ ] Il lock precede la verifica dove c'è contesa.

---

## Riferimenti

- [Frammenti](README.md) · [Walkthrough](../walkthroughs/01-magazzino-sanitario.md)
- [Action Pattern](../../rules/action-pattern.md) · [Laravel](../../rules/laravel.md)
- [Livello di dominio](../../architecture/12-domain-layer.md)
- [Foundation — Action e DTO](../../foundation/docs/03-action-e-dto.md)
