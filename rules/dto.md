# Regole — DTO

> Oggetti immutabili che trasportano dati validati tra i livelli. Il confine oltre il quale nessuno
> deve più chiedersi se un campo esiste.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole](#regole)
3. [Costruttori nominati](#costruttori-nominati)
4. [DTO e validazione](#dto-e-validazione)
5. [DTO di uscita](#dto-di-uscita)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Un array associativo che attraversa tre livelli è un contratto implicito: nessuno sa quali chiavi
contenga, quali siano obbligatorie, di che tipo siano. Ogni consumatore aggiunge i propri controlli
difensivi, e quando una chiave cambia nome il difetto si manifesta lontano dalla causa.

Il DTO rende quel contratto **esplicito e verificato dal linguaggio**.

---

## Regole

**R1.** Ogni DTO vive in `Application/<Context>/Data/` con suffisso `Data`.
*Verifica:* test di architettura.

**R2.** Ogni DTO è `final readonly`.
*Motivo:* un dato in transito non deve poter essere modificato da un livello intermedio.
*Verifica:* `arch('i DTO sono readonly')`.

**R3.** Tutte le proprietà sono pubbliche e tipizzate. Nessun `mixed`.
*Motivo:* il DTO è un contratto: i getter non aggiungono nulla a proprietà immutabili.
*Verifica:* PHPStan livello 8.

**R4.** Nessuna logica di business nel DTO.
*Ammesso:* costruttori nominati, conversioni di formato, metodi `with*` che ritornano una nuova
istanza. *Vietato:* validazione di dominio, accesso al database, calcoli di business.
*Verifica:* revisione.

**R5.** Il DTO non dipende da `Request` nel costruttore: la dipendenza sta solo nel costruttore
nominato `fromRequest()`.
*Motivo:* costruibile in qualunque contesto. *Verifica:* revisione.

**R6.** I valori con vincoli di validità sono **value object**, non tipi primitivi.

```php
public function __construct(
    public VatNumber $vatNumber,      // ✓ auto-validante
    public Quantity $quantity,        // ✓ con unità di misura
    public CarbonImmutable $occurredAt,
) {}
```

*Motivo:* un DTO con `string $vatNumber` può contenere una partita IVA non valida.
*Verifica:* revisione.

**R7.** Le date sono `CarbonImmutable`, mai stringhe.
*Verifica:* PHPStan, revisione.

**R8.** I DTO annidati sono ammessi e preferibili agli array di array.
*Verifica:* revisione.

**R9.** Le collezioni dentro un DTO dichiarano il tipo generico.

```php
/** @param array<int, MovementLineData> $lines */
public function __construct(public array $lines) {}
```

*Verifica:* PHPStan.

**R10.** Nessun valore predefinito che nasconda un dato obbligatorio.
*Motivo:* `public ?int $operatorId = null` su un campo che serve sempre sposta il difetto a valle.
*Verifica:* revisione.

---

## Costruttori nominati

Un costruttore nominato per ogni origine dei dati.

```php
final readonly class MovementData
{
    public function __construct(
        public int $batchId,
        public MovementType $type,
        public Quantity $quantity,
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
            quantity: new Quantity((float) $validated['quantity'], UnitOfMeasure::Piece),
            operatorId: $request->user()->id,
            occurredAt: CarbonImmutable::parse($validated['occurred_at'] ?? now()),
            reason: $validated['reason'] ?? null,
        );
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self { /* … */ }

    /** @param array<int, string> $row */
    public static function fromImportRow(array $row, int $systemOperatorId): self { /* … */ }

    public function withQuantity(Quantity $quantity): self
    {
        return new self(
            $this->batchId, $this->type, $quantity,
            $this->operatorId, $this->occurredAt, $this->reason,
        );
    }
}
```

| Origine | Costruttore |
|---|---|
| HTTP | `fromRequest()` |
| Filament, array generico | `fromArray()` |
| Importazione | `fromImportRow()` |
| Comando | `fromCommandOptions()` |
| Coda | ricostruito da `fromArray()` |

Il costruttore nominato è il punto in cui i dati grezzi diventano tipizzati: da lì in poi nessuno
deve più verificare l'esistenza di una chiave.

---

## DTO e validazione

Tre livelli, con responsabilità distinte:

| Livello | Verifica | Dove |
|---|---|---|
| Forma | tipi, presenza, lunghezze, formati | Form Request |
| Validità del valore | la partita IVA ha il formato corretto | value object nel costruttore |
| Regola di business | la partita IVA è unica nel tenant | Action |

Il DTO **non** valida: riceve dati già validati nella forma e li tipizza. La validità intrinseca di
un valore è responsabilità del value object che lo rappresenta.

```php
// Il DTO non verifica: il value object lo fa per costruzione
public function __construct(public VatNumber $vatNumber) {}

// VatNumber::__construct solleva se il formato è errato
```

---

## DTO di uscita

I DTO si usano anche in uscita, quando una Query deve ritornare una proiezione strutturata.

```php
final readonly class StockSummaryData
{
    public function __construct(
        public int $totalBatches,
        public int $expiringBatches,
        public float $totalQuantity,
        public CarbonImmutable $calculatedAt,
    ) {}
}
```

Preferibile a un array associativo perché la presentazione ottiene un contratto verificato dal
linguaggio. Per le collezioni grandi resta accettabile la proiezione in array, per non pagare la
costruzione di migliaia di oggetti.

---

## Esempi

### Esempio 1 — conforme

```php
final readonly class SupplierData
{
    public function __construct(
        public string $name,
        public VatNumber $vatNumber,
        public ?Email $email = null,
        public ?Address $address = null,
    ) {}

    public static function fromRequest(StoreSupplierRequest $request): self
    {
        $data = $request->validated();

        return new self(
            name: $data['name'],
            vatNumber: new VatNumber($data['vat_number']),
            email: isset($data['email']) ? new Email($data['email']) : null,
            address: isset($data['address']) ? Address::fromArray($data['address']) : null,
        );
    }
}
```

### Esempio 2 — violazioni

```php
class SupplierData                          // ✗ R2: non final, non readonly
{
    public $name;                           // ✗ R3: non tipizzata
    public string $vatNumber;               // ✗ R6: primitivo invece di value object
    public ?int $createdBy = null;          // ✗ R10: obbligatorio con default nullo
    public string $createdAt;               // ✗ R7: data come stringa

    public function __construct(Request $request)   // ✗ R5
    {
        $this->name = $request->input('name');
        $this->vatNumber = $request->input('vat_number');

        if (! preg_match('/^IT[0-9]{11}$/', $this->vatNumber)) {   // ✗ R4
            throw new InvalidArgumentException();
        }
    }

    public function isValidForCreation(): bool { /* … */ }   // ✗ R4
}
```

---

## Best practice

- Un DTO per operazione, non un DTO generico riusato per tutto.
- Value object per ogni campo con vincoli: il DTO diventa impossibile da costruire male.
- Un costruttore nominato per ogni origine, anche se all'inizio ne serve uno.
- Nomi dei parametri identici a quelli del dominio, non a quelli del form.
- Argomenti nominati alla costruzione: rendono leggibile il punto di chiamata.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Array associativo tra livelli | Contratto implicito, difetti lontani dalla causa | DTO |
| DTO mutabile | Modificato da un livello intermedio | `readonly` |
| Tipi primitivi al posto dei value object | Dati non validi che circolano | Value object |
| Validazione dentro il DTO | Responsabilità confusa | Forma nel Request, validità nel VO, business nell'Action |
| `Request` nel costruttore | Non costruibile fuori da HTTP | Costruttore nominato |
| Date come stringhe | Parsing ripetuto, formati incoerenti | `CarbonImmutable` |
| Default nullo su campo obbligatorio | Difetto spostato a valle | Parametro obbligatorio |
| DTO generico riusato | Campi non pertinenti, `null` sparsi | Un DTO per operazione |

---

## Checklist

- [ ] Ogni DTO è `final readonly` con suffisso `Data`.
- [ ] Tutte le proprietà sono pubbliche e tipizzate, nessun `mixed`.
- [ ] I campi con vincoli sono value object.
- [ ] Le date sono `CarbonImmutable`.
- [ ] Le collezioni dichiarano il tipo generico.
- [ ] Nessuna logica di business nel DTO.
- [ ] Un costruttore nominato per ogni origine.
- [ ] Nessun default che nasconda un campo obbligatorio.
- [ ] Le Action ricevono DTO, non array.

---

## Riferimenti

- [Livello applicativo](../architecture/13-application-layer.md)
- [Action Pattern](action-pattern.md) · [Validazione](validation.md) · [PHP](php.md)
- [Template DTO](../templates/backend/README.md)
