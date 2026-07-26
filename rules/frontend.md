# Regole — Frontend

> Struttura, asset, componenti e scelta dello strumento giusto per ogni tipo di interazione.

---

## Indice

1. [Descrizione](#descrizione)
2. [Scelta dello strumento](#scelta-dello-strumento)
3. [Componenti Blade](#componenti-blade)
4. [Asset e build](#asset-e-build)
5. [Viste](#viste)
6. [Prestazioni](#prestazioni)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Lo stack frontend è quello di Filament: Livewire, Tailwind, Alpine. Introdurne un secondo
significherebbe mantenerne due per anni, con un team piccolo.

Queste regole valgono per il frontend scritto a mano: landing page, portali, componenti specifici.

---

## Scelta dello strumento

**R1.** La domanda di partenza è: *serve il server per questa interazione?*

| Interazione | Strumento |
|---|---|
| Pagina statica o quasi | Blade |
| Interazione con il server | Livewire |
| Solo client (toggle, dropdown, tab, modale) | Alpine |
| Stile | Tailwind |
| Grafici | libreria minimale via Vite |
| Tempo reale | Livewire polling o broadcasting |

*Verifica:* revisione.

**R2.** Nessun framework JS SPA (React, Vue, Inertia) senza ADR di progetto.
*Motivo:* seconda architettura frontend da mantenere. *Verifica:* dipendenze. *Livello: vincolante.*

---

## Componenti Blade

**R3.** Il riuso visivo si ottiene con componenti Blade, non con classi CSS personalizzate.
*Verifica:* revisione.

**R4.** Ogni componente ha proprietà tipizzate e documentate.

```php
final class StatusBadge extends Component
{
    public function __construct(
        public SupplierStatus $status,
        public string $size = 'md',
    ) {}
}
```

*Verifica:* revisione.

**R5.** I componenti non eseguono query.
*Verifica:* conteggio delle query nei test.

**R6.** Ogni stringa visibile passa da `__()`.
*Verifica:* ricerca in CI su testo letterale nelle viste. *Livello: vincolante.*

---

## Asset e build

**R7.** Nessun asset da CDN esterni: tutto locale, via Vite.
*Motivo:* disponibilità, privacy, riproducibilità del build. *Verifica:* revisione, CSP.
*Livello: vincolante.*

**R8.** Gli asset si includono con `@vite`, mai con percorsi scritti a mano.
*Motivo:* il versionamento è automatico. *Verifica:* revisione.

**R9.** Nessun JavaScript inline oltre poche righe.
*Motivo:* incompatibile con una CSP restrittiva. *Verifica:* verifica della CSP.

**R10.** Le immagini sono ottimizzate, dimensionate e in formati moderni con ripiego.
*Verifica:* revisione, metriche di prestazione.

**R11.** Il build degli asset avviene in CI e l'esito entra nell'immagine.
*Verifica:* pipeline.

---

## Viste

**R12.** Nessuna query nelle viste.
*Verifica:* conteggio delle query.

**R13.** Nessuna logica di business nelle viste: solo presentazione e condizioni di visualizzazione.
*Verifica:* revisione.

**R14.** I dati arrivano alla vista già formattati per la presentazione, o tramite componenti che se
ne occupano.
*Verifica:* revisione.

**R15.** Le viste usano i layout condivisi; nessun HTML di struttura duplicato.
*Verifica:* revisione.

---

## Prestazioni

| Obiettivo | Valore |
|---|---|
| Largest Contentful Paint | < 2,5 s |
| Interaction to Next Paint | < 200 ms |
| Cumulative Layout Shift | < 0,1 |
| Peso JS iniziale | < 150 KB compressi |
| Richieste per pagina | < 30 |

**R16.** Le immagini sotto la piega usano il caricamento differito.
*Verifica:* revisione.

**R17.** Le pagine pubbliche hanno cache HTTP.
*Verifica:* verifica degli header.

---

## Esempi

### Esempio 1 — scelta corretta dello strumento

Pannello a fisarmonica (stato visivo) → **Alpine**.
Filtro che ricarica un elenco → **Livewire**.
Pagina «chi siamo» → **Blade** con contenuto dal CMS.

### Esempio 2 — componente riusabile

```blade
{{-- resources/views/components/status-badge.blade.php --}}
@props(['status', 'size' => 'md'])

<span class="inline-flex items-center rounded-full font-medium
    {{ $size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm' }}
    {{ match($status) {
        SupplierStatus::Active => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        SupplierStatus::Suspended => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        SupplierStatus::Archived => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    } }}">
    {{ $status->label() }}
</span>
```

Usato come `<x-status-badge :status="$supplier->status" />`: un solo punto da modificare.

---

## Best practice

- Alpine per lo stato dell'interfaccia, Livewire per i dati, Blade per il resto.
- Componenti Blade per ogni elemento visivo che compare più di due volte.
- Modalità scura considerata da subito.
- Verificare le metriche di prestazione su una connessione lenta simulata.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Livewire per interazioni client | Richieste inutili, interfaccia lenta | Alpine |
| Asset da CDN | Dipendenza esterna, tracciamento, CSP violata | Asset locali |
| Testo scritto nelle viste | Seconda lingua impossibile | `__()` |
| Query nelle viste | N+1 invisibili | Dati dal controller |
| CSS personalizzato che duplica utility | Due sistemi di stile | Componenti Blade |
| Percorsi di asset scritti a mano | Cache non invalidata al rilascio | `@vite` |
| Immagini non ottimizzate | LCP oltre soglia | Formati moderni, dimensioni corrette |

---

## Checklist

- [ ] Lo strumento corrisponde al tipo di interazione.
- [ ] Nessun framework SPA senza ADR.
- [ ] Riuso tramite componenti Blade con proprietà tipizzate.
- [ ] Nessuna query nelle viste o nei componenti.
- [ ] Tutte le stringhe da `__()`.
- [ ] Nessun asset esterno; inclusione con `@vite`.
- [ ] Immagini ottimizzate e differite sotto la piega.
- [ ] Metriche di prestazione entro gli obiettivi.

---

## Riferimenti

- [Sviluppare il frontend](../docs/03-development/06-frontend-development-guide.md)
- [Tailwind](tailwind.md) · [Alpine](alpine.md) · [Livewire](livewire.md) · [UI](ui.md) · [Accessibilità](accessibility.md)
- [Template frontend](../templates/frontend/README.md)
