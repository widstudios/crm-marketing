# Regole — Accessibilità

> WCAG 2.1 livello AA come requisito minimo. Nei progetti per la pubblica amministrazione è un
> obbligo di legge.

---

## Indice

1. [Descrizione](#descrizione)
2. [Requisiti minimi](#requisiti-minimi)
3. [Struttura](#struttura)
4. [Contrasto e colore](#contrasto-e-colore)
5. [Tastiera e focus](#tastiera-e-focus)
6. [Form](#form)
7. [Contenuti dinamici](#contenuti-dinamici)
8. [Verifica](#verifica)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

L'accessibilità non riguarda solo gli utenti con disabilità permanenti: riguarda chi lavora con una
mano occupata, chi usa uno schermo in un magazzino illuminato dal sole, chi non usa il mouse per
velocità.

Aggiungerla dopo costa il triplo e produce risultati parziali: va considerata mentre si sviluppa.

---

## Requisiti minimi

**R1.** Ogni interfaccia rispetta **WCAG 2.1 livello AA**.
*Verifica:* strumento automatico + prova manuale. *Livello: vincolante.*

**R2.** Nei progetti destinati alla pubblica amministrazione, la conformità è dichiarata e
verificata da audit.
*Verifica:* documentazione di conformità.

---

## Struttura

**R3.** Gerarchia di titoli coerente: un solo `h1` per pagina, nessun livello saltato.
*Verifica:* strumento automatico.

**R4.** Elementi semantici corretti: `<button>` per le azioni, `<a>` per la navigazione,
`<table>` per i dati tabellari.
*Motivo:* un `<div>` con `onclick` non è raggiungibile da tastiera né annunciato correttamente.
*Verifica:* revisione, strumento automatico.

**R5.** Regioni identificate: `<nav>`, `<main>`, `<aside>`, `<header>`, `<footer>`.
*Verifica:* strumento automatico.

**R6.** Collegamento «salta al contenuto» come primo elemento focalizzabile.
*Verifica:* prova da tastiera.

**R7.** Ogni pagina ha un titolo univoco e descrittivo.
*Verifica:* revisione.

**R8.** La lingua è dichiarata su `<html>`; i cambi di lingua nel contenuto sono marcati.
*Verifica:* strumento automatico.

---

## Contrasto e colore

**R9.** Contrasto minimo: **4.5:1** sul testo normale, **3:1** su testo grande e componenti di
interfaccia.
*Verifica:* strumento di verifica del contrasto, in entrambe le modalità.

**R10.** Nessuna informazione veicolata dal **solo** colore.
*Verifica:* revisione. *Livello: vincolante.*

**R11.** Il contrasto è verificato sia in modalità chiara sia in modalità scura.
*Verifica:* strumento automatico su entrambe.

**R12.** Il testo si ingrandisce fino al 200% senza perdita di contenuto o funzionalità.
*Verifica:* prova manuale.

---

## Tastiera e focus

**R13.** Ogni funzionalità è utilizzabile **solo da tastiera**.
*Verifica:* prova manuale completa. *Livello: vincolante.*

**R14.** Il focus è sempre visibile, con contrasto adeguato.
*Motivo:* rimuovere l'anello di focus rende l'interfaccia inutilizzabile da tastiera.
*Verifica:* prova manuale.

**R15.** L'ordine di tabulazione segue l'ordine logico del contenuto.
*Verifica:* prova manuale.

**R16.** Nessuna trappola per il focus: da ogni elemento si può uscire.
*Verifica:* prova manuale.

**R17.** Le modali catturano il focus all'apertura e lo restituiscono all'elemento di origine alla
chiusura.
*Verifica:* prova manuale.

**R18.** `Esc` chiude modali e menu a tendina.
*Verifica:* prova manuale.

---

## Form

**R19.** Ogni campo ha una `<label>` associata (`for`/`id`), non solo un segnaposto.
*Motivo:* il segnaposto scompare alla digitazione e non è annunciato da tutti gli screen reader.
*Verifica:* strumento automatico. *Livello: vincolante.*

**R20.** I campi obbligatori sono indicati testualmente, non solo con un asterisco colorato.
*Verifica:* revisione.

**R21.** Gli errori di validazione sono associati al campo con `aria-describedby` e annunciati.
*Verifica:* prova con screen reader.

**R22.** I gruppi di campi correlati usano `<fieldset>` e `<legend>`.
*Verifica:* revisione.

**R23.** Il testo di aiuto è associato al campo, non solo posizionato accanto.
*Verifica:* revisione.

---

## Contenuti dinamici

**R24.** Gli aggiornamenti asincroni sono annunciati con `aria-live`.

```html
<div aria-live="polite" aria-atomic="true" class="sr-only">
    {{ $statusMessage }}
</div>
```

*Verifica:* prova con screen reader.

**R25.** Gli attributi ARIA di stato (`aria-expanded`, `aria-selected`, `aria-busy`) si aggiornano
insieme allo stato visivo.
*Verifica:* revisione, prova con screen reader.

**R26.** Nessun contenuto che si muove, lampeggia o si aggiorna automaticamente senza controllo
dell'utente.
*Verifica:* revisione.

**R27.** Le notifiche temporanee restano visibili almeno 5 secondi e sono richiamabili.
*Verifica:* revisione.

---

## Verifica

| Verifica | Strumento | Frequenza |
|---|---|---|
| Contrasto | strumento automatico | ad ogni componente nuovo |
| Struttura e ARIA | analizzatore automatico in CI | ad ogni build |
| Navigazione da tastiera | prova manuale | ad ogni schermata nuova |
| Screen reader | prova manuale | ad ogni rilascio maggiore |
| Ingrandimento al 200% | prova manuale | ad ogni rilascio maggiore |
| Audit completo | esterno | annuale nei progetti PA |

---

## Esempi

### Esempio 1 — campo conforme

```blade
<div>
    <label for="quantity" class="block text-sm font-medium">
        {{ __('inventory.field.quantity') }}
        <span class="text-danger-600">({{ __('common.required') }})</span>
    </label>

    <input
        type="number"
        id="quantity"
        name="quantity"
        step="0.001"
        min="0.001"
        max="{{ $available }}"
        aria-describedby="quantity-help quantity-error"
        @error('quantity') aria-invalid="true" @enderror
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600"
    >

    <p id="quantity-help" class="mt-1 text-sm text-gray-500">
        {{ __('inventory.help.available', ['n' => $available]) }}
    </p>

    @error('quantity')
        <p id="quantity-error" class="mt-1 text-sm text-danger-600">{{ $message }}</p>
    @enderror
</div>
```

### Esempio 2 — violazioni

```blade
{{-- ✗ R4: div cliccabile, ✗ R13: non raggiungibile da tastiera --}}
<div onclick="save()" class="cursor-pointer bg-primary-600 text-white">Salva</div>

{{-- ✗ R19: solo segnaposto, ✗ R10: obbligatorietà dal solo colore --}}
<input type="text" placeholder="Nome *" class="border-red-500">

{{-- ✗ R14: focus rimosso --}}
<button class="focus:outline-none">Conferma</button>
```

---

## Best practice

- Usare elementi semantici nativi: fanno gratis metà del lavoro di accessibilità.
- Provare ogni schermata nuova solo da tastiera, prima di considerarla finita.
- Verificare il contrasto mentre si scelgono i colori, non dopo.
- Tenere una checklist di accessibilità nella revisione, non come attività separata.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `<div>` cliccabile | Non raggiungibile da tastiera | `<button>` |
| Segnaposto invece di etichetta | Campo non identificabile | `<label>` associata |
| Focus rimosso per estetica | Interfaccia inutilizzabile da tastiera | Anello di focus visibile |
| Informazione dal solo colore | Inaccessibile | Colore + testo/icona |
| Contrasto verificato solo in chiaro | Modalità scura inaccessibile | Verifica su entrambe |
| ARIA non aggiornata | Stato non annunciato | ARIA legata allo stato |
| Modale senza gestione del focus | Utente da tastiera bloccato | Cattura e restituzione |
| Notifica che scompare in 2 secondi | Non leggibile da tutti | Almeno 5 secondi, richiamabile |

---

## Checklist

- [ ] Gerarchia dei titoli coerente, un solo `h1`.
- [ ] Elementi semantici nativi per azioni, navigazione e dati.
- [ ] Regioni identificate; collegamento «salta al contenuto».
- [ ] Contrasto ≥ 4.5:1, verificato in chiaro e in scuro.
- [ ] Nessuna informazione dal solo colore.
- [ ] Ogni funzionalità utilizzabile da tastiera.
- [ ] Focus sempre visibile, ordine logico, nessuna trappola.
- [ ] Modali con cattura e restituzione del focus; `Esc` chiude.
- [ ] Ogni campo con `<label>` associata.
- [ ] Errori associati al campo e annunciati.
- [ ] Aggiornamenti asincroni annunciati con `aria-live`.
- [ ] Testo ingrandibile al 200% senza perdite.

---

## Riferimenti

- [UI](ui.md) · [UX](ux.md) · [Frontend](frontend.md) · [Alpine](alpine.md)
- [Sviluppare il frontend](../docs/03-development/06-frontend-development-guide.md)
- WCAG 2.1: https://www.w3.org/TR/WCAG21/
