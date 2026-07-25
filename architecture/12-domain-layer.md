# Livello di dominio

> Dove vivono le regole di business: entità, value object, enum, eventi, contratti. Il livello che
> non conosce il framework e sopravvive a tutto il resto.

---

## Indice

1. [Descrizione](#descrizione)
2. [Che cosa contiene](#che-cosa-contiene)
3. [Entità](#entità)
4. [Value object](#value-object)
5. [Enum di dominio](#enum-di-dominio)
6. [Eventi di dominio](#eventi-di-dominio)
7. [Eccezioni di dominio](#eccezioni-di-dominio)
8. [Contratti](#contratti)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Il dominio è il livello che contiene **ciò che sarebbe vero anche senza il software**: un lotto
scaduto non si preleva, una partita IVA ha undici cifre, un fornitore archiviato non torna attivo.

È il livello che vive più a lungo: sopravvive al cambio di framework, di database, di interfaccia.
Per questo va protetto da ogni dipendenza tecnica.

---

## Che cosa contiene

```
Domain/<BoundedContext>/
├── Models/          entità
├── ValueObjects/    dati validati senza identità
├── Enums/           insiemi chiusi con le loro regole
├── Events/          fatti accaduti
├── Exceptions/      violazioni di regole
└── Contracts/       ciò di cui il dominio ha bisogno dall'esterno
```

**Cosa non contiene mai:** HTTP, sessioni, viste, coda, filesystem, chiamate esterne, `Illuminate\*`
(nel grado puro), formattazione per la presentazione.

---

## Entità

Un'entità ha un'**identità stabile**: due lotti con gli stessi attributi restano lotti diversi.

Grado pragmatico (model Eloquent con logica di dominio):

```php
final class Batch extends Model
{
    protected $casts = [
        'expiry_date' => 'immutable_date',
        'quantity' => 'decimal:3',
        'status' => BatchStatus::class,
    ];

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function canBePicked(): bool
    {
        return ! $this->isExpired()
            && $this->status === BatchStatus::Available
            && $this->quantity > 0;
    }

    public function daysUntilExpiry(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->expiry_date, absolute: false);
    }
}
```

Grado puro (classe PHP, mappata da un repository):

```php
final class Batch
{
    public function __construct(
        public readonly BatchId $id,
        public readonly ArticleId $articleId,
        public readonly BatchNumber $number,
        public readonly ExpiryDate $expiryDate,
        private Quantity $quantity,
        private BatchStatus $status,
    ) {}

    public function pick(Quantity $amount): void
    {
        throw_if($this->expiryDate->isPast(), BatchExpired::withId($this->id));
        throw_if($amount->greaterThan($this->quantity), InsufficientQuantity::forBatch($this->id));

        $this->quantity = $this->quantity->subtract($amount);
    }
}
```

In entrambi i casi la regola «un lotto scaduto non si preleva» vive nell'entità, non nel
controller e non nell'interfaccia.

---

## Value object

Identificato dal proprio **valore**, immutabile, auto-validante.

```php
final readonly class Quantity implements \Stringable
{
    public function __construct(public float $value, public UnitOfMeasure $unit)
    {
        throw_if($value < 0, new InvalidQuantity('La quantità non può essere negativa.'));
    }

    public function add(self $other): self
    {
        throw_unless($this->unit === $other->unit, new IncompatibleUnits());

        return new self($this->value + $other->value, $this->unit);
    }

    public function greaterThan(self $other): bool
    {
        throw_unless($this->unit === $other->unit, new IncompatibleUnits());

        return $this->value > $other->value;
    }

    public function __toString(): string
    {
        return sprintf('%.3f %s', $this->value, $this->unit->symbol());
    }
}
```

Il valore del value object: rende **impossibile** l'esistenza di un dato non valido in memoria.
La validazione non può essere dimenticata perché è nel costruttore.

Candidati naturali: quantità, importi, partite IVA, codici fiscali, indirizzi email, date di
scadenza, intervalli temporali, identificatori tipizzati.

---

## Enum di dominio

Non solo un elenco di valori: contiene le **regole** che li governano.

```php
enum BatchStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Depleted = 'depleted';
    case Expired = 'expired';
    case Quarantined = 'quarantined';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Available => in_array($target, [self::Reserved, self::Depleted, self::Expired, self::Quarantined], true),
            self::Reserved => in_array($target, [self::Available, self::Depleted], true),
            self::Quarantined => in_array($target, [self::Available, self::Expired], true),
            self::Depleted, self::Expired => false,
        };
    }

    public function allowsPicking(): bool
    {
        return $this === self::Available;
    }

    public function label(): string
    {
        return __("inventory::batch_status.{$this->value}");
    }
}
```

Chi legge l'enum conosce **tutte** le transizioni ammesse: non deve cercarle sparse tra controller,
Action e interfacce.

---

## Eventi di dominio

Un fatto accaduto, al **passato**, immutabile.

```php
final readonly class MovementRegistered
{
    public function __construct(
        public int $movementId,
        public int $batchId,
        public CarbonImmutable $occurredAt,
    ) {}
}
```

| Regola | Motivo |
|---|---|
| Nome al passato | descrive un fatto, non un comando |
| Trasporta identificatori, non oggetti | i listener asincroni devono rileggere lo stato corrente |
| Immutabile (`readonly`) | un fatto accaduto non cambia |
| Emesso **dopo** il commit | altrimenti il listener non trova i dati |
| Nessuna logica dentro l'evento | è un messaggio, non un comportamento |

---

## Eccezioni di dominio

Rappresentano la **violazione di una regola di business**, non un errore tecnico.

```php
final class BatchExpired extends DomainException
{
    public static function withId(BatchId $id): self
    {
        return new self("Il lotto {$id} è scaduto e non può essere prelevato.");
    }

    public function context(): array
    {
        return ['batch_id' => (string) $this->id];
    }
}
```

Ogni eccezione di dominio ha una traduzione nella presentazione:

| Eccezione | HTTP | Interfaccia |
|---|---|---|
| `BatchExpired` | `422` | messaggio di errore sul campo |
| `InsufficientQuantity` | `422` | messaggio con la disponibilità |
| `TransitionNotAllowed` | `409` | azione disabilitata |
| `EntityNotFound` | `404` | pagina non trovata |

---

## Contratti

Il dominio dichiara ciò di cui ha bisogno, senza sapere come verrà fornito.

```php
namespace App\Domain\Inventory\Contracts;

interface BatchRepository
{
    public function findById(BatchId $id): ?Batch;

    public function findOrFail(BatchId $id): Batch;

    /** @return Collection<int, Batch> */
    public function expiringWithin(int $days): Collection;

    public function save(Batch $batch): void;
}
```

Il contratto sta nel **dominio**, l'implementazione nell'**infrastruttura**: è la direzione che
rende il dominio indipendente.

---

## Esempi

### Esempio 1 — regola nel posto giusto

```php
// ✗ La regola vive nella Filament Resource: non vale per API e importazioni
->action(function (Batch $record): void {
    if ($record->expiry_date->isPast()) {
        Notification::make()->danger()->title('Lotto scaduto')->send();
        return;
    }
    // …
})

// ✓ La regola vive nel dominio, tutti i percorsi la rispettano
$batch->pick($quantity);   // solleva BatchExpired se scaduto
```

### Esempio 2 — value object che elimina una classe di difetti

Senza `Quantity`, sommare 5 kg e 5 pezzi produce 10 di qualcosa. Con `Quantity`, produce
`IncompatibleUnits`: il difetto è impossibile, non improbabile.

---

## Best practice

- Le regole di business stanno nel dominio, sempre.
- Value object per ogni dato con vincoli di validità.
- Enum con le transizioni: la macchina a stati in un posto solo.
- Eventi al passato, con identificatori.
- Contratti nel dominio, implementazioni nell'infrastruttura.
- Test unitari del dominio senza database.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Regole nel controller o nell'interfaccia | Non valgono per API, CLI, importazioni | Regole nel dominio |
| Tipi primitivi al posto dei value object | Validazione dimenticabile | Value object auto-validanti |
| Transizioni sparse nel codice | Impossibile sapere cosa è ammesso | Enum con `canTransitionTo` |
| Evento con l'intero oggetto | Dati obsoleti nel listener asincrono | Solo identificatori |
| Evento emesso dentro la transazione | Listener che non trova i dati | Emissione dopo il commit |
| Contratti nell'infrastruttura | Dipendenza invertita male | Contratti nel dominio |
| Formattazione per la presentazione nel dominio | Dominio dipendente dall'interfaccia | Formattazione in presentazione |

---

## Checklist

- [ ] Il dominio non importa da `Illuminate/` (grado puro).
- [ ] Ogni dato con vincoli ha un value object.
- [ ] Ogni insieme chiuso di stati ha un enum con le transizioni.
- [ ] Gli eventi sono al passato, immutabili, con identificatori.
- [ ] Le eccezioni di dominio hanno una traduzione HTTP e di interfaccia.
- [ ] I contratti stanno nel dominio.
- [ ] I test del dominio girano senza database.

---

## Riferimenti

- [Livelli](02-layers.md) · [Livello applicativo](13-application-layer.md)
- [Regole PHP](../rules/php.md) · [Events](../rules/events.md)
- [Template di dominio](../templates/domain/README.md)
