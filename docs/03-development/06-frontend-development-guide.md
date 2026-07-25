# Sviluppare il frontend

> Livewire, Tailwind, Alpine e Vite nei progetti della Factory: landing page, portali pubblici e
> componenti interattivi fuori da Filament.

---

## Indice

1. [Descrizione](#descrizione)
2. [Che cosa è frontend in questi progetti](#che-cosa-è-frontend-in-questi-progetti)
3. [Livewire](#livewire)
4. [Alpine.js](#alpinejs)
5. [Tailwind CSS](#tailwind-css)
6. [Vite e asset](#vite-e-asset)
7. [Landing page e CMS](#landing-page-e-cms)
8. [Accessibilità](#accessibilità)
9. [Prestazioni](#prestazioni)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Nei progetti della Factory la maggior parte dell'interfaccia è generata da Filament. Il frontend
«a mano» copre tre casi:

1. la **landing page** pubblica e le pagine di contenuto (CMS);
2. i **portali** rivolti agli utenti finali del cliente, dove Filament sarebbe eccessivo;
3. i **componenti interattivi** specifici, dentro o fuori Filament.

In tutti e tre i casi lo stack è lo stesso di Filament — Livewire, Tailwind, Alpine — perché
introdurre un secondo paradigma frontend significa mantenerne due per anni.

---

## Che cosa è frontend in questi progetti

| Serve | Strumento | Non usare |
|---|---|---|
| Pagina statica o quasi | Blade | Livewire |
| Interazione con il server | Livewire | fetch a mano |
| Interazione solo client (toggle, dropdown, tab) | Alpine | Livewire |
| Stile | Tailwind | CSS personalizzato |
| Grafici | libreria JS minimale via Vite | soluzioni server-side |
| Aggiornamenti in tempo reale | Livewire polling o broadcasting | WebSocket artigianali |

La domanda di partenza è sempre: *serve il server per questa interazione?* Se no, è Alpine. Se sì,
è Livewire.

---

## Livewire

```php
final class SupplierSearch extends Component
{
    #[Url(as: 'q')]
    public string $query = '';

    public int $perPage = 25;

    public function render(): View
    {
        return view('livewire.supplier-search', [
            'suppliers' => Supplier::query()
                ->when($this->query !== '', fn (Builder $q) => $q->search($this->query))
                ->orderBy('name')
                ->paginate($this->perPage),
        ]);
    }
}
```

| Regola | Motivo |
|---|---|
| Nessuna logica di business nel componente | vale la stessa regola di Filament: delegare alle Action |
| Le proprietà pubbliche sono dati, non oggetti complessi | vengono serializzate ad ogni richiesta |
| Le query stanno in `render()` o in metodi computati | evita stati incoerenti |
| `wire:model.live` solo dove serve davvero | ogni digitazione è una richiesta al server |
| Autorizzazione esplicita nei metodi pubblici | ogni metodo pubblico è un endpoint |
| Nessun dato sensibile nelle proprietà pubbliche | vengono inviate al client |

L'ultimo punto è la fonte più comune di incidenti: una proprietà pubblica di un componente
Livewire è, a tutti gli effetti, un dato pubblicato nel browser dell'utente.

---

## Alpine.js

Per l'interattività che non richiede il server.

```html
<div x-data="{ open: false }">
    <button
        @click="open = !open"
        :aria-expanded="open"
        aria-controls="details"
        class="btn btn-secondary"
    >
        {{ __('common.details') }}
    </button>

    <div x-show="open" x-collapse id="details" class="mt-2">
        {{ $slot }}
    </div>
</div>
```

| Regola | Motivo |
|---|---|
| Alpine per lo stato dell'interfaccia, Livewire per i dati | ognuno il suo compito |
| Logica oltre le ~15 righe in un file JS separato | `x-data` non è un posto dove scrivere programmi |
| Nessuna chiamata HTTP diretta da Alpine | passa da Livewire, che porta autorizzazione e contesto |
| Attributi ARIA aggiornati insieme allo stato | senza, i componenti interattivi non sono accessibili |

---

## Tailwind CSS

**Utility first, senza eccezioni negoziabili.** Il CSS personalizzato è ammesso solo per ciò che
Tailwind non copre (animazioni complesse, stampa).

```html
<!-- ✗ Classe personalizzata che duplica utility esistenti -->
<div class="card-fornitore">…</div>

<!-- ✓ Utility, con componente Blade per il riuso -->
<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">…</div>
```

Il riuso si ottiene con i **componenti Blade**, non con classi CSS: `<x-card>` incapsula le
utility e resta un solo posto da modificare.

| Regola | Motivo |
|---|---|
| Riuso tramite componenti Blade | un solo punto di modifica |
| `@apply` solo in casi eccezionali e motivati | reintroduce il problema che Tailwind risolve |
| Colori dal tema, mai valori arbitrari | coerenza tra i prodotti |
| Modalità scura considerata da subito | aggiungerla dopo costa il triplo |
| Ordine delle classi automatico | plugin Prettier, non disciplina |

---

## Vite e asset

```js
// vite.config.js
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
```

| Regola | Motivo |
|---|---|
| Nessun asset da CDN esterni | disponibilità, privacy, riproducibilità |
| Immagini ottimizzate e in formati moderni | prestazioni percepite |
| `@vite` nel layout, mai percorsi a mano | versionamento automatico |
| Nessun JS inline oltre poche righe | Content Security Policy |
| Build in CI, artefatti versionati nell'immagine | riproducibilità del deploy |

---

## Landing page e CMS

La landing page vive nel **contesto landlord** e presenta il prodotto; il CMS gestisce i suoi
contenuti.

| Elemento | Nota |
|---|---|
| Contenuti | dal modulo `cms`, non scritti nelle viste |
| SEO | meta tag, dati strutturati, sitemap, canonical |
| Prestazioni | pagina statica quando possibile, cache HTTP |
| Moduli di contatto | protetti da limitazione del traffico e verifica anti-abuso |
| Analitica | conforme al GDPR, senza cookie non necessari |
| Multilingua | percorsi separati per lingua |

Le pagine pubbliche sono l'unica parte dell'applicazione che chiunque può raggiungere: sono
anche la superficie più esposta, e vanno trattate come tale.

---

## Accessibilità

Requisito minimo: **WCAG 2.1 livello AA**. Nei progetti destinati alla pubblica amministrazione
è un obbligo di legge, non una buona pratica.

| Requisito | Verifica |
|---|---|
| Contrasto ≥ 4.5:1 sul testo normale | strumento di verifica automatica |
| Ogni immagine ha `alt` significativo | revisione |
| Ogni campo ha `label` associata | revisione |
| Navigazione completa da tastiera | prova manuale |
| Focus sempre visibile | prova manuale |
| ARIA aggiornata insieme allo stato | prova con screen reader |
| Nessuna informazione veicolata dal solo colore | revisione |
| Struttura di heading coerente | revisione |

---

## Prestazioni

| Obiettivo | Valore |
|---|---|
| Largest Contentful Paint | < 2,5 s |
| Interaction to Next Paint | < 200 ms |
| Cumulative Layout Shift | < 0,1 |
| Peso JS iniziale | < 150 KB compressi |
| Richieste per pagina | < 30 |

Interventi ricorrenti: caricamento differito delle immagini, `wire:model.live.debounce` invece di
`live`, paginazione al posto degli elenchi completi, cache HTTP sulle pagine pubbliche.

---

## Esempi

### Esempio 1 — scegliere lo strumento giusto

Un pannello a fisarmonica: stato puramente visivo, nessun dato dal server → **Alpine**.

Un filtro che ricarica un elenco dal database → **Livewire**.

Una pagina «chi siamo» → **Blade** con contenuto dal CMS.

### Esempio 2 — dato sensibile esposto

```php
// ✗ La proprietà pubblica viene inviata al browser
public Supplier $supplier;   // include note interne, margini, valutazioni

// ✓ Solo ciò che serve alla vista
public int $supplierId;
public string $supplierName;
```

---

## Best practice

- Alpine per lo stato dell'interfaccia, Livewire per i dati, Blade per il resto.
- Riuso tramite componenti Blade, non classi CSS.
- Modalità scura fin dall'inizio.
- Accessibilità verificata durante lo sviluppo, non prima del rilascio.
- Nessun asset esterno.
- Autorizzare esplicitamente ogni metodo pubblico di un componente Livewire.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Livewire per interazioni puramente client | Richieste inutili, interfaccia lenta | Alpine |
| Dati sensibili in proprietà pubbliche | Esposizione nel browser | Solo i dati necessari |
| Metodo pubblico senza autorizzazione | Endpoint aperto | Autorizzazione esplicita |
| CSS personalizzato che duplica utility | Due sistemi di stile | Componenti Blade |
| Asset da CDN | Dipendenza esterna, tracciamento | Asset locali via Vite |
| Accessibilità rimandata | Rifacimento dell'interfaccia | Verifiche durante lo sviluppo |
| `wire:model.live` su ogni campo | Una richiesta per digitazione | `debounce` o `blur` |

---

## Checklist

- [ ] Lo strumento scelto corrisponde al tipo di interazione.
- [ ] Nessuna logica di business nei componenti Livewire.
- [ ] Nessun dato sensibile nelle proprietà pubbliche.
- [ ] Ogni metodo pubblico Livewire autorizza.
- [ ] Riuso tramite componenti Blade.
- [ ] Modalità scura supportata.
- [ ] Contrasto, `alt`, `label`, focus e navigazione da tastiera verificati.
- [ ] Nessun asset esterno.
- [ ] Metriche di prestazione entro gli obiettivi.

---

## Riferimenti

- [Regole frontend](../../rules/frontend.md) · [Tailwind](../../rules/tailwind.md) · [Alpine](../../rules/alpine.md)
- [Livewire](../../rules/livewire.md) · [Accessibilità](../../rules/accessibility.md)
- [CMS e landing page](../../architecture/17-cms-landing.md)
- [Checklist frontend](../../checklists/frontend-checklist.md)
