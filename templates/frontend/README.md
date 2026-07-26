# Templates — Frontend

> Gli stub della superficie pubblica: componenti Livewire, componenti Blade, build degli asset.

---

## Indice

1. [Descrizione](#descrizione) 2. [Gli stub](#gli-stub) 3. [Dove vanno i file](#dove-vanno-i-file)
4. [Livewire o Blade](#livewire-o-blade) 5. [Esempi](#esempi) 6. [Best practice](#best-practice)
7. [Errori comuni](#errori-comuni) 8. [Checklist](#checklist) 9. [Riferimenti](#riferimenti)

---

## Descrizione

Il frontend di un gestionale multitenant ha due superfici con esigenze opposte: il **pannello**, che
può essere pesante perché lo usano persone formate su sessioni lunghe, e la **parte pubblica** —
landing page, CMS, portale clienti — che deve caricare in fretta su connessioni qualunque.

Trattarle come una cosa sola produce il difetto più comune di questa area: la landing page che
scarica il bundle del pannello.

---

## Gli stub

| Stub | Quando | Vincolo che conta |
|---|---|---|
| [LivewireComponent.php.stub](LivewireComponent.php.stub) | serve reattività lato server | `#[Locked]` sugli id, autorizzazione in ogni metodo pubblico |
| [BladeComponent.php.stub](BladeComponent.php.stub) | serve riuso di markup | nessuna query, nessun servizio |
| [vite.config.js.stub](vite.config.js.stub) | build degli asset | bundle separati, hash nei nomi |

---

## Dove vanno i file

```
app/
├── Livewire/{{ Module }}/{{ Class }}.php    LivewireComponent.php.stub
└── View/Components/{{ Class }}.php          BladeComponent.php.stub

resources/views/
├── livewire/{{ module }}/…
└── components/{{ module }}/…

vite.config.js                               vite.config.js.stub
```

---

## Livewire o Blade

| Domanda | Livewire | Blade |
|---|---|---|
| Ha stato lato server? | sì | no |
| Risponde a interazioni? | sì | no |
| Costo per istanza | una richiesta per interazione | nessuno |
| In un elenco di 50 righe | 50 componenti con stato | markup |

Il criterio: **serve reattività**, o serve solo riuso di markup? Un componente Livewire usato per
riuso di markup dentro un elenco produce cinquanta componenti con stato serializzato, e il rallentamento
non è attribuibile a nessuna riga in particolare.

---

## Esempi

### Il difetto più costoso di Livewire

```php
// ✗ L'utente cambia batchId dal client e lavora su un lotto non suo
public int $batchId;

// ✓
#[Locked]
public int $batchId;
```

`#[Locked]` non è un'ottimizzazione: senza, ogni identificatore in una proprietà pubblica è un
parametro sotto il controllo di chi usa la pagina.

### Autorizzazione in due punti, non uno

```php
public function mount(int $batchId): void
{
    $this->authorize('view', Batch::findOrFail($batchId));   // caricamento
    $this->batchId = $batchId;
}

public function archive(): void
{
    $batch = Batch::findOrFail($this->batchId);
    $this->authorize('update', $batch);                       // mutazione
    // …
}
```

La verifica in `mount()` non copre `archive()`: il client può invocare il metodo direttamente, senza
passare dall'interfaccia.

---

## Best practice

- `#[Locked]` su ogni identificatore in una proprietà pubblica.
- Autorizzare in `mount()` **e** in ogni metodo pubblico che muta.
- `#[Computed]` per i dati derivati: non entrano nello stato serializzato.
- `wire:model.live` solo dove serve la reattività a ogni tasto; altrove `.blur` o `.live.debounce`.
- Bundle separati per pannello e parte pubblica.
- Immagini caricate dagli utenti ridimensionate lato server, mai con `width` nel markup.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Identificatore senza `#[Locked]` | L'utente lavora su risorse non sue | `#[Locked]` |
| Metodo pubblico non autorizzato | Invocabile dal client con parametri arbitrari | `authorize()` |
| Autorizzazione solo in `mount()` | Le mutazioni restano scoperte | Anche nei metodi |
| Collezione in proprietà pubblica | Viaggia a ogni interazione | `#[Computed]` |
| Livewire per riuso di markup | Cinquanta componenti con stato in un elenco | Blade |
| Query dentro un componente Blade | Una query per ogni comparsa | Il chiamante passa i dati |
| `wire:model.live` ovunque | Una richiesta per ogni tasto premuto | `.blur` o `.debounce` |
| Bundle unico | La landing scarica il codice del pannello | Input separati |
| `sourcemap: true` in produzione | Struttura del codice esposta | `false` |

---

## Checklist

- [ ] Ogni identificatore pubblico è `#[Locked]`.
- [ ] Ogni metodo pubblico che muta autorizza esplicitamente.
- [ ] I dati derivati sono `#[Computed]`.
- [ ] Nessuna query nei componenti Blade.
- [ ] Pannello e parte pubblica hanno bundle separati.
- [ ] Gli asset di produzione hanno hash nel nome e nessuna sourcemap.

---

## Riferimenti

- [Livewire](../../rules/livewire.md) · [Frontend](../../rules/frontend.md) · [Tailwind](../../rules/tailwind.md) · [Alpine](../../rules/alpine.md)
- [Accessibilità](../../rules/accessibility.md) · [UI](../../rules/ui.md) · [UX](../../rules/ux.md)
- [Frontend Agent](../../agents/05-frontend-agent.md) · [Checklist frontend](../../checklists/frontend-checklist.md)
