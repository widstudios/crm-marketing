# Regole — UI

> Coerenza visiva, stati espliciti, componenti riconoscibili tra tutti i prodotti aziendali.

---

## Indice

1. [Descrizione](#descrizione)
2. [Coerenza](#coerenza)
3. [Stati obbligatori](#stati-obbligatori)
4. [Componenti standard](#componenti-standard)
5. [Tipografia e spaziature](#tipografia-e-spaziature)
6. [Colori e significato](#colori-e-significato)
7. [Densità e dispositivi](#densità-e-dispositivi)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

Un cliente che usa due nostri prodotti deve riconoscerli come della stessa famiglia; un operatore
che passa da uno all'altro non deve reimparare dove si trovano le cose.

L'interfaccia di un gestionale non deve essere originale: deve essere **prevedibile**.

---

## Coerenza

**R1.** La posizione degli elementi è la stessa in tutti i prodotti.

| Elemento | Posizione |
|---|---|
| Navigazione principale | colonna a sinistra |
| Azioni della pagina | in alto a destra |
| Azioni di riga | ultima colonna della tabella |
| Filtri | sopra la tabella |
| Messaggi di riscontro | notifica in alto a destra |
| Errori di validazione | sotto il campo interessato |
| Azioni del form | in basso, primaria a destra |
| Percorso di navigazione | sopra il titolo della pagina |

*Verifica:* revisione visiva.

**R2.** Le azioni distruttive sono visivamente distinte e richiedono conferma.
*Verifica:* revisione.

**R3.** L'azione primaria è una sola per schermata.
*Motivo:* due azioni primarie non ne evidenziano nessuna. *Verifica:* revisione.

**R4.** Le icone hanno sempre un'etichetta testuale, o un'etichetta accessibile quando lo spazio non
lo consente.
*Motivo:* le icone da sole sono ambigue. *Verifica:* revisione, accessibilità.

---

## Stati obbligatori

**R5.** Ogni componente che carica dati gestisce **quattro** stati.

| Stato | Cosa mostra |
|---|---|
| Caricamento | scheletro o indicatore, non pagina bianca |
| Vuoto | messaggio esplicativo e azione suggerita |
| Errore | cosa è andato storto e cosa fare |
| Popolato | i dati |

*Motivo:* lo stato vuoto e quello di errore sono quelli che si dimenticano, e sono quelli che
l'utente incontra nei momenti peggiori. *Verifica:* revisione. *Livello: vincolante.*

**R6.** Lo stato vuoto distingue «nessun dato» da «nessun risultato per questo filtro».
*Verifica:* revisione.

**R7.** Ogni azione oltre i 300 ms mostra un indicatore di avanzamento e disabilita il comando.
*Motivo:* senza, l'utente clicca due volte. *Verifica:* revisione.

---

## Componenti standard

Componenti forniti dalla Foundation, da non riscrivere:

| Componente | Uso |
|---|---|
| `x-button` | azioni, con varianti e dimensioni |
| `x-card` | contenitore di contenuto |
| `x-badge` | stati ed etichette |
| `x-alert` | messaggi persistenti |
| `x-modal` | conferme e form brevi |
| `x-empty-state` | stato vuoto |
| `x-skeleton` | caricamento |
| `x-data-table` | tabelle non Filament |
| `x-form-field` | campo con etichetta, aiuto ed errore |
| `x-breadcrumbs` | percorso di navigazione |

**R8.** I componenti standard si usano invece di essere riscritti.
*Verifica:* revisione.

**R9.** Un componente nuovo che sarebbe utile in tre progetti si propone per la Foundation.
*Verifica:* revisione.

---

## Tipografia e spaziature

| Elemento | Regola |
|---|---|
| Scala tipografica | dal tema, quattro livelli di titolo al massimo |
| Corpo del testo | 14-16 px, interlinea 1,5 |
| Lunghezza di riga | massimo ~75 caratteri per il testo lungo |
| Spaziature | multipli della scala del tema, mai valori arbitrari |
| Allineamento | numeri a destra, testo a sinistra, date coerenti |

**R10.** I numeri nelle tabelle sono allineati a destra, con separatore delle migliaia e decimali
coerenti.
*Motivo:* le colonne numeriche si confrontano visivamente solo se allineate.
*Verifica:* revisione.

**R11.** Le date usano un formato coerente in tutto il prodotto, localizzato.
*Verifica:* revisione.

---

## Colori e significato

**R12.** I colori si usano per il **ruolo**, non per l'aspetto.

| Ruolo | Uso |
|---|---|
| `primary` | azione principale, elemento attivo |
| `success` | esito positivo, stato valido |
| `warning` | attenzione, scadenza prossima |
| `danger` | errore, azione distruttiva |
| `info` | informazione neutra |
| `gray` | testo, bordi, elementi disabilitati |

*Verifica:* revisione.

**R13.** Nessuna informazione è veicolata dal **solo** colore: sempre accompagnata da testo o icona.
*Motivo:* accessibilità per chi non distingue i colori.
*Verifica:* revisione. *Livello: vincolante.*

**R14.** Il significato dei colori è identico in tutti i prodotti.
*Verifica:* revisione.

---

## Densità e dispositivi

**R15.** Le interfacce operative privilegiano la **densità** informativa: gli operatori lavorano su
molte righe.
*Verifica:* revisione con utenti reali.

**R16.** Le tabelle su schermo piccolo diventano schede, non tabelle con scorrimento orizzontale.
*Verifica:* prova su dispositivo.

**R17.** Gli elementi tattili hanno almeno 44×44 px di area attiva.
*Verifica:* revisione.

**R18.** Nessun contenuto obbliga allo scorrimento orizzontale della pagina.
*Verifica:* prova su dispositivo.

---

## Esempi

### Esempio 1 — stato vuoto utile

```blade
<x-empty-state
    icon="heroicon-o-cube"
    :title="__('inventory.batches.empty.title')"
    :description="__('inventory.batches.empty.description')"
>
    <x-button variant="primary" href="{{ route('batches.create') }}">
        {{ __('inventory.batches.create') }}
    </x-button>
</x-empty-state>
```

Dice cosa manca e cosa fare, invece di mostrare una tabella vuota.

### Esempio 2 — informazione dal solo colore

```blade
{{-- ✗ R13: chi non distingue i colori non capisce lo stato --}}
<span class="h-3 w-3 rounded-full bg-danger-500"></span>

{{-- ✓ Colore, icona e testo --}}
<x-badge variant="danger">
    <x-icon name="exclamation-triangle" class="mr-1 h-4 w-4" />
    {{ __('inventory.batch.expired') }}
</x-badge>
```

---

## Best practice

- Prima di disegnare una schermata, verificare se esiste già un modello simile in un altro prodotto.
- Progettare lo stato vuoto insieme a quello popolato.
- Provare l'interfaccia con volumi di dati realistici.
- Mostrare all'utente reale prima di considerare finita una schermata.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Stato vuoto non gestito | Tabella vuota senza spiegazioni | Componente dedicato |
| Stato di errore non gestito | Pagina bianca | Messaggio con azione |
| Due azioni primarie | Nessuna risalta | Una sola |
| Icone senza etichetta | Ambiguità | Etichetta o etichetta accessibile |
| Informazione dal solo colore | Inaccessibile | Colore + testo/icona |
| Numeri allineati a sinistra | Colonne non confrontabili | Allineamento a destra |
| Tabella con scorrimento orizzontale su mobile | Inutilizzabile | Schede |
| Nessun indicatore sulle azioni lente | Doppi invii | Indicatore + comando disabilitato |

---

## Checklist

- [ ] Posizione degli elementi conforme allo standard.
- [ ] Una sola azione primaria per schermata.
- [ ] Azioni distruttive distinte e con conferma.
- [ ] Quattro stati gestiti su ogni componente che carica dati.
- [ ] Stato vuoto distingue «nessun dato» da «nessun risultato».
- [ ] Indicatore di avanzamento sulle azioni oltre 300 ms.
- [ ] Componenti standard usati, non riscritti.
- [ ] Numeri allineati a destra, formati coerenti.
- [ ] Colori per ruolo; nessuna informazione dal solo colore.
- [ ] Tabelle usabili su schermo piccolo.

---

## Riferimenti

- [UX](ux.md) · [Accessibilità](accessibility.md) · [Tailwind](tailwind.md) · [Filament](filament.md)
- [Sviluppare il frontend](../docs/03-development/06-frontend-development-guide.md)
