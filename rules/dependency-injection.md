# Regole — Dipendenze e contratti

> Iniezione nel costruttore, dipendenze verso i contratti, binding espliciti.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole](#regole)
3. [Dove va il contratto](#dove-va-il-contratto)
4. [Binding](#binding)
5. [Quando non serve un contratto](#quando-non-serve-un-contratto)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

L'iniezione delle dipendenze serve a due cose concrete: rendere **visibile** ciò da cui una classe
dipende, e rendere **sostituibile** ciò che nei test non si vuole eseguire davvero.

Le Facade e gli helper globali sono comodi e producono l'effetto opposto: dipendenze invisibili e
non sostituibili.

---

## Regole

**R1.** Le dipendenze si iniettano nel **costruttore**, con promozione delle proprietà.

```php
public function __construct(
    private readonly BatchRepository $batches,
    private readonly StockCalculator $calculator,
) {}
```

*Motivo:* l'oggetto è sempre in stato valido e le dipendenze sono visibili nella firma.
*Verifica:* revisione.

**R2.** Le dipendenze sono tipizzate sui **contratti**, non sulle implementazioni.
*Verifica:* revisione, PHPStan.

**R3.** Massimo **4** dipendenze nel costruttore. Oltre, la classe ha troppe responsabilità.
*Verifica:* revisione, metriche.

**R4.** Nessuna risoluzione manuale dal contenitore (`app()`, `resolve()`) nel codice applicativo.
*Ammesso:* nei punti di ingresso (controller, Filament, comandi) dove il framework non può
iniettare. *Verifica:* revisione.

**R5.** Nessuna Facade nel dominio; nell'applicazione solo `DB` ed `Event`.
*Verifica:* test di architettura.

**R6.** Nessun helper globale definito dal progetto, salvo quelli della Foundation.
*Motivo:* non sostituibili nei test, non tracciabili. *Verifica:* ricerca in CI.

**R7.** Nessun metodo statico che contenga logica sostituibile.
*Ammesso:* costruttori nominati, funzioni pure. *Verifica:* revisione.

**R8.** Le dipendenze delle Action possono essere iniettate anche nel metodo `execute()` quando
servono solo lì.
*Verifica:* revisione.

---

## Dove va il contratto

| Contratto | Posizione | Implementazione |
|---|---|---|
| Persistenza di un'entità | `Domain/<Context>/Contracts/` | `Infrastructure/Repositories/` |
| Servizio esterno | `Domain/Shared/Contracts/` | `Infrastructure/External/` |
| Generazione di documenti | `Domain/Shared/Contracts/` | `Infrastructure/Storage/` |
| Ricerca | `Domain/Shared/Contracts/` | `Infrastructure/Search/` |
| Notifica su canale | `Domain/Shared/Contracts/` | `Infrastructure/Notifications/` |

**R9.** Il contratto sta nel **dominio**, l'implementazione nell'**infrastruttura**.
*Motivo:* è la direzione che rende il dominio indipendente.
*Verifica:* test di architettura. *Livello: vincolante.*

**R10.** Il contratto dichiara ciò di cui il dominio **ha bisogno**, non ciò che la tecnologia
offre.
*Verifica:* revisione.

---

## Binding

**R11.** Ogni contratto ha un binding esplicito in un service provider.
*Verifica:* test di risoluzione dal contenitore.

**R12.** I binding condizionali per ambiente sono ammessi e documentati.

```php
$this->app->bind(VatValidator::class, fn (Application $app): VatValidator =>
    config('services.vies.enabled')
        ? $app->make(ViesVatValidator::class)
        : $app->make(AlwaysValidVatValidator::class));
```

*Motivo:* permette a sviluppo e test di funzionare senza accesso esterno.
*Verifica:* revisione.

**R13.** `singleton()` solo per servizi **senza stato**.
*Motivo:* un singleton con stato in un contesto multitenant può conservare dati tra i tenant.
*Verifica:* revisione. *Livello: vincolante.*

**R14.** I binding dei moduli stanno nel provider del modulo, non in quello dell'applicazione.
*Verifica:* revisione.

---

## Quando non serve un contratto

| Caso | Perché |
|---|---|
| Classe usata da un solo punto, senza I/O | l'astrazione non aggiunge nulla |
| Value object, DTO, enum | non sono dipendenze sostituibili |
| Calcolo puro senza dipendenze | si istanzia direttamente |
| Classe della Foundation già stabile | il contratto esiste già a monte |

Creare un contratto per ogni classe produce il doppio dei file senza alcun beneficio: il criterio è
la **sostituibilità reale**.

---

## Esempi

### Esempio 1 — conforme

```php
// Dominio: dichiara il bisogno
namespace App\Domain\Shared\Contracts;

interface PdfRenderer
{
    public function render(string $view, array $data): string;
}
```

```php
// Infrastruttura: implementa
final readonly class DomPdfRenderer implements PdfRenderer
{
    public function render(string $view, array $data): string
    {
        return Pdf::loadView($view, $data)->output();
    }
}
```

```php
// Provider
$this->app->bind(PdfRenderer::class, DomPdfRenderer::class);
```

```php
// Uso: l'Action non sa quale libreria genera il PDF
public function __construct(private readonly PdfRenderer $pdf) {}
```

### Esempio 2 — violazioni

```php
final class GenerateInvoiceAction
{
    public function execute(Invoice $invoice): string
    {
        $pdf = Pdf::loadView('invoices.show', ['invoice' => $invoice]);   // ✗ R2, R5

        Storage::disk('tenant')->put($path, $pdf->output());             // ✗ R5

        Mail::to($invoice->customer->email)->send(new InvoiceMail());     // ✗ R5

        return $path;
    }
}
```

Nei test non si può sostituire nulla: ogni esecuzione genera un PDF reale e invia una mail reale.

---

## Best practice

- Iniettare sempre ciò che nei test si vorrebbe sostituire.
- Definire il contratto partendo dal bisogno del dominio.
- Verificare la risoluzione dei binding con un test.
- Se il costruttore supera quattro dipendenze, dividere la classe prima di proseguire.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Facade nel dominio | Dipendenza dal framework, test impossibili | Iniezione |
| Dipendenza sull'implementazione | Sostituzione impossibile | Contratto |
| `app()` sparso nel codice | Dipendenze invisibili | Iniezione nel costruttore |
| Contratto nell'infrastruttura | Dipendenza invertita male | Contratto nel dominio |
| `singleton()` con stato | Dati che passano tra tenant | Solo servizi senza stato |
| Contratto per ogni classe | Il doppio dei file senza beneficio | Solo dove serve sostituibilità |
| Helper globali di progetto | Non sostituibili né tracciabili | Classi con contratto |

---

## Checklist

- [ ] Dipendenze iniettate nel costruttore, tipizzate sui contratti.
- [ ] Massimo 4 dipendenze per costruttore.
- [ ] Nessuna Facade nel dominio.
- [ ] Nessun `app()` nel codice applicativo.
- [ ] Contratti nel dominio, implementazioni nell'infrastruttura.
- [ ] Ogni contratto ha un binding esplicito, verificato da un test.
- [ ] `singleton()` solo per servizi senza stato.
- [ ] Binding dei moduli nel provider del modulo.

---

## Riferimenti

- [Livelli](../architecture/02-layers.md) · [Infrastruttura](../architecture/14-infrastructure-layer.md)
- [PHP](php.md) · [Laravel](laravel.md) · [Repository Pattern](repository-pattern.md)
