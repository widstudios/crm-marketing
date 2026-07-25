# Livello applicativo

> Action, Query, DTO e Service: il livello che orchestra il dominio e rende ogni operazione
> invocabile da qualunque punto di ingresso.

---

## Indice

1. [Descrizione](#descrizione)
2. [I quattro elementi](#i-quattro-elementi)
3. [Action](#action)
4. [Query](#query)
5. [DTO](#dto)
6. [Service](#service)
7. [Transazioni](#transazioni)
8. [Quando serve cosa](#quando-serve-cosa)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Il livello applicativo risponde alla domanda: *cosa può fare questo sistema?*

La risposta è l'elenco delle Action e delle Query. Scorrerlo dà, in un colpo d'occhio, l'inventario
completo delle operazioni possibili — informazione che in un'applicazione senza questo livello
richiederebbe di leggere tutti i controller.

---

## I quattro elementi

| Elemento | Responsabilità | Ritorna | Suffisso |
|---|---|---|---|
| **Action** | una mutazione di stato | l'entità modificata | `Action` |
| **Query** | una lettura complessa | una proiezione | `Query` |
| **DTO** | trasporto di dati validati | — | `Data` |
| **Service** | coordinamento di più Action | variabile | `Service` |

Regola di scelta: se **cambia** qualcosa è un'Action; se **legge** ed è complesso è una Query; se
coordina più operazioni è un Service; altrimenti probabilmente non serve una classe.

---

## Action

```php
final readonly class RegisterMovementAction
{
    public function __construct(
        private BatchRepository $batches,
        private StockCalculator $calculator,
    ) {}

    public function execute(MovementData $data): StockMovement
    {
        $batch = $this->batches->findOrFail($data->batchId);

        // Precondizioni di dominio, verificate prima della transazione
        throw_if($batch->isExpired() && $data->type->isOutbound(), BatchExpired::withId($batch->id));
        throw_if(
            $data->type->isOutbound() && $this->calculator->available($batch) < $data->quantity,
            InsufficientStock::forBatch($batch->id),
        );

        $movement = DB::transaction(fn (): StockMovement => StockMovement::create([
            'batch_id' => $batch->id,
            'type' => $data->type,
            'quantity' => $data->quantity,
            'reason' => $data->reason,
            'operator_id' => $data->operatorId,
            'occurred_at' => $data->occurredAt,
        ]));

        MovementRegistered::dispatch($movement->id, $batch->id, $movement->occurred_at);

        return $movement;
    }
}
```

| Regola | Motivo |
|---|---|
| `final` | l'ereditarietà tra Action rende il comportamento non deducibile dal nome |
| **Un solo** metodo pubblico (`execute`) | un'operazione per classe |
| Riceve un DTO, mai una `Request` | invocabile da HTTP, CLI, coda, Filament |
| Verifica le precondizioni **prima** della transazione | transazioni brevi |
| Transazione limitata alle scritture | meno lock |
| Emette gli eventi **dopo** il commit | i listener trovano i dati |
| Nessuna dipendenza da HTTP | riutilizzabile |
| Copertura di test al **100%** | è la superficie funzionale |

---

## Query

Incapsula una lettura complessa e ritorna una **proiezione**, non entità.

```php
final readonly class ExpiringBatchesQuery
{
    public function execute(int $days = 30, ?int $warehouseId = null): Collection
    {
        return Batch::query()
            ->select(['id', 'article_id', 'number', 'expiry_date', 'quantity'])
            ->with(['article:id,code,name'])
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('quantity', '>', 0)
            ->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->orderBy('expiry_date')
            ->get();
    }
}
```

Differenza rispetto al Repository:

| | Repository | Query |
|---|---|---|
| Ritorna | entità o aggregati | proiezioni, elenchi, aggregazioni |
| Usato da | dominio e Action | presentazione e report |
| Contratto | nel dominio | nessuno, è già applicativo |
| Scopo | persistenza | lettura ottimizzata |

Questa separazione risolve la tensione ricorrente: il Repository resta puro (entità), mentre le
letture ottimizzate per l'interfaccia vivono nelle Query.

---

## DTO

Oggetto immutabile che trasporta dati **già validati**.

```php
final readonly class MovementData
{
    public function __construct(
        public int $batchId,
        public MovementType $type,
        public float $quantity,
        public int $operatorId,
        public CarbonImmutable $occurredAt,
        public ?string $reason = null,
    ) {}

    public static function fromRequest(StoreMovementRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            batchId: (int) $validated['batch_id'],
            type: MovementType::from($validated['type']),
            quantity: (float) $validated['quantity'],
            operatorId: $request->user()->id,
            occurredAt: CarbonImmutable::parse($validated['occurred_at'] ?? now()),
            reason: $validated['reason'] ?? null,
        );
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self { /* … */ }
}
```

| Regola | Motivo |
|---|---|
| `readonly` | i dati non cambiano dopo la costruzione |
| Proprietà tipizzate, mai `mixed` | contratto esplicito |
| Costruttori nominati per ogni origine | HTTP, array, comando, importazione |
| Nessuna logica di business | è trasporto, non comportamento |
| Nessuna dipendenza da `Request` nel costruttore | costruibile ovunque |

Il DTO è il confine tra presentazione e applicazione: da qui in poi i dati sono tipizzati e
validati, e nessuno deve più chiedersi se un campo esiste.

---

## Service

Coordina più Action, gestisce processi che attraversano più aggregati, incapsula logica tecnica
riutilizzabile.

```php
final readonly class StockTransferService
{
    public function __construct(
        private RegisterMovementAction $registerMovement,
        private ReserveBatchAction $reserveBatch,
    ) {}

    public function transfer(TransferData $data): TransferResult
    {
        return DB::transaction(function () use ($data): TransferResult {
            $outbound = $this->registerMovement->execute($data->toOutboundMovement());
            $inbound = $this->registerMovement->execute($data->toInboundMovement());

            return new TransferResult($outbound->id, $inbound->id);
        });
    }
}
```

**Quando NON serve un Service:** per una singola operazione (basta l'Action), per raggruppare
metodi non correlati (nasce il «Service tuttofare»), per accedere a dati (è una Query).

Un `...Service` con dieci metodi pubblici non correlati è quasi sempre un insieme di Action non
ancora separate.

---

## Transazioni

| Regola | Motivo |
|---|---|
| Precondizioni verificate **prima** della transazione | transazioni brevi, meno lock |
| Transazione limitata alle scritture correlate | riduce i tempi di attesa |
| Nessuna chiamata esterna dentro la transazione | una API lenta bloccherebbe il database |
| Nessun dispatch di job dentro la transazione | il job potrebbe partire prima del commit |
| Eventi emessi dopo il commit | i listener trovano i dati |
| Lock pessimistico su risorse contese | evita condizioni di corsa |

```php
// ✓ Lock su risorsa contesa
DB::transaction(function () use ($batchId, $quantity): void {
    $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);

    throw_if($batch->quantity < $quantity, InsufficientStock::forBatch($batchId));

    $batch->decrement('quantity', $quantity);
});
```

Senza `lockForUpdate()`, due prelievi simultanei sullo stesso lotto possono entrambi superare la
verifica e produrre una giacenza negativa.

---

## Quando serve cosa

```
L'operazione cambia lo stato?
├── sì → una sola operazione?
│        ├── sì → ACTION
│        └── no, coordina più Action → SERVICE
└── no → è una lettura complessa o ottimizzata?
         ├── sì → QUERY
         └── no → accesso diretto al repository o al model
```

---

## Esempi

### Esempio 1 — stessa Action, tre punti di ingresso

```php
// HTTP
$action->execute(MovementData::fromRequest($request));

// Filament
$action->execute(MovementData::fromArray($data));

// Comando di importazione
foreach ($rows as $row) {
    $action->execute(MovementData::fromImportRow($row));
}
```

Tre origini, un solo comportamento: le regole valgono ovunque, senza duplicazione.

### Esempio 2 — Service tuttofare da correggere

`InventoryService` con dodici metodi: creazione articolo, aggiornamento, registrazione movimento,
calcolo giacenza, esportazione, report.

Correzione: quattro Action, due Query, il calcolo nel dominio. Il Service sparisce, e con lui
l'ambiguità su dove cercare la logica.

---

## Best practice

- Un'Action per operazione, `final`, con un solo metodo pubblico.
- DTO come confine tra presentazione e applicazione.
- Precondizioni prima della transazione, eventi dopo il commit.
- Query per le letture ottimizzate, Repository per le entità.
- Service solo per il coordinamento reale.
- Lock pessimistico sulle risorse contese.
- Copertura al 100% sulle Action.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Action che riceve una `Request` | Inutilizzabile da CLI e coda | DTO |
| Action con più metodi pubblici | Responsabilità confusa | Una per operazione |
| Chiamata esterna dentro la transazione | Database bloccato da un servizio lento | Fuori dalla transazione |
| Job accodato dentro la transazione | Parte prima del commit | `afterCommit()` |
| Service tuttofare | Nessuno sa dove sta la logica | Separare in Action |
| Nessun lock su risorse contese | Condizioni di corsa, giacenze negative | `lockForUpdate()` |
| Repository che ritorna proiezioni arbitrarie | Confine tra livelli sfumato | Query object |

---

## Checklist

- [ ] Ogni Action è `final` con un solo metodo pubblico.
- [ ] Ogni Action riceve un DTO.
- [ ] Le precondizioni sono verificate prima della transazione.
- [ ] Gli eventi sono emessi dopo il commit.
- [ ] Nessuna chiamata esterna dentro una transazione.
- [ ] Le letture complesse sono Query object.
- [ ] I Service coordinano, non accumulano.
- [ ] Lock pessimistico dove c'è contesa.
- [ ] Copertura al 100% sulle Action.

---

## Riferimenti

- [Livelli](02-layers.md) · [Dominio](12-domain-layer.md) · [Infrastruttura](14-infrastructure-layer.md)
- [Action Pattern](../rules/action-pattern.md) · [DTO](../rules/dto.md) · [Service Layer](../rules/service-layer.md)
- [Template backend](../templates/backend/README.md)
- [ADR-0005 — Action Pattern](decisions/0005-action-pattern.md)
