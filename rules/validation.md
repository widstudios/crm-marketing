# Regole — Validazione

> Tre livelli con responsabilità distinte: forma nel Form Request, validità nel value object,
> regole di business nell'Action.

---

## Indice

1. [Descrizione](#descrizione)
2. [I tre livelli](#i-tre-livelli)
3. [Regole](#regole)
4. [Regole ricorrenti di dominio italiano](#regole-ricorrenti-di-dominio-italiano)
5. [Messaggi](#messaggi)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

La validazione fallisce quando è concentrata in un solo punto: se sta solo nel form, è aggirabile
da API, importazioni e code; se sta solo nel dominio, l'utente riceve messaggi di errore inutili.

I tre livelli non sono duplicazione: proteggono da cose diverse e da percorsi diversi.

---

## I tre livelli

| Livello | Verifica | Dove | Protegge da |
|---|---|---|---|
| **Forma** | tipi, presenza, lunghezze, formati | Form Request | dati malformati dal client |
| **Validità** | il valore è intrinsecamente valido | value object | dati non validi in memoria |
| **Business** | l'operazione è ammessa | Action | operazioni non consentite dal dominio |

---

## Regole

**R1.** La validazione della forma sta nel Form Request, mai nel controller.
*Verifica:* test di architettura.

**R2.** Le regole si dichiarano come **array**, non come stringa con separatori.

```php
'quantity' => ['required', 'numeric', 'min:0.001'],   // ✓
'quantity' => 'required|numeric|min:0.001',           // ✗
```

*Motivo:* le regole con parametri contenenti `|` si rompono nella forma a stringa.
*Verifica:* revisione.

**R3.** Ogni campo ha una regola di **tipo** e una di **limite**.
*Motivo:* `'name' => ['required']` accetta una stringa da 10 MB.
*Verifica:* revisione.

**R4.** I valori da un insieme chiuso usano `Rule::enum()`.
*Verifica:* revisione.

**R5.** L'input variabile è a **lista bianca**: filtri, ordinamenti, campi di ricerca.
*Verifica:* revisione. *Livello: vincolante.*

**R6.** Le regole di business **non** stanno nel Form Request.
*Motivo:* non valgono per importazioni, API interne e code.
*Verifica:* revisione. *Livello: vincolante.*

**R7.** I valori con vincoli di validità intrinseca sono value object auto-validanti.
*Verifica:* revisione.

**R8.** La validazione di unicità nel Form Request è un controllo preventivo, non la garanzia: il
vincolo sta nel database.
*Motivo:* tra la verifica e l'inserimento esiste una condizione di corsa.
*Verifica:* presenza del vincolo nello schema.

**R9.** Il Form Request espone un metodo che costruisce il DTO.

```php
public function toData(): MovementData
{
    return MovementData::fromRequest($this);
}
```

*Verifica:* revisione.

**R10.** Nessuna query costosa dentro le regole di validazione.
*Verifica:* revisione.

---

## Regole ricorrenti di dominio italiano

Regole personalizzate della Foundation, riusabili in tutti i progetti:

| Regola | Verifica |
|---|---|
| `ValidVatNumber` | partita IVA italiana, con checksum |
| `ValidFiscalCode` | codice fiscale, con carattere di controllo |
| `ValidIban` | IBAN, con checksum |
| `ValidPec` | indirizzo PEC |
| `ValidSdiCode` | codice destinatario per la fatturazione elettronica |
| `ValidCap` | codice di avviamento postale |

**R11.** Le regole di dominio italiano si usano dalla Foundation, non si riscrivono per progetto.
*Verifica:* revisione.

---

## Messaggi

**R12.** I messaggi passano da `__()`, con chiavi in inglese.
*Verifica:* revisione.

**R13.** Il messaggio dice **cosa fare**, non solo cosa è sbagliato.

```php
// ✗ Non aiuta
'La quantità non è valida.'

// ✓ Dice cosa fare
'La quantità deve essere maggiore di zero e non superare la disponibilità di 5 unità.'
```

*Verifica:* revisione.

**R14.** I messaggi non rivelano informazioni riservate (esistenza di un record di un altro
contesto, struttura interna).
*Verifica:* revisione.

---

## Esempi

### Esempio 1 — i tre livelli

```php
// 1. Forma
final class StoreMovementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'type' => ['required', Rule::enum(MovementType::class)],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

// 2. Validità: il value object non può esistere in stato non valido
final readonly class Quantity
{
    public function __construct(public float $value)
    {
        throw_if($value <= 0, new InvalidQuantity('La quantità deve essere positiva.'));
    }
}

// 3. Business: nell'Action, vale per ogni percorso
throw_if($requested > $available, InsufficientStock::forBatch($batch->id, $requested, $available));
```

### Esempio 2 — regola di business nel posto sbagliato

```php
// ✗ La verifica non vale per API e importazioni
public function rules(): array
{
    return [
        'quantity' => ['required', 'numeric', function ($attr, $value, $fail): void {
            if ($value > Batch::find($this->batch_id)->available()) {
                $fail('Quantità superiore alla disponibilità.');
            }
        }],
    ];
}
```

La verifica può restare nel form **in aggiunta**, per dare un messaggio immediato all'utente, ma la
regola autorevole sta nell'Action.

---

## Best practice

- Scrivere prima la regola di business nell'Action, poi eventualmente replicarla nel form per il
  messaggio immediato.
- Usare i value object per eliminare interi gruppi di regole di validazione.
- Verificare le regole personalizzate con test parametrici.
- Messaggi che indicano l'azione correttiva, con i valori concreti.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Validazione solo nel form | Aggirabile da API, CLI, import | Regole nell'Action |
| Regole come stringa con `|` | Si rompono con parametri complessi | Array |
| Nessun limite di lunghezza | Input enormi accettati | Regola di limite |
| Filtri arbitrari accettati | Query costose, superficie aperta | Lista bianca |
| Unicità solo applicativa | Duplicati per condizione di corsa | Vincolo nel database |
| Query costose nelle regole | Validazione lenta | Verifica nell'Action |
| Messaggi generici | L'utente non sa cosa fare | Messaggi con azione correttiva |

---

## Checklist

- [ ] Validazione della forma nel Form Request, come array.
- [ ] Ogni campo ha regola di tipo e di limite.
- [ ] Insiemi chiusi con `Rule::enum()`.
- [ ] Lista bianca per filtri e ordinamenti.
- [ ] Regole di business nell'Action, non nel form.
- [ ] Valori con vincoli intrinseci come value object.
- [ ] Unicità garantita da un vincolo nel database.
- [ ] Il Form Request costruisce il DTO.
- [ ] Messaggi tradotti, con azione correttiva.

---

## Riferimenti

- [DTO](dto.md) · [Action Pattern](action-pattern.md) · [Sicurezza](security.md) · [REST API](rest-api.md)
- [Livello di presentazione](../architecture/15-presentation-layer.md)
- [Template Form Request](../templates/api/README.md)
