# Sviluppare una feature

> Il percorso completo di una funzionalità attraverso i livelli dell'applicazione, con l'ordine
> che minimizza i rifacimenti.

---

## Indice

1. [Descrizione](#descrizione)
2. [L'ordine di costruzione](#lordine-di-costruzione)
3. [Passo 1 — Modellare il dominio](#passo-1--modellare-il-dominio)
4. [Passo 2 — Schema e migration](#passo-2--schema-e-migration)
5. [Passo 3 — Operazioni](#passo-3--operazioni)
6. [Passo 4 — Autorizzazione](#passo-4--autorizzazione)
7. [Passo 5 — Punti di ingresso](#passo-5--punti-di-ingresso)
8. [Passo 6 — Effetti collaterali](#passo-6--effetti-collaterali)
9. [Passo 7 — Test](#passo-7--test)
10. [Passo 8 — Documentazione e traduzioni](#passo-8--documentazione-e-traduzioni)
11. [Esempi](#esempi)
12. [Best practice](#best-practice)
13. [Errori comuni](#errori-comuni)
14. [Checklist](#checklist)
15. [Riferimenti](#riferimenti)

---

## Descrizione

Una feature attraversa tutti i livelli dell'applicazione. L'ordine in cui la si costruisce
determina quanto lavoro andrà rifatto: partire dall'interfaccia sembra più rapido e produce
sistematicamente un dominio modellato sulle esigenze di una schermata.

L'ordine corretto è **dall'interno verso l'esterno**: dominio, persistenza, operazioni,
autorizzazione, interfacce.

---

## L'ordine di costruzione

```
1. Dominio          cosa esiste e quali regole lo governano
2. Schema           come si conserva
3. Operazioni       cosa si può fare (Action, Query)
4. Autorizzazione   chi può farlo
5. Ingressi         come lo si chiede (HTTP, Filament, CLI)
6. Effetti          cosa succede dopo (eventi, job, notifiche)
7. Test             verifica di tutti i livelli
8. Documentazione   perché è fatto così
```

Ogni passo dipende solo dai precedenti. Se ci si accorge di dover tornare indietro di due passi,
di solito significa che il dominio non era compreso.

---

## Passo 1 — Modellare il dominio

Domande da chiudere prima di scrivere:

| Domanda | Determina |
|---|---|
| Quali concetti nuovi introduce? | entità, value object |
| Hanno un'identità stabile? | entità vs value object |
| Quali stati attraversano? | enum con le transizioni |
| Quali invarianti valgono sempre? | validazione nel dominio |
| Quali fatti vanno annunciati? | eventi di dominio |
| Quali termini usa il cliente? | glossario e traduzioni |

```php
enum MovementType: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Adjustment = 'adjustment';

    public function affectsStock(): int
    {
        return match ($this) {
            self::Inbound => 1,
            self::Outbound => -1,
            self::Adjustment => 0,
        };
    }
}
```

La regola «lo scarico riduce la giacenza» vive nell'enum: non può essere dimenticata da chi scrive
la prossima schermata.

---

## Passo 2 — Schema e migration

Dal modello di dominio discende lo schema, non viceversa.

```php
Schema::create('stock_movements', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
    $table->string('type', 20);
    $table->decimal('quantity', 12, 3);
    $table->string('reason')->nullable();
    $table->foreignId('operator_id')->constrained('users');
    $table->timestamp('occurred_at');
    $table->timestamps();

    $table->index(['batch_id', 'occurred_at']);
    $table->index(['type', 'occurred_at']);
});
```

Da verificare sempre:

- indici sulle colonne usate nei filtri e negli ordinamenti dichiarati;
- vincoli di integrità referenziale con il comportamento corretto in cancellazione;
- `down()` implementato;
- nessuna colonna `tenant_id` (il tenant è il database);
- tipi decimali per le quantità e gli importi, mai `float`.

---

## Passo 3 — Operazioni

Una Action per ogni mutazione, una Query per ogni lettura complessa.

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

        throw_if(
            $data->type === MovementType::Outbound
                && $this->calculator->available($batch) < $data->quantity,
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

        MovementRegistered::dispatch($movement->id);

        return $movement;
    }
}
```

Punti che valgono come regola: verifica delle precondizioni **prima** della transazione,
transazione limitata alla scrittura, evento con l'identificatore dopo il commit.

---

## Passo 4 — Autorizzazione

```php
public function register(User $user, Batch $batch): bool
{
    return $user->can('movement.create')
        && ! $batch->isExpired();
}
```

Permesso **e** stato: il permesso dice cosa l'utente può fare in generale, lo stato dice se
l'operazione è ammessa adesso.

Il permesso va aggiunto anche al seeder di sistema e al ruolo predefinito, altrimenti la feature
è invisibile a tutti dopo il deploy.

---

## Passo 5 — Punti di ingresso

Gli ingressi sono **sottili**: traducono e delegano.

```php
public function store(StoreMovementRequest $request, RegisterMovementAction $action): JsonResponse
{
    $this->authorize('register', $request->batch());

    $movement = $action->execute(MovementData::fromRequest($request));

    return MovementResource::make($movement)
        ->response()
        ->setStatusCode(201);
}
```

In Filament, l'azione delega alla stessa Action:

```php
Action::make('register')
    ->form([/* campi */])
    ->action(fn (array $data) => app(RegisterMovementAction::class)
        ->execute(MovementData::fromArray($data)));
```

La stessa operazione, invocata da HTTP e dall'interfaccia amministrativa, passa dallo stesso
codice: è questo che garantisce comportamento identico.

---

## Passo 6 — Effetti collaterali

Ciò che accade **dopo** l'operazione va in listener asincroni, non nell'Action.

| Effetto | Dove |
|---|---|
| Ricalcolo di aggregati | listener in coda |
| Notifica a un utente | listener in coda |
| Chiamata a un sistema esterno | job con ripetizione |
| Scrittura dell'audit | automatica, tramite concern |
| Invalidazione della cache | listener sincrono |

```php
final class RecalculateStockOnMovement implements ShouldQueue
{
    public function handle(MovementRegistered $event): void
    {
        $movement = StockMovement::findOrFail($event->movementId);
        app(StockCalculator::class)->recalculate($movement->batch_id);
    }
}
```

Il job deve **ripristinare il contesto tenant**: un job che gira nel tenant sbagliato scrive dati
nel database sbagliato. La Foundation lo fa automaticamente per i job che usano il trait previsto.

---

## Passo 7 — Test

| Livello | Cosa verifica |
|---|---|
| Unit | regole di dominio (transizioni, calcoli, validazioni) |
| Feature | l'operazione completa, compresi errori e autorizzazione |
| Tenant | l'invisibilità dei dati tra tenant |
| Architecture | i vincoli strutturali |

Casi che non vanno dimenticati: quantità zero o negativa, lotto scaduto, permesso mancante,
concorrenza su giacenza limitata, tenant diverso.

---

## Passo 8 — Documentazione e traduzioni

- Traduzioni per ogni stringa visibile, in `it` e `en`.
- README del modulo aggiornato se cambiano le operazioni o i permessi.
- Documentazione API aggiornata se cambia un endpoint.
- Changelog se la modifica è visibile all'utente.
- ADR di progetto se è stata presa una decisione non ovvia.

---

## Esempi

### Esempio 1 — ordine sbagliato e sua conseguenza

Si parte dalla schermata Filament, si aggiungono i campi che servono, si crea la migration per
farli funzionare, si mette il calcolo della giacenza dentro la Resource.

Due settimane dopo serve la stessa operazione via API: il calcolo va duplicato, e le due copie
divergono al primo cambiamento.

### Esempio 2 — la precondizione nel posto giusto

`InsufficientStock` viene verificata nell'Action, non nel Form Request. Così la regola vale anche
quando il movimento arriva da API, da importazione massiva o da un job — cioè da tutti i percorsi
che non passano dal form.

---

## Best practice

- Costruire dall'interno verso l'esterno.
- Mettere le regole di dominio in enum e value object: lì non si dimenticano.
- Verificare le precondizioni nell'Action, non solo nella validazione HTTP.
- Delegare dagli ingressi, sempre alla stessa Action.
- Mettere gli effetti collaterali in listener asincroni.
- Aggiungere il permesso al seeder e al ruolo nello stesso commit.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Partire dall'interfaccia | Dominio modellato su una schermata | Ordine dall'interno |
| Logica duplicata tra Filament e API | Divergenza al primo cambiamento | Un'unica Action |
| Validazione solo nel Form Request | Aggirabile da API, CLI, import | Precondizioni nell'Action |
| Effetti collaterali dentro l'Action | Operazione lenta e fragile | Listener asincroni |
| Job senza contesto tenant | Scrittura nel database sbagliato | Trait della Foundation |
| Permesso non seminato | Feature invisibile dopo il deploy | Seeder aggiornato |
| `float` per quantità e importi | Errori di arrotondamento | `decimal` |

---

## Checklist

- [ ] Concetti di dominio modellati in enum e value object.
- [ ] Migration con indici, vincoli e `down()`.
- [ ] Un'Action per mutazione, con precondizioni verificate.
- [ ] Policy che considera permesso e stato.
- [ ] Ingressi sottili che delegano alla stessa Action.
- [ ] Effetti collaterali in listener asincroni con contesto tenant.
- [ ] Test unit, feature, tenant e architettura.
- [ ] Traduzioni, documentazione, changelog e permessi seminati.

---

## Riferimenti

- [Il primo modulo](../01-getting-started/03-first-module.md)
- [Action Pattern](../../rules/action-pattern.md) · [DTO](../../rules/dto.md) · [Events](../../rules/events.md)
- [Queue](../../rules/queue.md) · [Policies](../../rules/policies.md)
- [Workflow del database](03-database-workflow.md)
- [Strategia di testing](../04-quality/01-testing-strategy.md)
