# Regole — Alpine.js

> Interattività lato client, senza logica applicativa e senza chiamate HTTP dirette.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole](#regole)
3. [Quando Alpine, quando Livewire](#quando-alpine-quando-livewire)
4. [Accessibilità](#accessibilità)
5. [Esempi](#esempi)
6. [Best practice](#best-practice)
7. [Errori comuni](#errori-comuni)
8. [Checklist](#checklist)
9. [Riferimenti](#riferimenti)

---

## Descrizione

Alpine copre l'interattività che non richiede il server: aprire un menu, mostrare un pannello,
gestire una scheda attiva. È il complemento di Livewire, non un'alternativa.

Il rischio è che `x-data` diventi il posto dove si scrivono programmi: attributi HTML con
cinquanta righe di JavaScript, non testabili e non riusabili.

---

## Regole

**R1.** Alpine gestisce lo **stato dell'interfaccia**; Livewire i **dati**.
*Verifica:* revisione.

**R2.** La logica oltre le **15 righe** va in un file JavaScript separato, registrato come
componente Alpine.

```js
// resources/js/components/dropdown.js
Alpine.data('dropdown', () => ({
    open: false,
    toggle() { this.open = ! this.open; },
    close() { this.open = false; },
}));
```

*Verifica:* revisione.

**R3.** Nessuna chiamata HTTP diretta da Alpine.
*Motivo:* passando da Livewire si ottengono autorizzazione, contesto tenant e validazione.
*Verifica:* revisione. *Livello: vincolante.*

**R4.** Nessuna logica di business in Alpine (calcoli di dominio, validazioni autorevoli).
*Motivo:* il codice lato client è modificabile dall'utente. *Verifica:* revisione.

**R5.** Nessun dato sensibile in `x-data`.
*Motivo:* è visibile nel sorgente della pagina. *Verifica:* revisione. *Livello: assoluto.*

**R6.** Gli attributi ARIA si aggiornano insieme allo stato.
*Verifica:* prova con screen reader, revisione.

**R7.** `x-cloak` sugli elementi inizialmente nascosti.
*Motivo:* senza, compaiono per un istante al caricamento. *Verifica:* revisione visiva.

**R8.** Nessuna duplicazione di stato tra Alpine e Livewire sullo stesso dato.
*Motivo:* due sorgenti di verità che divergono. *Verifica:* revisione.

---

## Quando Alpine, quando Livewire

| Caso | Strumento |
|---|---|
| Menu a tendina, modale, scheda | Alpine |
| Mostrare o nascondere una sezione | Alpine |
| Contatore di caratteri | Alpine |
| Copia negli appunti | Alpine |
| Validazione immediata di forma (non autorevole) | Alpine |
| Filtro che interroga il database | Livewire |
| Salvataggio | Livewire |
| Caricamento di dati | Livewire |
| Validazione autorevole | Livewire (server) |

---

## Accessibilità

```html
<div x-data="{ open: false }">
    <button
        @click="open = ! open"
        :aria-expanded="open"
        aria-controls="details-panel"
        class="btn btn-secondary"
    >
        {{ __('common.details') }}
    </button>

    <div x-show="open" x-cloak x-collapse id="details-panel" role="region">
        {{ $slot }}
    </div>
</div>
```

**R9.** Ogni elemento interattivo è raggiungibile e azionabile da tastiera.
*Verifica:* prova manuale.

**R10.** I componenti che catturano il focus (modali) lo rilasciano correttamente alla chiusura.
*Verifica:* prova manuale.

---

## Esempi

### Esempio 1 — conforme

```html
<div x-data="{ count: 0, max: 255 }">
    <textarea
        x-model="text"
        @input="count = $event.target.value.length"
        maxlength="255"
        class="w-full rounded-md border-gray-300 dark:border-gray-600"
    ></textarea>

    <p class="mt-1 text-sm" :class="count > max * 0.9 ? 'text-danger-600' : 'text-gray-500'">
        <span x-text="count"></span> / <span x-text="max"></span>
    </p>
</div>
```

### Esempio 2 — violazioni

```html
<!-- ✗ R3: chiamata HTTP diretta, ✗ R4: logica di business, ✗ R5: dato sensibile -->
<div x-data="{
    apiToken: '{{ auth()->user()->api_token }}',
    async save() {
        const total = this.quantity * this.price * 1.22;
        await fetch('/api/v1/movements', {
            method: 'POST',
            headers: { Authorization: 'Bearer ' + this.apiToken },
            body: JSON.stringify({ total }),
        });
    }
}">
```

---

## Best practice

- Se `x-data` supera quindici righe, estrarre un componente.
- Aggiornare ARIA insieme allo stato, non dopo.
- `x-cloak` su tutto ciò che parte nascosto.
- Verificare la navigazione da tastiera su ogni componente interattivo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Programmi dentro `x-data` | Codice non testabile né riusabile | File JS separato |
| `fetch()` da Alpine | Nessuna autorizzazione né contesto tenant | Passare da Livewire |
| Calcoli di dominio lato client | Aggirabili dall'utente | Calcolo lato server |
| Dati sensibili in `x-data` | Visibili nel sorgente | Solo dati pubblici |
| ARIA non aggiornata | Componente inaccessibile | ARIA legata allo stato |
| `x-cloak` dimenticato | Elementi che lampeggiano al caricamento | Attributo sempre |
| Stato duplicato con Livewire | Due verità divergenti | Una sola sorgente |

---

## Checklist

- [ ] Alpine solo per lo stato dell'interfaccia.
- [ ] Logica oltre 15 righe in file JS separati.
- [ ] Nessuna chiamata HTTP diretta.
- [ ] Nessuna logica di business lato client.
- [ ] Nessun dato sensibile in `x-data`.
- [ ] ARIA aggiornata insieme allo stato.
- [ ] `x-cloak` sugli elementi nascosti all'avvio.
- [ ] Navigazione da tastiera verificata.
- [ ] Nessuna duplicazione di stato con Livewire.

---

## Riferimenti

- [Frontend](frontend.md) · [Livewire](livewire.md) · [Accessibilità](accessibility.md) · [Sicurezza](security.md)
- [Sviluppare il frontend](../docs/03-development/06-frontend-development-guide.md)
