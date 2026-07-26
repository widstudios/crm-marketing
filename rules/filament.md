# Regole — Filament

> Una Resource dichiara un'interfaccia, non implementa un comportamento.

---

## Indice

1. [Descrizione](#descrizione)
2. [Pannelli](#pannelli)
3. [Resource](#resource)
4. [Form](#form)
5. [Tabelle](#tabelle)
6. [Azioni](#azioni)
7. [Widget](#widget)
8. [Autorizzazione](#autorizzazione)
9. [Prestazioni](#prestazioni)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Filament fa risparmiare settimane e per questo attira dentro di sé cose che non gli appartengono. Il
rischio non è tecnico ma architetturale: la logica di business finisce nelle Resource, dove non è
riutilizzabile da API e code, e dove è difficile da testare.

---

## Pannelli

**R1.** Un pannello per contesto di autorizzazione: Super Admin (`landlord`), Tenant Admin
(`tenant`), portale operativo (`tenant`).
*Motivo:* un pannello unico con menu nascosti prima o poi lascia visibile qualcosa a chi non deve
vederlo. *Verifica:* configurazione. *Livello: vincolante.*

**R2.** Ogni pannello dichiara la guardia corretta e, per i pannelli tenant, il middleware di
risoluzione.
*Verifica:* configurazione, test.

**R3.** Il pannello Super Admin non accede ai dati dei tenant se non tramite la procedura tracciata.
*Verifica:* revisione.

---

## Resource

**R4.** Una Resource contiene solo: `form()`, `table()`, `getRelations()`, `getPages()`,
`getEloquentQuery()`, etichette e navigazione.
*Verifica:* revisione.

**R5.** Nessuna logica di business nella Resource.
*Motivo:* non riutilizzabile da API, CLI e code; non testabile in isolamento.
*Verifica:* revisione. *Livello: vincolante.*

**R6.** Ogni etichetta passa da `__()`.
*Verifica:* ricerca in CI su stringhe letterali nelle Resource.

**R7.** Nessun filtro sul tenant dentro la Resource.
*Motivo:* se serve, il middleware non sta funzionando: quello è il problema da risolvere.
*Verifica:* revisione.

---

## Form

**R8.** La validazione del form replica quella del dominio, non la sostituisce.
*Verifica:* revisione.

**R9.** I campi condizionali usano `visible()`, `disabled()`, `required()` con closure sullo stato.
*Verifica:* revisione.

**R10.** I campi calcolati sono in sola lettura: il calcolo sta nel dominio.
*Verifica:* revisione.

**R11.** Le select con molte opzioni usano `getSearchResultsUsing()`, non il caricamento completo.
*Verifica:* revisione, prova con dati realistici.

**R12.** Le sezioni raggruppano per concetto di dominio, non per estetica.
*Verifica:* revisione.

---

## Tabelle

**R13.** Le colonne calcolate su relazioni usano `counts()`, `sum()`, `exists()`, mai un accessor
che esegue una query per riga.
*Verifica:* test sul numero di query.

**R14.** `getEloquentQuery()` dichiara l'eager loading delle relazioni usate dalle colonne.
*Verifica:* test sul numero di query. *Livello: vincolante.*

**R15.** Le colonne pesanti sono `toggleable(isToggledHiddenByDefault: true)`.
*Verifica:* revisione.

**R16.** I filtri e la ricerca agiscono su colonne **indicizzate**.
*Verifica:* revisione con lo schema alla mano.

**R17.** Ogni tabella ha un ordinamento predefinito esplicito.
*Verifica:* revisione.

---

## Azioni

**R18.** Ogni azione che modifica lo stato **delega** a un'Action del dominio.
*Verifica:* revisione. *Livello: vincolante.*

**R19.** Ogni azione personalizzata ha `->authorize()`.
*Motivo:* non eredita alcun controllo: senza, è accessibile a chiunque veda la pagina.
*Verifica:* script di verifica. *Livello: vincolante.*

**R20.** Le azioni distruttive hanno `requiresConfirmation()`.
*Verifica:* revisione.

**R21.** La visibilità dell'azione riflette lo stato: `visible()` con la transizione ammessa
dall'enum.
*Verifica:* revisione.

**R22.** Il corpo di `->action()` fa tre cose: invoca, notifica, eventualmente reindirizza. Nessun
`if` su regole di dominio.
*Verifica:* revisione.

**R23.** Le azioni massive (`BulkAction`) elaborano a blocchi e sono autorizzate come le altre.
*Verifica:* revisione.

---

## Widget

**R24.** Ogni widget usa una cache con chiave **tenant-scoped**.
*Motivo:* i widget si aprono ad ogni caricamento della dashboard; senza prefisso, un tenant vede i
numeri di un altro. *Verifica:* revisione, test di isolamento. *Livello: assoluto.*

**R25.** Le query dei widget sono aggregate, non iterative.
*Verifica:* test sul numero di query della dashboard.

**R26.** I widget di tipo tabella hanno sempre un limite.
*Verifica:* revisione.

**R27.** I grafici limitano l'intervallo temporale.
*Verifica:* revisione.

---

## Autorizzazione

| Elemento | Autorizzazione |
|---|---|
| Visibilità nel menu | Policy `viewAny` (automatica) |
| Elenco, dettaglio, creazione, modifica, cancellazione | Policy (automatica) |
| Azione personalizzata | `->authorize()` **esplicito** |
| Azione massiva | `->authorize()` **esplicito** |
| Widget | verifica nel metodo `canView()` |
| Pagina personalizzata | verifica in `mount()` |

---

## Prestazioni

| Sintomo | Causa | Rimedio |
|---|---|---|
| Elenco lento | N+1 su colonne di relazione | `with()` in `getEloquentQuery()` |
| Dashboard lenta | widget senza cache | cache tenant-scoped |
| Filtro lento | colonna non indicizzata | indice o rimozione del filtro |
| Ricerca lenta | `searchable()` su colonna non indicizzata | indice |
| Form lento | opzioni di select caricate tutte | `getSearchResultsUsing()` |

---

## Esempi

### Esempio 1 — azione conforme

```php
Action::make('archive')
    ->label(__('suppliers.action.archive'))
    ->icon('heroicon-o-archive-box')
    ->requiresConfirmation()
    ->visible(fn (Supplier $r): bool => $r->status->canTransitionTo(SupplierStatus::Archived))
    ->authorize(fn (Supplier $r): bool => auth()->user()->can('archive', $r))
    ->action(function (Supplier $record): void {
        try {
            app(ArchiveSupplierAction::class)->execute($record);
            Notification::make()->success()->title(__('suppliers.notification.archived'))->send();
        } catch (SupplierHasRecentMovements) {
            Notification::make()->danger()->title(__('suppliers.error.recent_movements'))->send();
        }
    });
```

### Esempio 2 — logica nel posto sbagliato

```php
// ✗ R5, R18, R22: la regola non vale per API e importazioni
->action(function (Supplier $record): void {
    if ($record->movements()->where('created_at', '>', now()->subYear())->exists()) {
        Notification::make()->danger()->title('Non archiviabile')->send();
        return;
    }
    $record->update(['status' => 'archived']);
})
```

---

## Best practice

- Partire dal template della Resource.
- Provare gli elenchi con dati realistici, non con dieci righe.
- Contare le query della dashboard: è la pagina più caricata.
- Tenere le Resource brevi: se crescono, la logica sta rientrando.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Logica di business nella Resource | Non riutilizzabile, non testabile | Delegare all'Action |
| Azione senza `authorize()` | Aperta a chiunque veda la pagina | Autorizzazione esplicita |
| Etichette scritte direttamente | Seconda lingua impossibile | `__()` |
| Widget senza cache tenant-scoped | Dashboard lenta, o dati di altri tenant | Cache con prefisso |
| Accessor con query nelle colonne | N+1 per riga | `counts()`, `sum()`, `with()` |
| Filtro su colonna non indicizzata | Elenco lentissimo | Indice |
| Filtro per tenant nella Resource | Sintomo di middleware non funzionante | Correggere la risoluzione |
| Pannello unico con menu condizionali | Prima o poi qualcosa resta visibile | Pannelli separati |

---

## Checklist

- [ ] Un pannello per contesto, con guardia e middleware corretti.
- [ ] Nessuna logica di business nelle Resource.
- [ ] Tutte le etichette da `__()`.
- [ ] Ogni azione di modifica delega a un'Action.
- [ ] Ogni azione personalizzata e massiva ha `->authorize()`.
- [ ] Azioni distruttive con conferma.
- [ ] Eager loading dichiarato in `getEloquentQuery()`.
- [ ] Nessun accessor con query nelle colonne.
- [ ] Filtri e ricerca su colonne indicizzate.
- [ ] Widget con cache tenant-scoped e query aggregate.
- [ ] Test di feature sulle azioni personalizzate.

---

## Riferimenti

- [Sviluppare con Filament](../docs/03-development/05-filament-development-guide.md)
- [Action Pattern](action-pattern.md) · [Policies](policies.md) · [Cache](cache.md) · [UI](ui.md)
- [Template Filament](../templates/filament/README.md)
- [Checklist Filament](../checklists/filament-checklist.md)
