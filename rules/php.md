# Regole PHP

> Tipizzazione stretta, immutabilità dove possibile, nessuna ambiguità. Vale per ogni file `.php`
> della Factory e dei progetti generati.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole di base](#regole-di-base)
3. [Tipizzazione](#tipizzazione)
4. [Classi](#classi)
5. [Immutabilità](#immutabilità)
6. [Enum](#enum)
7. [Eccezioni](#eccezioni)
8. [Funzioni e metodi](#funzioni-e-metodi)
9. [Array e collezioni](#array-e-collezioni)
10. [Stile](#stile)
11. [Esempi](#esempi)
12. [Best practice](#best-practice)
13. [Errori comuni](#errori-comuni)
14. [Checklist](#checklist)
15. [Riferimenti](#riferimenti)

---

## Descrizione

PHP permette di scrivere codice molto ambiguo. Le regole di questo documento eliminano l'ambiguità
in modo verificabile: quasi tutte sono controllate da Pint o da PHPStan al livello 8, quindi non
richiedono attenzione umana.

Il principio guida: **rendere impossibili gli errori invece di documentarli**.

---

## Regole di base

**R1.** Ogni file PHP deve iniziare con `declare(strict_types=1);`.
*Motivo:* senza, PHP converte silenziosamente i tipi e un `'abc'` passato dove serve un `int`
diventa `0`. *Verifica:* Pint, regola `declare_strict_types`. *Livello: assoluto.*

**R2.** Un file, una classe. Il nome del file corrisponde al nome della classe.
*Motivo:* autoload PSR-4, prevedibilità. *Verifica:* Composer.

**R3.** Il codice segue PSR-12, applicato da Pint.
*Motivo:* la formattazione non si discute in revisione. *Verifica:* `composer lint`.

**R4.** Nessun `require` o `include` di file di classe.
*Motivo:* l'autoload esiste per questo. *Verifica:* ricerca in CI.

**R5.** Nessuna variabile globale, nessun `static` mutabile fuori dai contenitori previsti.
*Motivo:* stato condiviso non tracciabile, test che si influenzano tra loro.
*Verifica:* PHPStan, revisione.

---

## Tipizzazione

**R6.** Ogni parametro, proprietà e valore di ritorno deve essere tipizzato.
*Motivo:* il tipo è documentazione che non può divergere. *Verifica:* PHPStan livello 8.

**R7.** `mixed` è vietato salvo motivazione scritta nel PHPDoc.
*Motivo:* annulla il beneficio della tipizzazione. *Verifica:* PHPStan, revisione.

**R8.** I tipi generici delle collezioni e degli array devono essere dichiarati nel PHPDoc.

```php
/** @return Collection<int, Batch> */
public function expiringWithin(int $days): Collection

/** @param array<string, mixed> $payload */
public static function fromArray(array $payload): self
```

*Motivo:* `array` senza tipo non dice nulla. *Verifica:* PHPStan
`checkMissingIterableValueType`.

**R9.** `void` esplicito quando il metodo non ritorna nulla; `never` quando solleva sempre.
*Verifica:* PHPStan.

**R10.** Nessun tipo nullable «per comodità»: `?Batch` significa che l'assenza è un caso previsto e
gestito.
*Motivo:* i nullable non necessari propagano controlli in tutto il codice.
*Verifica:* revisione.

---

## Classi

**R11.** Le classi devono essere `final`, salvo quando l'estensione è prevista dal progetto
(classi base della Foundation, classi del framework).
*Motivo:* l'ereditarietà non prevista rende il comportamento non deducibile dal tipo dichiarato.
*Verifica:* test di architettura sui namespace applicativi.

**R12.** Le dipendenze si iniettano nel costruttore, con promozione delle proprietà.

```php
public function __construct(
    private readonly BatchRepository $batches,
    private readonly StockCalculator $calculator,
) {}
```

*Motivo:* dipendenze visibili, oggetto sempre in stato valido. *Verifica:* revisione.

**R13.** Nessun metodo statico che contenga logica di business.
*Motivo:* non sostituibile nei test, dipendenza nascosta. Ammessi i costruttori nominati
(`fromArray`, `fromRequest`) e le funzioni pure. *Verifica:* revisione.

**R14.** Nessuna classe con più di **una** responsabilità. Segnali di violazione: oltre 200 righe,
oltre 10 metodi pubblici, nome contenente «Manager», «Helper», «Utils».
*Verifica:* revisione, metriche.

**R15.** Le proprietà sono `private` per default; `protected` solo se l'estensione è prevista.
*Verifica:* revisione.

---

## Immutabilità

**R16.** I DTO e i value object devono essere `readonly`.

```php
final readonly class MovementData
{
    public function __construct(
        public int $batchId,
        public MovementType $type,
        public float $quantity,
    ) {}
}
```

*Motivo:* un oggetto che non cambia non può essere modificato per errore da un altro livello.
*Verifica:* test di architettura sui suffissi `Data`.

**R17.** I metodi che «modificano» un oggetto immutabile ritornano una nuova istanza.

```php
public function withQuantity(float $quantity): self
{
    return new self($this->batchId, $this->type, $quantity);
}
```

*Verifica:* revisione.

**R18.** Le date usano `CarbonImmutable`, mai `Carbon`.
*Motivo:* `Carbon` è mutabile: `$date->addDay()` modifica l'originale, e questo produce difetti
difficili da individuare. *Verifica:* test di architettura.

---

## Enum

**R19.** Ogni insieme chiuso di valori deve essere un enum, non una costante o una stringa.
*Motivo:* le stringhe magiche non sono verificabili dal compilatore. *Verifica:* revisione.

**R20.** Gli enum di dominio devono contenere le proprie regole (transizioni, capacità,
etichette).

```php
enum SupplierStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Active => in_array($target, [self::Suspended, self::Archived], true),
            self::Suspended => in_array($target, [self::Active, self::Archived], true),
            self::Archived => false,
        };
    }

    public function label(): string
    {
        return __("suppliers::status.{$this->value}");
    }
}
```

*Motivo:* la macchina a stati in un solo posto. *Verifica:* copertura al 100% sugli enum di dominio.

**R21.** Gli enum sono sempre `string`-backed, mai `int`-backed.
*Motivo:* i valori nel database restano leggibili. *Verifica:* revisione.

---

## Eccezioni

**R22.** Le eccezioni di dominio estendono una classe base di dominio, non `Exception`.
*Motivo:* permette la gestione differenziata rispetto agli errori tecnici. *Verifica:* test di
architettura.

**R23.** Ogni eccezione di dominio deve avere costruttori nominati che descrivono la causa.

```php
final class InsufficientStock extends DomainException
{
    public static function forBatch(int $batchId, float $requested, float $available): self
    {
        return new self("Batch {$batchId}: requested {$requested}, available {$available}.");
    }
}
```

*Motivo:* il punto di sollevamento resta leggibile. *Verifica:* revisione.

**R24.** Mai catturare `Throwable` o `Exception` genericamente, salvo nei confini
dell'applicazione (gestore globale, `failed()` dei job).
*Motivo:* nasconde difetti reali. *Verifica:* PHPStan, revisione.

**R25.** Mai sopprimere un'eccezione con un blocco `catch` vuoto.
*Verifica:* PHPStan, revisione.

**R26.** I messaggi di eccezione sono in **inglese**; la traduzione avviene nella presentazione.
*Verifica:* revisione.

---

## Funzioni e metodi

**R27.** Un metodo non deve superare le **30 righe**. Oltre, ha più di un compito.
*Verifica:* revisione, metriche.

**R28.** Massimo **4 parametri**. Oltre, si introduce un DTO.
*Verifica:* revisione.

**R29.** Le condizioni si risolvono con guardie anticipate, non con annidamento.

```php
// ✗ Tre livelli di annidamento
if ($supplier->isActive()) {
    if ($user->can('update', $supplier)) {
        // …
    }
}

// ✓ Guardie
throw_unless($supplier->isActive(), SupplierNotActive::withId($supplier->id));
throw_unless($user->can('update', $supplier), new AuthorizationException());
// …
```

*Verifica:* revisione.

**R30.** Nessun parametro booleano che seleziona un comportamento: si scrivono due metodi.
*Motivo:* `process($data, true)` non è leggibile al punto di chiamata. *Verifica:* revisione.

**R31.** `match` invece di `switch` quando si valuta un valore.
*Motivo:* `match` è esaustivo e non ha fall-through. *Verifica:* Rector, revisione.

---

## Array e collezioni

**R32.** Le collezioni di oggetti usano `Collection`, non array, quando servono operazioni di
trasformazione.
*Verifica:* revisione.

**R33.** Gli array associativi che rappresentano una struttura devono diventare DTO.
*Motivo:* un array con chiavi non documentate è un contratto implicito. *Verifica:* revisione.

**R34.** L'operatore di spread e la destrutturazione sono preferiti alle funzioni di manipolazione
quando migliorano la leggibilità.
*Livello: consigliato.*

---

## Stile

| Elemento | Regola |
|---|---|
| Indentazione | 4 spazi, mai tabulazioni |
| Lunghezza riga | ≤ 120 caratteri |
| Virgola finale | sempre negli elenchi multiriga |
| Importazioni | ordinate alfabeticamente, nessuna non usata |
| Nomi | in inglese (vedi [naming.md](naming.md)) |
| Commenti | in italiano, solo per spiegare il **perché** |
| PHPDoc | solo quando aggiunge informazione ai tipi |
| Stringhe | virgolette singole salvo interpolazione |

**R35.** Nessun commento che ripete il codice.

```php
// ✗ Non aggiunge nulla
// Incrementa il contatore
$counter++;

// ✓ Spiega una scelta non ovvia
// La scadenza si calcola dalla consegna e non dalla produzione: lo richiede
// la procedura di qualità del cliente (vedi ADR-P0003).
$expiry = $delivery->date->addMonths($article->shelf_life_months);
```

---

## Esempi

### Esempio 1 — classe conforme

```php
<?php

declare(strict_types=1);

namespace App\Application\Inventory\Actions;

use App\Domain\Inventory\Contracts\BatchRepository;
use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Domain\Inventory\Models\StockMovement;

final readonly class RegisterMovementAction
{
    public function __construct(
        private BatchRepository $batches,
        private StockCalculator $calculator,
    ) {}

    public function execute(MovementData $data): StockMovement
    {
        $batch = $this->batches->findOrFail($data->batchId);
        $available = $this->calculator->available($batch);

        throw_if(
            $data->type->isOutbound() && $available < $data->quantity,
            InsufficientStock::forBatch($batch->id, $data->quantity, $available),
        );

        return DB::transaction(fn (): StockMovement => StockMovement::create([
            'batch_id' => $batch->id,
            'type' => $data->type,
            'quantity' => $data->quantity,
        ]));
    }
}
```

### Esempio 2 — violazioni

```php
<?php
// ✗ R1: manca declare(strict_types=1)

class MovementService   // ✗ R11: non final, ✗ R14: nome generico
{
    public function process($data, $validate = true)   // ✗ R6, R7, R30
    {
        if ($validate) {                               // ✗ R30
            if (isset($data['quantity'])) {            // ✗ R29, R33
                if ($data['quantity'] > 0) {
                    // …
                }
            }
        }
        try {
            // …
        } catch (Exception $e) {}                      // ✗ R24, R25
    }
}
```

---

## Best practice

- Scrivere il tipo più stretto possibile: restringere dopo è più difficile.
- Preferire l'immutabilità: un oggetto che non cambia non ha difetti di stato.
- Sostituire le stringhe magiche con enum appena compaiono due volte.
- Usare i costruttori nominati per rendere leggibile il punto di creazione.
- Eseguire `composer qa` prima di ogni commit.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `strict_types` dimenticato | Conversioni silenziose di tipo | Pint lo aggiunge |
| `mixed` per non decidere | Nessuna verifica statica | Tipo reale |
| `array` senza tipo generico | PHPStan non può verificare | PHPDoc con generico |
| `Carbon` invece di `CarbonImmutable` | Modifiche accidentali della data | `CarbonImmutable` |
| Classe non `final` | Estensioni non previste | `final` per default |
| `catch (Exception $e) {}` | Difetti nascosti | Catturare il tipo specifico |
| Parametro booleano di comportamento | Chiamate illeggibili | Due metodi |
| Array associativo tra livelli | Contratto implicito | DTO |
| Commenti che ripetono il codice | Rumore da mantenere | Commentare il perché |

---

## Checklist

- [ ] `declare(strict_types=1);` in ogni file.
- [ ] Tutti i tipi dichiarati, nessun `mixed` non motivato.
- [ ] Generici dichiarati per array e collezioni.
- [ ] Classi `final` salvo estensione prevista.
- [ ] DTO e value object `readonly`.
- [ ] `CarbonImmutable` per le date.
- [ ] Enum per ogni insieme chiuso, con le proprie regole.
- [ ] Eccezioni di dominio con costruttori nominati.
- [ ] Nessun `catch` generico o vuoto.
- [ ] Metodi sotto le 30 righe, massimo 4 parametri.
- [ ] `composer lint` e `composer analyse` verdi.

---

## Riferimenti

- [Regole Laravel](laravel.md) · [Naming](naming.md) · [DTO](dto.md) · [Error handling](error-handling.md)
- [Livello di dominio](../architecture/12-domain-layer.md)
- [Analisi statica](../docs/04-quality/03-static-analysis.md)
- [Configurazioni condivise](../tooling/README.md)
