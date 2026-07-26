# Templates — Filament

> Gli stub del pannello di gestione: Resource, Widget, pagine personalizzate.

---

## Indice

1. [Descrizione](#descrizione) 2. [Gli stub](#gli-stub) 3. [Dove vanno i file](#dove-vanno-i-file)
4. [I tre punti scoperti](#i-tre-punti-scoperti) 5. [Esempi](#esempi) 6. [Best practice](#best-practice)
7. [Errori comuni](#errori-comuni) 8. [Checklist](#checklist) 9. [Riferimenti](#riferimenti)

---

## Descrizione

Filament genera moltissimo, e questa è la sua forza e il suo rischio. Il rischio non è il codice
generato — quello è buono — ma il fatto che generi **abbastanza** da far sembrare inutile
l'architettura: se un Resource sa già fare tutto, perché passare da un'Action?

La risposta è che il Resource è un punto di ingresso fra tanti. Il giorno in cui la stessa
operazione serve all'API, all'importazione o a un job, la logica scritta dentro un'azione Filament
non c'è.

---

## Gli stub

| Stub | Quando | Vincolo che conta |
|---|---|---|
| [FilamentResource.php.stub](FilamentResource.php.stub) | CRUD su un'entità | nessuna logica, azioni con `->authorize()` |
| [FilamentWidget.php.stub](FilamentWidget.php.stub) | numero in dashboard | cache tenant-scoped, `canView()` |
| [FilamentPage.php.stub](FilamentPage.php.stub) | interazione non CRUD | `canAccess()`, metodi pubblici autorizzati |

---

## Dove vanno i file

```
app/Filament/
├── Resources/{{ Entity }}Resource.php          FilamentResource.php.stub
├── Resources/{{ Entity }}Resource/Pages/
├── Widgets/{{ Class }}Widget.php               FilamentWidget.php.stub
└── Pages/{{ Class }}.php                       FilamentPage.php.stub
```

Il pannello del tenant e quello di piattaforma sono **due pannelli distinti**, con provider,
percorsi e guardie separate. Un pannello solo con condizioni al suo interno è un modo per avere
l'interfaccia di amministrazione della piattaforma raggiungibile dai domini dei clienti.

---

## I tre punti scoperti

L'autorizzazione automatica di Filament copre il CRUD. Non copre questi tre, e sono esattamente i
punti dove i difetti si nascondono.

### 1. Azioni personalizzate

```php
Action::make('approve')
    ->authorize(fn (Batch $record): bool => auth()->user()->can('approve', $record))
```

Senza `->authorize()`, l'azione è disponibile a chiunque possa vedere la riga.

### 2. Pagine personalizzate

```php
public static function canAccess(): bool
{
    return auth()->user()?->can('batch.manage') ?? false;
}
```

Non c'è un model, quindi non c'è una Policy che Filament possa invocare da solo.

### 3. Metodi pubblici Livewire

Ogni metodo pubblico di un componente Livewire è **invocabile dal client**, con i parametri che il
client preferisce. Ogni metodo pubblico che muta qualcosa autorizza esplicitamente.

---

## Esempi

### Azione conforme

```php
Action::make('approve')
    ->label('Approva')
    ->icon('heroicon-o-check')
    ->requiresConfirmation()
    ->authorize(fn (Batch $record): bool => auth()->user()->can('approve', $record))
    ->action(fn (Batch $record) => app(ApproveBatchAction::class)
        ->execute(new ApproveBatchData($record->getKey())));
```

Autorizza, conferma, delega. Le tre cose che un'azione deve fare, e le uniche.

### Azione non conforme

```php
Action::make('approve')
    ->action(function (Batch $record): void {
        if ($record->expiry_date < now()) {
            return;                                   // regola di dominio nascosta qui
        }
        $record->update(['status' => 'approved']);    // mutazione diretta
    });
```

Nessuna autorizzazione, una regola che l'API non applicherà, e una mutazione che non passa da
nessuna Action — quindi senza audit, senza evento, senza transazione.

---

## Best practice

- Ogni azione personalizzata: `->authorize()` e delega a un'Action.
- Caricare in anticipo le relazioni usate nelle colonne.
- Filtrare solo su colonne indicizzate.
- Cache tenant-scoped su ogni widget, con invalidazione testata.
- Etichette in italiano, chiavi di traduzione in inglese.
- Azioni massive a blocchi, in coda oltre una soglia dichiarata.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Logica dentro un'azione Filament | L'API e l'importazione non la applicano | Delegare a un'Action |
| Azione senza `->authorize()` | Operazione aperta a chi vede la riga | Sempre presente |
| Pagina senza `canAccess()` | Pagina raggiungibile da tutti | Verifica del permesso |
| Metodo pubblico Livewire non autorizzato | Invocabile dal client con parametri arbitrari | `authorize()` nel metodo |
| Widget senza cache tenant-scoped | Un cliente vede i numeri di un altro | `TenantCacheKey` |
| Widget lento | La dashboard è la pagina più lenta | Cache o precalcolo in coda |
| Relazioni senza eager loading | Una query per riga | `modifyQueryUsing()` con `with()` |
| Filtro su colonna senza indice | Scansione completa a ogni caricamento | Indice, o niente filtro |
| Scope di tenant nella query | Ragionamento sul modello sbagliato | Il tenant è il database |
| Un pannello solo per tenant e piattaforma | Amministrazione raggiungibile dai domini clienti | Due pannelli |

---

## Checklist

- [ ] Nessuna logica di business nei Resource.
- [ ] Ogni azione personalizzata autorizza e delega a un'Action.
- [ ] Ogni pagina personalizzata implementa `canAccess()`.
- [ ] Ogni metodo pubblico Livewire che muta autorizza.
- [ ] I widget usano la cache tenant-scoped e implementano `canView()`.
- [ ] Le relazioni usate nelle colonne sono caricate in anticipo.
- [ ] I filtri agiscono su colonne indicizzate.
- [ ] Pannello tenant e pannello piattaforma sono distinti.

---

## Riferimenti

- [Regole Filament](../../rules/filament.md) · [Livewire](../../rules/livewire.md) · [Policies](../../rules/policies.md)
- [Livello di presentazione](../../architecture/15-presentation-layer.md)
- [Filament Agent](../../agents/07-filament-agent.md) · [Checklist Filament](../../checklists/filament-checklist.md)
- [Guida allo sviluppo Filament](../../docs/03-development/05-filament-development-guide.md)
