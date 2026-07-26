# Regole — Livewire

> Ogni metodo pubblico è un endpoint; ogni proprietà pubblica è un dato inviato al browser.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole](#regole)
3. [Proprietà](#proprietà)
4. [Metodi](#metodi)
5. [Prestazioni](#prestazioni)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Livewire nasconde il confine tra server e client, e questa comodità genera due difetti ricorrenti:
metodi pubblici raggiungibili senza autorizzazione, e dati sensibili inviati al browser nelle
proprietà pubbliche.

Il modello mentale corretto: **un componente Livewire è un controller con stato**.

---

## Regole

**R1.** Nessuna logica di business nel componente: si delega alle Action.
*Verifica:* revisione. *Livello: vincolante.*

**R2.** Ogni metodo pubblico autorizza esplicitamente.
*Motivo:* è raggiungibile dal browser come un endpoint.
*Verifica:* revisione. *Livello: vincolante.*

**R3.** Nessun dato sensibile nelle proprietà pubbliche.
*Motivo:* vengono serializzate e inviate al client ad ogni richiesta.
*Verifica:* revisione. *Livello: assoluto.*

**R4.** Le proprietà pubbliche contengono tipi semplici, non oggetti complessi o model interi.
*Motivo:* la serializzazione avviene ad ogni richiesta.
*Verifica:* revisione.

**R5.** Le query stanno in `render()` o in proprietà calcolate, non in proprietà pubbliche.
*Motivo:* una collezione in proprietà pubblica viene serializzata e rinviata continuamente.
*Verifica:* revisione.

**R6.** Ogni stringa visibile passa da `__()`.
*Verifica:* revisione.

**R7.** La validazione usa `$this->validate()` con regole dichiarate, oppure un Form Request
dedicato.
*Verifica:* revisione.

**R8.** Le proprietà esposte nell'URL usano `#[Url]` con nome esplicito.
*Verifica:* revisione.

---

## Proprietà

```php
// ✗ Il model intero viene inviato al browser: include note interne, margini, valutazioni
public Supplier $supplier;

// ✓ Solo ciò che serve alla vista
public int $supplierId;
public string $supplierName;
```

| Ammesso in proprietà pubblica | Vietato |
|---|---|
| identificatori | model interi |
| stringhe di ricerca e filtro | collezioni di risultati |
| valori di form | dati non visibili all'utente |
| stati di interfaccia (`$showModal`) | credenziali, token |
| enum | oggetti di dominio complessi |

---

## Metodi

```php
public function save(RegisterMovementAction $action): void
{
    $this->authorize('create', StockMovement::class);      // R2

    $validated = $this->validate([
        'quantity' => ['required', 'numeric', 'min:0.001'],
    ]);

    $action->execute(MovementData::fromArray([              // R1
        ...$validated,
        'batch_id' => $this->batchId,
        'operator_id' => auth()->id(),
    ]));

    $this->dispatch('movement-registered');
}
```

**R9.** I metodi che non devono essere raggiungibili dal client sono `protected` o `private`.
*Verifica:* revisione.

**R10.** Gli eventi Livewire hanno nomi in `kebab-case`, documentati nel componente.
*Verifica:* revisione.

---

## Prestazioni

**R11.** `wire:model.live` solo dove l'aggiornamento immediato è necessario; altrimenti `blur` o
`debounce`.

```html
<input wire:model.live.debounce.300ms="query">   <!-- ✓ -->
<input wire:model.live="query">                  <!-- ✗ una richiesta per carattere -->
```

*Verifica:* revisione.

**R12.** Le collezioni sono paginate.
*Verifica:* revisione.

**R13.** `wire:key` su ogni elemento di un ciclo.
*Motivo:* senza, il DOM si aggiorna in modo errato dopo un riordino.
*Verifica:* revisione.

**R14.** `wire:loading` sulle azioni che superano i 300 ms.
*Verifica:* revisione.

**R15.** Nessuna query nel corpo della vista.
*Verifica:* conteggio delle query nei test.

---

## Esempi

### Esempio 1 — componente conforme

```php
final class SupplierSearch extends Component
{
    #[Url(as: 'q')]
    public string $query = '';

    public int $perPage = 25;

    public function updatedQuery(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Supplier::class);

        return view('livewire.supplier-search', [
            'suppliers' => Supplier::query()
                ->select(['id', 'name', 'vat_number', 'status'])
                ->when($this->query !== '', fn (Builder $q) => $q->search($this->query))
                ->orderBy('name')
                ->paginate($this->perPage),
        ]);
    }
}
```

### Esempio 2 — violazioni

```php
final class SupplierEditor extends Component
{
    public Supplier $supplier;             // ✗ R3, R4: dati interni al browser
    public Collection $allSuppliers;       // ✗ R5: serializzata ad ogni richiesta

    public function delete(int $id): void  // ✗ R2: nessuna autorizzazione
    {
        Supplier::find($id)->delete();     // ✗ R1: logica nel componente
    }
}
```

---

## Best practice

- Trattare ogni metodo pubblico come un endpoint: autorizzarlo.
- Trattare ogni proprietà pubblica come un dato pubblicato: verificare cosa contiene.
- Usare Alpine per lo stato puramente visivo, Livewire per i dati.
- Verificare il numero di richieste durante la digitazione con gli strumenti del browser.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Metodo pubblico senza autorizzazione | Endpoint aperto | Autorizzazione esplicita |
| Model in proprietà pubblica | Dati interni esposti nel browser | Solo i campi necessari |
| Collezione in proprietà pubblica | Serializzazione continua, lentezza | Query in `render()` |
| Logica di business nel componente | Non riutilizzabile, non testabile | Delegare all'Action |
| `wire:model.live` su ogni campo | Una richiesta per carattere | `debounce` o `blur` |
| `wire:key` mancante nei cicli | Aggiornamenti errati del DOM | Chiave sempre |
| Livewire per stato visivo | Richieste inutili | Alpine |

---

## Checklist

- [ ] Nessuna logica di business nel componente.
- [ ] Ogni metodo pubblico autorizza.
- [ ] Nessun dato sensibile nelle proprietà pubbliche.
- [ ] Proprietà pubbliche con tipi semplici.
- [ ] Query in `render()` o in proprietà calcolate.
- [ ] Collezioni paginate.
- [ ] `wire:key` su ogni ciclo, `wire:loading` sulle azioni lente.
- [ ] `wire:model.live` solo dove serve.
- [ ] Tutte le stringhe da `__()`.

---

## Riferimenti

- [Sviluppare il frontend](../docs/03-development/06-frontend-development-guide.md)
- [Frontend](frontend.md) · [Alpine](alpine.md) · [Sicurezza](security.md) · [Policies](policies.md)
- [Template componente Livewire](../templates/frontend/README.md)
