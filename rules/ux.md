# Regole — UX

> Flussi prevedibili, errori prevenuti, messaggi utili. L'obiettivo è ridurre il carico cognitivo di
> chi usa il software otto ore al giorno.

---

## Indice

1. [Descrizione](#descrizione)
2. [Principi operativi](#principi-operativi)
3. [Prevenzione degli errori](#prevenzione-degli-errori)
4. [Messaggi](#messaggi)
5. [Flussi](#flussi)
6. [Operazioni massive e lunghe](#operazioni-massive-e-lunghe)
7. [Ricerca e filtri](#ricerca-e-filtri)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

I nostri utenti non scelgono il software: lo usano perché è il gestionale dell'azienda, tutti i
giorni, spesso sotto pressione. Non cercano un'esperienza gradevole: cercano di **finire il lavoro
senza sbagliare**.

Questo cambia le priorità: prevedibilità prima di eleganza, prevenzione degli errori prima della
velocità, densità informativa prima dello spazio bianco.

---

## Principi operativi

**R1.** L'operazione più frequente richiede il minor numero di passaggi.
*Motivo:* un passaggio in più su un'operazione ripetuta cento volte al giorno costa ore.
*Verifica:* revisione con l'utente reale.

**R2.** Nessuna schermata senza un'azione evidente.
*Verifica:* revisione.

**R3.** L'utente sa sempre dove si trova: percorso di navigazione, titolo, elemento attivo nel menu.
*Verifica:* revisione.

**R4.** Le operazioni reversibili non chiedono conferma; quelle irreversibili sì.
*Motivo:* la conferma su tutto abitua a confermare senza leggere.
*Verifica:* revisione.

**R5.** Il lavoro non si perde: i form lunghi salvano una bozza, la navigazione via chiede conferma.
*Verifica:* revisione.

---

## Prevenzione degli errori

**R6.** L'interfaccia **impedisce** l'errore invece di segnalarlo dopo.

| Tecnica | Esempio |
|---|---|
| Azione non disponibile | pulsante «archivia» assente se la transizione non è ammessa |
| Valori vincolati | selezione invece di testo libero |
| Formato guidato | maschera di inserimento sulle date |
| Limiti visibili | «massimo 5 unità disponibili» accanto al campo |
| Valori predefiniti sensati | data di oggi, magazzino dell'operatore |
| Conferma sui casi anomali | «la quantità supera la media del 400%: confermi?» |

*Verifica:* revisione. *Livello: vincolante.*

**R7.** I campi obbligatori sono indicati prima della compilazione, non dopo l'invio.
*Verifica:* revisione.

**R8.** La validazione immediata segnala l'errore quando l'utente lascia il campo, non ad ogni
carattere digitato.
*Motivo:* segnalare mentre si scrive è percepito come rimprovero.
*Verifica:* revisione.

---

## Messaggi

**R9.** Ogni messaggio di errore dice **cosa è successo** e **cosa fare**.

```
✗  Errore durante il salvataggio.
✓  Impossibile registrare lo scarico: il lotto LOT-0042 ha 5 unità disponibili, ne sono state
   richieste 10. Riduci la quantità o seleziona un altro lotto.
```

*Verifica:* revisione. *Livello: vincolante.*

**R10.** I messaggi usano il linguaggio del **dominio**, non quello tecnico.
*«Il fornitore ha movimenti recenti»*, non *«violazione di vincolo di integrità referenziale»*.
*Verifica:* revisione.

**R11.** I messaggi di successo confermano **cosa** è avvenuto, non solo che è avvenuto.
*«Movimento registrato: 10 unità scaricate dal lotto LOT-0042»*, non *«Operazione completata»*.
*Verifica:* revisione.

**R12.** Nessun messaggio espone dettagli tecnici o interni all'utente finale.
*Verifica:* revisione, test con `APP_DEBUG=false`.

**R13.** I messaggi non attribuiscono colpa all'utente.
*«La quantità supera la disponibilità»*, non *«Hai inserito una quantità errata»*.
*Verifica:* revisione.

---

## Flussi

**R14.** Un flusso con più passaggi mostra sempre a che punto si è e quanti passaggi restano.
*Verifica:* revisione.

**R15.** L'utente può tornare indietro senza perdere i dati inseriti.
*Verifica:* prova manuale.

**R16.** I flussi interrotti si possono riprendere.
*Verifica:* revisione.

**R17.** Dopo un'operazione l'utente si trova dove serve continuare, non in una schermata generica.
*Motivo:* dopo aver registrato un movimento, servirà registrarne un altro.
*Verifica:* revisione con l'utente reale.

---

## Operazioni massive e lunghe

**R18.** Le operazioni oltre i 2 secondi girano in coda, con riscontro immediato all'utente.
*Verifica:* revisione.

**R19.** Le operazioni lunghe mostrano l'avanzamento e permettono di continuare a lavorare.
*Verifica:* revisione.

**R20.** Le operazioni massive mostrano un riepilogo prima di eseguire e un esito dettagliato dopo.
*Motivo:* «45 righe importate, 3 scartate» è utile; «Importazione completata» no.
*Verifica:* revisione.

**R21.** Le operazioni massive parzialmente fallite indicano **quali** elementi sono falliti e
perché, in forma esportabile.
*Verifica:* revisione.

---

## Ricerca e filtri

**R22.** I filtri applicati sono visibili e rimovibili singolarmente.
*Verifica:* revisione.

**R23.** I filtri persistono durante la sessione.
*Motivo:* un operatore che lavora su un magazzino non vuole riselezionarlo ad ogni pagina.
*Verifica:* revisione.

**R24.** La ricerca parte da 3 caratteri, con attesa dopo la digitazione.
*Verifica:* revisione.

**R25.** Lo stato «nessun risultato» suggerisce come allargare la ricerca.
*Verifica:* revisione.

---

## Esempi

### Esempio 1 — prevenzione invece di segnalazione

```
✗  L'utente inserisce quantità 10, invia, riceve «giacenza insufficiente».

✓  Il campo mostra «disponibili: 5», il valore massimo è limitato a 5, e il pulsante di invio
   resta disabilitato finché il valore non è valido.
```

### Esempio 2 — esito di un'operazione massiva

```
Importazione completata: 45 righe importate, 3 scartate.

Righe scartate:
  riga 12 — articolo «ART-999» non trovato
  riga 27 — quantità non numerica: «dieci»
  riga 41 — lotto «LOT-0001» già presente

[Scarica il rapporto completo]
```

---

## Best practice

- Osservare l'utente reale mentre lavora: i passaggi inutili si notano solo così.
- Progettare gli errori prima dei percorsi corretti.
- Scrivere i messaggi con l'interlocutore di dominio, non da soli.
- Contare i passaggi delle operazioni frequenti e ridurli.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Errore segnalato dopo l'invio | Lavoro perso, frustrazione | Prevenirlo nell'interfaccia |
| Messaggi generici | L'utente non sa cosa fare | Cosa è successo + cosa fare |
| Linguaggio tecnico | Incomprensibile, genera ticket | Linguaggio del dominio |
| Conferma su tutto | Si conferma senza leggere | Solo per l'irreversibile |
| Filtri che si azzerano | Riselezione continua | Persistenza in sessione |
| Redirect a schermata generica | L'utente deve ritrovare la strada | Dove serve continuare |
| Esito massivo senza dettagli | Impossibile correggere | Elenco degli scarti |
| Operazione lunga sincrona | Interfaccia bloccata, timeout | Coda con avanzamento |

---

## Checklist

- [ ] L'operazione più frequente ha il minor numero di passaggi.
- [ ] Ogni schermata ha un'azione evidente.
- [ ] Gli errori sono prevenuti, non solo segnalati.
- [ ] I messaggi dicono cosa è successo e cosa fare, nel linguaggio del dominio.
- [ ] Conferma solo per le operazioni irreversibili.
- [ ] I flussi mostrano l'avanzamento e permettono il ritorno.
- [ ] Dopo un'operazione l'utente si trova dove serve continuare.
- [ ] Le operazioni lunghe girano in coda con avanzamento.
- [ ] Le operazioni massive hanno riepilogo ed esito dettagliato.
- [ ] I filtri sono visibili, rimovibili e persistenti.

---

## Riferimenti

- [UI](ui.md) · [Accessibilità](accessibility.md) · [i18n](i18n.md)
- [Sviluppare con Filament](../docs/03-development/05-filament-development-guide.md)
