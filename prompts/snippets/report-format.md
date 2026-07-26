# Snippet — formato del rapporto di fase

> Il formato del rapporto, in forma compatta, per l'inclusione negli altri prompt.

| | |
|---|---|
| **Versione** | 1.0.0 |
| **Usato da** | tutti gli agenti |
| **Fonte estesa** | [`system/02-output-format.md`](../system/02-output-format.md) |

---

## Indice

1. [Descrizione](#descrizione) 2. [Lo snippet](#lo-snippet) 3. [Esempi](#esempi)
4. [Best practice](#best-practice) 5. [Errori comuni](#errori-comuni) 6. [Checklist](#checklist)
7. [Riferimenti](#riferimenti)

---

## Descrizione

Il rapporto di fase è ciò che rende possibile l'handoff: dice alla fase successiva cosa ha ricevuto,
cosa è stato assunto e cosa resta aperto. Questo snippet ne contiene il formato in forma compatta,
da includere quando il prompt di sistema esteso non viene usato.

---

## Lo snippet

```markdown
## Formato del rapporto di fase

Al termine, produci:

    ## Rapporto di fase — <Nome> Agent

    ### Artefatti prodotti
    - <percorso completo>   (nuovo | modificato)

    ### Decisioni prese
    - <decisione>: <motivazione in una riga>

    ### Assunzioni
    - <assunzione>. Fonte del vuoto: <dove mancava>. **Da confermare.**
      (oppure: Nessuna.)

    ### Domande aperte
    - <domanda> — (a) <opzione>: <conseguenze>; (b) <opzione>: <conseguenze>.
      Chi può rispondere: <ruolo>.
      (oppure: Nessuna.)

    ### Deviazioni dalle regole
    - <regola e numero>: <motivo>, <ambito>, <durata proposta>.  (oppure: Nessuna.)

    ### Conflitti rilevati
    - <fonte A> contraddice <fonte B>: gerarchia applicata, correzione proposta.
      (oppure: Nessuno.)

    ### Verifiche eseguite
    - <comando>: <esito reale>

    ### Quality gate
    - checklists/<nome>.md: <n>/<totale>.
    - Voci non soddisfatte: <elenco con correzione necessaria> (oppure: nessuna).

**Tutte** le sezioni sono obbligatorie. Una sezione vuota si dichiara («Nessuna.»): l'omissione non
distingue «non ce ne sono» da «non ho verificato».

Chi legge il rapporto guarda per prime: **domande aperte**, **assunzioni**, **quality gate**.
```

---

## Esempi

Esempio completo in [`system/02-output-format.md`](../system/02-output-format.md#esempi).

---

## Best practice

- Compilare il rapporto mentre si lavora, non ricostruirlo alla fine.
- Scrivere le domande aperte per prime: sono ciò che blocca il processo.
- Riportare gli esiti reali dei comandi, non quelli attesi.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Sezioni omesse | Non si distingue «niente» da «non verificato» | Dichiararle vuote |
| Rapporto ricostruito a fine lavoro | Assunzioni dimenticate | Compilarlo durante |
| Esiti attesi al posto di quelli reali | Difetti che emergono a valle | Esito reale |

---

## Checklist

- [ ] Tutte le sezioni presenti, comprese quelle vuote.
- [ ] Assunzioni con la fonte del vuoto.
- [ ] Domande con opzioni e conseguenze.
- [ ] Esiti di verifica reali.
- [ ] Gate riportato voce per voce.

---

## Riferimenti

- [Formato di output esteso](../system/02-output-format.md)
- [Protocollo agenti](../../agents/00-agent-protocol.md#il-rapporto-di-fase)
