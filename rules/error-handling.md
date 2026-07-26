# Regole — Gestione degli errori

> Eccezioni di dominio distinte da quelle tecniche, traduzione centralizzata, messaggi utili
> all'utente e diagnostici nei log.

---

## Indice

1. [Descrizione](#descrizione)
2. [Tassonomia](#tassonomia)
3. [Regole](#regole)
4. [Traduzione in risposte](#traduzione-in-risposte)
5. [Errori tecnici](#errori-tecnici)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Due categorie di errori richiedono trattamenti opposti: la **violazione di una regola di business**
è un esito previsto, da comunicare all'utente in modo comprensibile; un **guasto tecnico** è
imprevisto, da registrare per la diagnosi e da nascondere all'utente.

Confonderle produce messaggi tecnici mostrati agli utenti e violazioni di dominio registrate come
errori di sistema.

---

## Tassonomia

```
Throwable
├── DomainException (base di progetto)       esito previsto, comunicabile
│   ├── InsufficientStock
│   ├── BatchExpired
│   ├── TransitionNotAllowed
│   └── EntityNotFound
└── Errori tecnici                            imprevisti, da registrare
    ├── QueryException
    ├── RequestException
    └── …
```

---

## Regole

**R1.** Le eccezioni di dominio estendono una classe base di progetto, non `Exception`.
*Motivo:* permette la gestione differenziata nel gestore globale.
*Verifica:* test di architettura.

**R2.** Ogni eccezione di dominio ha costruttori nominati che descrivono la causa.

```php
final class InsufficientStock extends DomainException
{
    public static function forBatch(int $batchId, float $requested, float $available): self
    {
        return new self("Batch {$batchId}: requested {$requested}, available {$available}.");
    }
}
```

*Verifica:* revisione.

**R3.** I messaggi di eccezione sono in **inglese**; la traduzione avviene nella presentazione.
*Motivo:* i log devono essere leggibili indipendentemente dalla lingua dell'utente.
*Verifica:* revisione.

**R4.** L'eccezione porta il **contesto** necessario alla diagnosi e al messaggio utente.

```php
public function context(): array
{
    return ['batch_id' => $this->batchId, 'requested' => $this->requested];
}
```

*Verifica:* revisione.

**R5.** Mai catturare `Throwable` o `Exception` genericamente, salvo nei confini
dell'applicazione (gestore globale, `failed()` dei job).
*Verifica:* PHPStan, revisione.

**R6.** Mai un blocco `catch` vuoto.
*Verifica:* PHPStan. *Livello: vincolante.*

**R7.** Nessuna eccezione usata per il **controllo di flusso** ordinario.
*Motivo:* un'eccezione per un esito frequente rende illeggibile il percorso normale.
*Verifica:* revisione.

**R8.** Le guardie usano `throw_if` e `throw_unless` per restare leggibili.
*Verifica:* revisione.

---

## Traduzione in risposte

**R9.** La traduzione da eccezione a risposta è **centralizzata** nel gestore, non ripetuta nei
controller.
*Verifica:* revisione. *Livello: vincolante.*

| Eccezione di dominio | HTTP | Filament | CLI |
|---|---|---|---|
| `InsufficientStock` | `422` | notifica di errore | messaggio + codice 1 |
| `BatchExpired` | `422` | notifica di errore | messaggio + codice 1 |
| `TransitionNotAllowed` | `409` | azione disabilitata | messaggio + codice 1 |
| `EntityNotFound` | `404` | pagina non trovata | messaggio + codice 1 |
| `PlanLimitReached` | `402` | notifica con invito all'aggiornamento | messaggio + codice 1 |
| `AuthorizationException` | `403` | azione nascosta | messaggio + codice 126 |
| `ValidationException` | `422` con dettagli | errori sui campi | elenco |

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (DomainException $e, Request $request) {
        $status = match (true) {
            $e instanceof EntityNotFound => 404,
            $e instanceof TransitionNotAllowed => 409,
            $e instanceof PlanLimitReached => 402,
            default => 422,
        };

        return $request->expectsJson()
            ? response()->json([
                'type' => $e->problemType(),
                'title' => __($e->translationKey()),
                'status' => $status,
                'detail' => __($e->translationKey() . '.detail', $e->context()),
            ], $status)
            : back()->withErrors(['error' => __($e->translationKey(), $e->context())]);
    });
})
```

**R10.** Il messaggio mostrato all'utente è tradotto e dice **cosa fare**.
*Verifica:* revisione.

---

## Errori tecnici

**R11.** Gli errori tecnici dell'infrastruttura si traducono in eccezioni di dominio.

```php
try {
    return $this->client->get('/check', ['vat' => (string) $vatNumber])->throw()->json('valid');
} catch (RequestException $e) {
    throw VatValidationUnavailable::becauseOf($e);
}
```

*Motivo:* il chiamante non deve conoscere HTTP. *Verifica:* revisione.

**R12.** Gli errori tecnici si registrano a livello `error` con contesto completo.
*Verifica:* revisione.

**R13.** All'utente non si mostrano mai dettagli tecnici.
*Verifica:* test con `APP_DEBUG=false`. *Livello: assoluto.*

**R14.** Ogni integrazione esterna dichiara il proprio **comportamento degradato**.
*Motivo:* cosa fa il sistema se il servizio non risponde è una decisione di dominio, non tecnica.
*Verifica:* revisione, documentazione dell'integrazione.

---

## Esempi

### Esempio 1 — eccezione di dominio completa

```php
final class InsufficientStock extends DomainException
{
    private function __construct(
        string $message,
        public readonly int $batchId,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct($message);
    }

    public static function forBatch(int $batchId, float $requested, float $available): self
    {
        return new self(
            "Batch {$batchId}: requested {$requested}, available {$available}.",
            $batchId, $requested, $available,
        );
    }

    public function translationKey(): string
    {
        return 'inventory::error.insufficient_stock';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['batch_id' => $this->batchId, 'requested' => $this->requested, 'available' => $this->available];
    }
}
```

Traduzione: `'insufficient_stock' => 'Disponibilità insufficiente: :available unità sul lotto. Riduci la quantità o seleziona un altro lotto.'`

### Esempio 2 — violazioni

```php
try {
    $action->execute($data);
} catch (Exception $e) {                       // ✗ R5
    // ✗ R6 se vuoto, ✗ R13 se mostrato all'utente
    return back()->withErrors(['error' => $e->getMessage()]);
}
```

---

## Best practice

- Definire l'eccezione di dominio insieme alla regola che la solleva.
- Includere nel contesto i valori che servono al messaggio utente.
- Tradurre gli errori tecnici al confine dell'infrastruttura, non lasciarli propagare.
- Decidere il comportamento degradato di ogni integrazione **prima** di scriverla.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Eccezioni tecniche propagate all'utente | Messaggi incomprensibili, dettagli esposti | Traduzione al confine |
| `catch (Exception $e)` generico | Difetti nascosti | Catturare il tipo specifico |
| `catch` vuoto | Errore silenzioso | Gestire o rilanciare |
| Traduzione ripetuta nei controller | Comportamento incoerente | Gestore centralizzato |
| Messaggi di eccezione in italiano | Log illeggibili in contesti misti | Inglese, traduzione in presentazione |
| Eccezioni per il flusso ordinario | Percorso normale illeggibile | Valori di ritorno |
| Nessun comportamento degradato definito | Il sistema si blocca su un servizio esterno | Decisione esplicita |

---

## Checklist

- [ ] Le eccezioni di dominio estendono la classe base di progetto.
- [ ] Costruttori nominati che descrivono la causa.
- [ ] Messaggi in inglese, traduzione in presentazione.
- [ ] Contesto sufficiente per diagnosi e messaggio utente.
- [ ] Nessun `catch` generico o vuoto.
- [ ] Traduzione in risposte centralizzata nel gestore.
- [ ] Errori tecnici tradotti in eccezioni di dominio.
- [ ] Nessun dettaglio tecnico mostrato all'utente.
- [ ] Comportamento degradato dichiarato per ogni integrazione.

---

## Riferimenti

- [PHP](php.md) · [Logging](logging.md) · [REST API](rest-api.md) · [UX](ux.md)
- [Livello di infrastruttura](../architecture/14-infrastructure-layer.md)
