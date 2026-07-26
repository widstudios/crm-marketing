# Regole — Action Pattern

> Ogni mutazione di stato è una classe Action. Queste sono le regole che la rendono affidabile.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole](#regole)
3. [Anatomia](#anatomia)
4. [Transazioni ed eventi](#transazioni-ed-eventi)
5. [Quando non usare un'Action](#quando-non-usare-unaction)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

L'Action è il punto unico in cui un'operazione viene eseguita, indipendentemente da chi la richiede.
Il beneficio non è stilistico: elimina la possibilità che la stessa operazione si comporti
diversamente secondo il percorso di invocazione.

Decisione di riferimento: [ADR-0005](../architecture/decisions/0005-action-pattern.md).

---

## Regole

**R1.** Ogni mutazione di stato passa da una classe in `Application/<Context>/Actions/`.
*Motivo:* inventario esplicito delle operazioni. *Verifica:* revisione. *Livello: vincolante.*

**R2.** Ogni Action è `final`.
*Motivo:* l'ereditarietà rende il comportamento non deducibile dal nome.
*Verifica:* `arch('le action sono final')`.

**R3.** Ogni Action ha **un solo** metodo pubblico, chiamato `execute()`.
*Motivo:* una classe, un'operazione. *Verifica:* test di architettura.

**R4.** `execute()` riceve un **DTO**, mai una `Request`, mai un array associativo.
*Motivo:* invocabile da HTTP, CLI, coda, Filament e importazioni con lo stesso contratto.
*Verifica:* test di architettura sulle dipendenze.

**R5.** Il nome è `<Verbo><Oggetto>Action`, imperativo.
*Verifica:* test di architettura sul suffisso.

**R6.** Le dipendenze si iniettano nel costruttore, tipizzate sui **contratti**, non sulle
implementazioni.
*Verifica:* revisione, PHPStan.

**R7.** Le precondizioni di dominio si verificano **prima** di aprire la transazione.
*Motivo:* transazioni brevi, meno lock. *Verifica:* revisione.

**R8.** La transazione racchiude solo le scritture correlate.
*Verifica:* revisione.

**R9.** Nessuna chiamata a servizi esterni dentro la transazione.
*Motivo:* un servizio lento bloccherebbe il database. *Verifica:* revisione.

**R10.** Gli eventi si emettono **dopo** il commit; i job si accodano con `afterCommit()`.
*Motivo:* un listener che parte prima del commit non trova i dati. *Verifica:* revisione, test.

**R11.** L'Action ritorna l'entità modificata o `void`, mai una risposta HTTP.
*Verifica:* test di architettura.

**R12.** L'Action non conosce l'utente autenticato: se serve, arriva nel DTO.
*Motivo:* invocabile senza contesto HTTP. *Verifica:* test di architettura su `auth()`.

**R13.** L'Action non autorizza: l'autorizzazione avviene nel punto di ingresso, tramite Policy.
*Motivo:* l'autorizzazione dipende dal contesto di chiamata; un'importazione di sistema non ha un
utente. *Verifica:* revisione.

**R14.** Copertura di test al **100%**, compresi i percorsi di errore.
*Verifica:* pipeline con filtro sul namespace.

**R15.** Le eccezioni sollevate sono di dominio, mai tecniche.
*Verifica:* revisione.

---

## Anatomia

```php
<?php

declare(strict_types=1);

namespace App\Application\Inventory\Actions;

final readonly class RegisterMovementAction
{
    public function __construct(
        private BatchRepository $batches,          // R6: contratto
        private StockCalculator $calculator,
    ) {}

    public function execute(MovementData $data): StockMovement   // R3, R4, R11
    {
        $batch = $this->batches->findOrFail($data->batchId);

        // R7: precondizioni prima della transazione
        throw_if(
            $data->type->isOutbound() && $batch->isExpired(),
            BatchExpired::withId($batch->id),
        );

        $available = $this->calculator->available($batch);

        throw_if(
            $data->type->isOutbound() && $available < $data->quantity,
            InsufficientStock::forBatch($batch->id, $data->quantity, $available),
        );

        // R8: transazione limitata alle scritture
        $movement = DB::transaction(fn (): StockMovement => StockMovement::create([
            'batch_id' => $batch->id,
            'type' => $data->type,
            'quantity' => $data->quantity,
            'reason' => $data->reason,
            'operator_id' => $data->operatorId,      // R12: dal DTO
            'occurred_at' => $data->occurredAt,
        ]));

        // R10: evento dopo il commit
        MovementRegistered::dispatch($movement->id, $batch->id, $movement->occurred_at);

        return $movement;
    }
}
```

---

## Transazioni ed eventi

| Situazione | Regola |
|---|---|
| Scrittura su una sola riga | transazione facoltativa (l'operazione è già atomica) |
| Scrittura su più righe o tabelle | transazione obbligatoria |
| Risorsa contesa (giacenza, numerazione) | `lockForUpdate()` dentro la transazione |
| Chiamata esterna necessaria | fuori dalla transazione, prima o dopo |
| Job da accodare | `->afterCommit()` |
| Evento da emettere | dopo il blocco della transazione |
| Audit | automatico dal model, o esplicito con contesto |

```php
// Risorsa contesa: lock pessimistico
DB::transaction(function () use ($data): void {
    $batch = Batch::query()->lockForUpdate()->findOrFail($data->batchId);

    throw_if($batch->quantity < $data->quantity, InsufficientStock::forBatch($batch->id));

    $batch->decrement('quantity', $data->quantity);
});
```

Senza `lockForUpdate()`, due prelievi simultanei possono entrambi superare la verifica.

---

## Quando non usare un'Action

| Situazione | Cosa usare |
|---|---|
| Lettura, anche complessa | Query object |
| Coordinamento di più Action | Service |
| Regola che riguarda una sola entità | metodo di dominio sull'entità |
| Trasformazione di dati senza persistenza | funzione pura o value object |
| Operazione di sola infrastruttura (invio di una mail) | job o servizio di infrastruttura |

Un'Action che non modifica nulla è una Query mal collocata.

---

## Esempi

### Esempio 1 — la stessa Action da tre punti di ingresso

```php
// HTTP
$action->execute(MovementData::fromRequest($request));

// Filament
$action->execute(MovementData::fromArray([...$data, 'operator_id' => auth()->id()]));

// Importazione massiva
foreach ($rows as $row) {
    $action->execute(MovementData::fromImportRow($row, $systemOperatorId));
}
```

Le regole valgono in tutti e tre i casi, senza duplicazione.

### Esempio 2 — violazioni

```php
final class MovementService                                  // ✗ R1, R2, R5
{
    public function register(Request $request): JsonResponse // ✗ R4, R11
    {
        if (! auth()->user()->can('movement.create')) {      // ✗ R12, R13
            abort(403);
        }

        return DB::transaction(function () use ($request) {
            $movement = StockMovement::create($request->all());

            Http::post('https://erp.example.com/sync', [...]); // ✗ R9
            RecalculateStockJob::dispatch($movement->batch_id); // ✗ R10 (dentro la transazione)

            return response()->json($movement);              // ✗ R11
        });
    }

    public function cancel(int $id): void { /* … */ }         // ✗ R3
}
```

---

## Best practice

- Partire dal [template Action](../templates/backend/README.md).
- Un'Action per operazione, anche quando l'operazione è banale.
- Verificare le precondizioni nell'Action, non solo nel Form Request.
- Nominare l'Action con il verbo che il committente usa per quell'operazione.
- Scrivere prima il test del percorso di errore: è quello che si dimentica.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Action che riceve `Request` | Inutilizzabile fuori da HTTP | DTO |
| Più metodi pubblici | Responsabilità confusa, dipendenze in eccesso | Una per operazione |
| Autorizzazione dentro l'Action | Non invocabile da processi di sistema | Autorizzare nel punto di ingresso |
| `auth()` dentro l'Action | Dipendenza dal contesto HTTP | Utente nel DTO |
| Evento dentro la transazione | Listener che non trova i dati | Dopo il commit |
| Chiamata esterna in transazione | Database bloccato | Fuori dalla transazione |
| Nessun lock su risorsa contesa | Giacenze negative, numerazioni duplicate | `lockForUpdate()` |
| Action che ritorna una risposta HTTP | Legata alla presentazione | Ritornare l'entità |
| Copertura sotto il 100% | Percorsi di errore non verificati | Test completi |

---

## Checklist

- [ ] Ogni mutazione passa da un'Action.
- [ ] Ogni Action è `final` con un solo metodo pubblico `execute()`.
- [ ] `execute()` riceve un DTO.
- [ ] Dipendenze iniettate sui contratti.
- [ ] Precondizioni verificate prima della transazione.
- [ ] Nessuna chiamata esterna in transazione.
- [ ] Eventi dopo il commit, job con `afterCommit()`.
- [ ] Lock pessimistico dove c'è contesa.
- [ ] Nessun uso di `auth()` o `request()`.
- [ ] Nessuna autorizzazione dentro l'Action.
- [ ] Copertura al 100%, percorsi di errore compresi.

---

## Riferimenti

- [ADR-0005](../architecture/decisions/0005-action-pattern.md)
- [Livello applicativo](../architecture/13-application-layer.md)
- [DTO](dto.md) · [Service Layer](service-layer.md) · [Events](events.md) · [Policies](policies.md)
- [Template backend](../templates/backend/README.md)
