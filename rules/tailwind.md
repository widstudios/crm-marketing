# Regole — Tailwind CSS

> Utility first, riuso tramite componenti Blade, tema condiviso, modalità scura da subito.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole](#regole)
3. [Riuso](#riuso)
4. [Tema](#tema)
5. [Modalità scura](#modalità-scura)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Tailwind risolve il problema per cui i fogli di stile crescono senza che nessuno osi rimuovere una
regola. Reintrodurre classi personalizzate per «pulire» il markup reintroduce esattamente quel
problema.

Il riuso si ottiene a livello di **componente**, non di classe CSS.

---

## Regole

**R1.** Utility first: le classi si applicano nel markup.
*Verifica:* revisione.

**R2.** Nessuna classe CSS personalizzata che duplichi utility esistenti.
*Verifica:* revisione del foglio di stile.

**R3.** `@apply` solo in casi eccezionali e motivati (integrazione con librerie di terze parti,
stampa).
*Motivo:* reintroduce il problema che Tailwind risolve. *Verifica:* revisione.

**R4.** CSS personalizzato ammesso solo per ciò che Tailwind non copre: animazioni complesse,
stampa, casi di integrazione.
*Verifica:* revisione.

**R5.** L'ordine delle classi è determinato dal plugin di formattazione, non dalla disciplina.
*Verifica:* verifica in CI.

**R6.** Nessun valore arbitrario per colori e spaziature: solo valori del tema.

```html
<div class="bg-[#3b82f6]">   <!-- ✗ -->
<div class="bg-primary-500"> <!-- ✓ -->
```

*Motivo:* coerenza visiva tra i prodotti. *Verifica:* revisione.

**R7.** Nessuno stile inline (`style="..."`), salvo valori calcolati a runtime.
*Verifica:* revisione, CSP.

---

## Riuso

**R8.** Un elemento visivo che compare più di **due** volte diventa un componente Blade.
*Verifica:* revisione.

```blade
{{-- ✗ Utility ripetute in dieci punti --}}
<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">…</div>

{{-- ✓ Componente --}}
<x-card>…</x-card>
```

**R9.** Le varianti di un componente si esprimono con proprietà, non con classi passate
dall'esterno.
*Verifica:* revisione.

---

## Tema

**R10.** Colori, tipografia e spaziature provengono dal tema condiviso della Factory.
*Verifica:* configurazione.

| Ruolo | Uso |
|---|---|
| `primary` | azioni principali, elementi attivi |
| `secondary` | azioni secondarie |
| `success`, `warning`, `danger`, `info` | stati e riscontri |
| `gray` | testo, bordi, sfondi |

**R11.** I colori si usano per il loro **ruolo semantico**, non per il loro aspetto.
*Motivo:* un cambio di tema non deve richiedere di rileggere tutto il markup.
*Verifica:* revisione.

**R12.** Il tema di Filament e quello del frontend sono coerenti: un solo insieme di colori.
*Verifica:* configurazione.

---

## Modalità scura

**R13.** Ogni componente supporta la modalità scura dal momento in cui viene creato.
*Motivo:* aggiungerla dopo costa il triplo e produce risultati incoerenti.
*Verifica:* revisione visiva. *Livello: vincolante.*

```html
<div class="bg-white text-gray-900 dark:bg-gray-800 dark:text-gray-100">
```

**R14.** Il contrasto è verificato in **entrambe** le modalità.
*Verifica:* strumento di verifica del contrasto.

---

## Esempi

### Esempio 1 — componente con varianti

```blade
{{-- resources/views/components/button.blade.php --}}
@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button'])

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => implode(' ', [
        'inline-flex items-center justify-center rounded-md font-medium',
        'focus:outline-none focus:ring-2 focus:ring-offset-2',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        match ($size) {
            'sm' => 'px-3 py-1.5 text-sm',
            'lg' => 'px-6 py-3 text-base',
            default => 'px-4 py-2 text-sm',
        },
        match ($variant) {
            'primary' => 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500',
            'secondary' => 'bg-gray-100 text-gray-900 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-100',
            'danger' => 'bg-danger-600 text-white hover:bg-danger-700 focus:ring-danger-500',
        },
    ])]) }}
>
    {{ $slot }}
</button>
```

### Esempio 2 — violazioni

```html
<!-- ✗ R6: colore arbitrario, ✗ R7: stile inline, ✗ R13: nessuna modalità scura -->
<div class="bg-[#f3f4f6]" style="padding: 17px">
    <span class="text-[#111827]">Testo</span>
</div>
```

---

## Best practice

- Estrarre un componente al terzo utilizzo, non prima e non dopo.
- Usare i colori per ruolo: `danger` per le azioni distruttive, non «rosso».
- Verificare la modalità scura mentre si sviluppa, non alla fine.
- Tenere il foglio di stile personalizzato sotto le 100 righe: oltre, qualcosa non va.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Classi personalizzate | Due sistemi di stile | Componenti Blade |
| `@apply` diffuso | Torna il problema del CSS che cresce | Utility nel markup |
| Colori arbitrari | Incoerenza visiva tra prodotti | Valori del tema |
| Stile inline | CSP violata, non riusabile | Utility |
| Modalità scura aggiunta dopo | Risultati incoerenti, triplo lavoro | Da subito |
| Utility ripetute in dieci punti | Modifiche in dieci punti | Componente |
| Colori scelti per aspetto | Cambio di tema impossibile | Ruolo semantico |

---

## Checklist

- [ ] Utility nel markup, nessuna classe personalizzata duplicata.
- [ ] `@apply` assente o motivato.
- [ ] Colori e spaziature dal tema, per ruolo semantico.
- [ ] Nessuno stile inline.
- [ ] Ordine delle classi automatico.
- [ ] Elementi ripetuti estratti in componenti.
- [ ] Modalità scura supportata da ogni componente.
- [ ] Contrasto verificato in entrambe le modalità.

---

## Riferimenti

- [Frontend](frontend.md) · [UI](ui.md) · [Accessibilità](accessibility.md)
- [Sviluppare il frontend](../docs/03-development/06-frontend-development-guide.md)
- [Template frontend](../templates/frontend/README.md)
