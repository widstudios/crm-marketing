# Sviluppare con Filament

> Pannelli, resource, form, tabelle, azioni e widget: come si costruisce l'amministrazione senza
> spostarci dentro la logica di business.

---

## Indice

1. [Descrizione](#descrizione)
2. [I pannelli standard](#i-pannelli-standard)
3. [Anatomia di una Resource](#anatomia-di-una-resource)
4. [Form](#form)
5. [Tabelle](#tabelle)
6. [Azioni](#azioni)
7. [Widget e dashboard](#widget-e-dashboard)
8. [Autorizzazione](#autorizzazione)
9. [Multitenancy in Filament](#multitenancy-in-filament)
10. [Prestazioni](#prestazioni)
11. [Esempi](#esempi)
12. [Best practice](#best-practice)
13. [Errori comuni](#errori-comuni)
14. [Checklist](#checklist)
15. [Riferimenti](#riferimenti)

---

## Descrizione

Filament fa risparmiare settimane sull'amministrazione, e per questo attira dentro di sé cose che
non gli appartengono. Il rischio concreto non è tecnico ma architetturale: la logica di business
finisce nelle Resource, dove non è riutilizzabile da API, comandi e code, e dove è difficile da
testare.

La regola che tiene: **una Resource dichiara un'interfaccia, non implementa un comportamento**.

---

## I pannelli standard

| Pannello | Percorso | Guardia | Utenti | Contenuto |
|---|---|---|---|---|
| Super Admin | `/super-admin` | `landlord` | personale WidStudios | tenant, piani, licenze, monitoraggio, code |
| Tenant Admin | `/admin` | `tenant` | amministratori del cliente | configurazione, utenti, dati di dominio |
| Operativo | `/app` | `tenant` | operatori del cliente | attività quotidiane, vista ridotta |

I pannelli sono **separati** perché hanno modelli di autorizzazione diversi e vivono su
connessioni diverse. Un unico pannello con menu nascosti a seconda del ruolo è l'anti-pattern
classico: prima o poi qualcosa resta visibile a chi non deve vederlo.

```php
final class TenantAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->authGuard('tenant')
            ->middleware([/* … */, ResolveTenant::class])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->colors(['primary' => Color::Blue]);
    }
}
```

---

## Anatomia di una Resource

Una Resource contiene, e nient'altro:

| Elemento | Cosa fa |
|---|---|
| `form()` | dichiara i campi |
| `table()` | dichiara colonne, filtri, azioni |
| `getRelations()` | dichiara le relazioni gestite |
| `getPages()` | dichiara le pagine |
| `getEloquentQuery()` | restringe la query di base |
| etichette e navigazione | traduzioni e collocazione nel menu |

Cosa **non** contiene: calcoli di dominio, transizioni di stato, chiamate a servizi esterni,
scritture su più tabelle. Tutto questo sta nelle Action.

---

## Form

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        Section::make(__('suppliers.section.identity'))
            ->schema([
                TextInput::make('name')
                    ->label(__('suppliers.field.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('vat_number')
                    ->label(__('suppliers.field.vat_number'))
                    ->required()
                    ->rules(['regex:/^IT[0-9]{11}$/'])
                    ->unique(ignoreRecord: true),
            ])
            ->columns(2),
    ]);
}
```

| Regola | Motivo |
|---|---|
| Ogni etichetta passa da `__()` | la seconda lingua arriva sempre |
| La validazione replica quella del dominio | il form è un ingresso, non la fonte della regola |
| I campi condizionali usano `visible()`/`disabled()` con closure | lo stato governa l'interfaccia |
| I campi calcolati sono in sola lettura | il calcolo sta nel dominio |
| Le sezioni raggruppano per concetto | leggibilità, non estetica |

---

## Tabelle

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('vat_number')->searchable()->toggleable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('movements_count')->counts('movements')->label(__('suppliers.field.movements')),
        ])
        ->filters([
            SelectFilter::make('status')->options(SupplierStatus::class),
            Filter::make('expiring')->query(fn (Builder $q) => $q->expiringWithin(30)),
        ])
        ->defaultSort('name')
        ->persistFiltersInSession();
}
```

Attenzione alle prestazioni:

- Le colonne calcolate su relazioni usano `counts()` o `sum()`, non un accessor che esegue una
  query per riga.
- Le colonne che richiedono relazioni usano `->with()` in `getEloquentQuery()`.
- Le colonne pesanti sono `toggleable(isToggledHiddenByDefault: true)`.
- I filtri su colonne non indicizzate vanno evitati o l'indice va aggiunto.

---

## Azioni

Ogni azione che modifica lo stato **delega** a un'Action del dominio.

```php
Action::make('suspend')
    ->label(__('suppliers.action.suspend'))
    ->icon('heroicon-o-pause-circle')
    ->requiresConfirmation()
    ->visible(fn (Supplier $record): bool => $record->status === SupplierStatus::Active)
    ->authorize(fn (Supplier $record): bool => auth()->user()->can('suspend', $record))
    ->form([
        Textarea::make('reason')->label(__('suppliers.field.reason'))->required(),
    ])
    ->action(function (Supplier $record, array $data): void {
        app(SuspendSupplierAction::class)->execute($record, $data['reason']);

        Notification::make()
            ->success()
            ->title(__('suppliers.notification.suspended'))
            ->send();
    });
```

Il corpo di `->action()` fa tre cose: invoca, notifica, eventualmente reindirizza. Se contiene un
`if` su regole di dominio, quella regola è nel posto sbagliato.

---

## Widget e dashboard

| Tipo | Uso | Attenzione |
|---|---|---|
| `StatsOverviewWidget` | indicatori sintetici | query aggregate, con cache |
| `ChartWidget` | andamenti | limitare l'intervallo temporale |
| `TableWidget` | elenchi brevi | sempre con limite |

I widget si aprono ad ogni caricamento della dashboard: sono la causa più frequente di
amministrazioni lente.

```php
final class StockOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $data = cache()->remember(
            TenantCacheKey::for('widget:stock-overview'),
            now()->addMinutes(5),
            fn (): array => app(StockOverviewQuery::class)->execute(),
        );

        return [
            Stat::make(__('stock.total_batches'), $data['batches']),
            Stat::make(__('stock.expiring'), $data['expiring'])
                ->color($data['expiring'] > 0 ? 'danger' : 'success'),
        ];
    }
}
```

La chiave di cache è **tenant-scoped**: senza prefisso, un tenant vedrebbe i numeri di un altro.

---

## Autorizzazione

Filament usa le Policy del progetto: non si duplica la logica di autorizzazione.

| Metodo Filament | Policy |
|---|---|
| visibilità nel menu | `viewAny` |
| elenco | `viewAny` |
| dettaglio | `view` |
| creazione | `create` |
| modifica | `update` |
| cancellazione | `delete` |
| azione personalizzata | `authorize()` esplicito |

Le azioni personalizzate **non** ereditano automaticamente un controllo: se manca `->authorize()`,
sono accessibili a chiunque veda la pagina.

---

## Multitenancy in Filament

Il contesto tenant è già risolto dal middleware quando la Resource viene eseguita: i model
interrogano il database del tenant senza filtri aggiuntivi.

| Non serve | Serve |
|---|---|
| filtrare per `tenant_id` | verificare che il pannello usi la guardia corretta |
| passare il tenant alle query | prefissare le chiavi di cache dei widget |
| controllare l'appartenenza dei record | usare la connessione tenant per gli upload |

L'errore concettuale ricorrente è cercare di «filtrare per tenant» dentro Filament: se ci si trova
a doverlo fare, il middleware non sta funzionando, ed è quello il problema da risolvere.

---

## Prestazioni

| Sintomo | Causa | Rimedio |
|---|---|---|
| Elenco lento | N+1 su colonne di relazione | `getEloquentQuery()` con `with()` |
| Dashboard lenta | widget con query aggregate non cacheate | cache tenant-scoped |
| Filtro lento | colonna non indicizzata | indice o rimozione del filtro |
| Ricerca lenta | `searchable()` su colonna non indicizzata | indice o ricerca dedicata |
| Form lento | opzioni di select caricate tutte | `getSearchResultsUsing()` |

---

## Esempi

### Esempio 1 — logica nel posto sbagliato

```php
// ✗ La regola di dominio finisce nella Resource
->action(function (Supplier $record): void {
    if ($record->movements()->where('created_at', '>', now()->subYear())->exists()) {
        Notification::make()->danger()->title('Non archiviabile')->send();
        return;
    }
    $record->update(['status' => 'archived']);
})
```

```php
// ✓ La regola vive nel dominio, la Resource riporta l'esito
->action(function (Supplier $record): void {
    try {
        app(ArchiveSupplierAction::class)->execute($record);
        Notification::make()->success()->title(__('suppliers.notification.archived'))->send();
    } catch (SupplierHasRecentMovements $e) {
        Notification::make()->danger()->title(__('suppliers.error.recent_movements'))->send();
    }
})
```

Nella versione corretta, la stessa regola vale anche via API e via importazione massiva.

---

## Best practice

- Un pannello per contesto di autorizzazione, mai un pannello unico con menu nascosti.
- Le Resource dichiarano; le Action eseguono.
- Ogni etichetta passa da `__()`.
- Ogni azione personalizzata ha `->authorize()`.
- Ogni widget ha una cache tenant-scoped.
- Verificare il numero di query sugli elenchi con dati realistici, non con dieci righe.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Logica di business nella Resource | Non riutilizzabile, non testabile | Delegare all'Action |
| Azione senza `authorize()` | Accessibile a chiunque veda la pagina | Autorizzazione esplicita |
| Etichette scritte direttamente | Seconda lingua impossibile | `__()` |
| Widget senza cache | Dashboard lenta | Cache tenant-scoped |
| Accessor con query nelle colonne | N+1 su ogni riga | `counts()`, `sum()`, `with()` |
| Filtro per `tenant_id` in Filament | Sintomo di middleware non funzionante | Correggere la risoluzione del tenant |
| Pannello unico con menu condizionali | Prima o poi qualcosa resta visibile | Pannelli separati |

---

## Checklist

- [ ] La Resource non contiene logica di business.
- [ ] Ogni azione di modifica delega a un'Action.
- [ ] Ogni azione personalizzata ha `->authorize()`.
- [ ] Tutte le etichette passano da `__()`.
- [ ] Le colonne di relazione non producono N+1.
- [ ] I widget usano cache con chiave tenant-scoped.
- [ ] I filtri agiscono su colonne indicizzate.
- [ ] Il pannello usa la guardia e il middleware corretti.
- [ ] Test di feature sulle azioni personalizzate.

---

## Riferimenti

- [Regole Filament](../../rules/filament.md) · [UI](../../rules/ui.md) · [UX](../../rules/ux.md)
- [Template Filament Resource](../../templates/filament/README.md)
- [Action Pattern](../../rules/action-pattern.md) · [Policies](../../rules/policies.md)
- [Checklist Filament](../../checklists/filament-checklist.md)
