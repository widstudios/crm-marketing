# Snippet — gestione dell'incertezza

> Come distinguere ciò che si può assumere da ciò che si deve chiedere.

| | |
|---|---|
| **Versione** | 1.0.0 |
| **Usato da** | tutti gli agenti |

---

## Indice

1. [Descrizione](#descrizione) 2. [Lo snippet](#lo-snippet) 3. [Esempi](#esempi)
4. [Best practice](#best-practice) 5. [Errori comuni](#errori-comuni) 6. [Checklist](#checklist)
7. [Riferimenti](#riferimenti)

---

## Descrizione

L'informazione manca sempre. La differenza tra un agente affidabile e uno pericoloso sta nel
distinguere il vuoto che si può colmare da quello che non si deve colmare.

---

## Lo snippet

```markdown
## Quando l'informazione manca

### Il criterio

Chiediti: **se la mia assunzione fosse sbagliata, cosa otterrei?**

- Un software **corretto con un dettaglio da cambiare** → è un'assunzione. Applica il default
  ragionevole e **dichiaralo**.
- Un software **plausibile ma sbagliato** → è una domanda. **Non decidere.**

Il secondo caso è il più insidioso perché il risultato *sembra* corretto: passa i test, supera la
revisione formale, e sbaglia il problema.

### Tabella di decisione

| Natura del vuoto | Comportamento |
|---|---|
| Dettaglio tecnico con default della Factory | assunzione dichiarata |
| Scelta tecnica tra alternative equivalenti | scegli la più semplice, dichiarala |
| Formato, precisione, unità di misura | assunzione dichiarata |
| **Regola di business** | **domanda** |
| **Significato di un termine di dominio** | **domanda** |
| **Requisito normativo** | **domanda**, mai un'assunzione |
| **Chi può fare cosa** | **domanda** |
| **Cosa succede in un caso limite del dominio** | **domanda** |
| Integrazione esterna non documentata | domanda |
| Conflitto tra requisito e regola vincolante | segnalazione + proposta di ADR |

### Come si scrive un'assunzione

    - La quantità è espressa con tre decimali. Fonte del vuoto: il brief non specifica la
      precisione; gli esempi riportati usano valori interi. Default della Factory applicato.
      **Da confermare.**

Contiene: cosa hai assunto, **dove** mancava l'informazione, quale default hai applicato.

### Come si scrive una domanda

    - Un lotto scaduto può essere prelevato con autorizzazione di un responsabile?

      (a) No, in nessun caso
          → vincolo nel dominio: `BatchStatus::Expired` non ammette il prelievo
          → nessun campo aggiuntivo, nessun permesso aggiuntivo

      (b) Sì, con autorizzazione
          → campo `override_authorized_by` sui movimenti
          → permesso `movement.override_expiry`
          → voce di audit specifica
          → l'interfaccia richiede una motivazione

      Chi può rispondere: responsabile qualità del committente.

Contiene: la domanda, le **opzioni**, le **conseguenze tecniche** di ciascuna, chi può rispondere.

Una domanda formulata così ottiene una risposta utile al primo tentativo. «Cosa faccio con i lotti
scaduti?» ne ottiene un'altra domanda.

### Cosa non fare

- Non scegliere l'opzione più semplice per proseguire.
- Non scegliere l'opzione che il codice esistente rende più comoda.
- Non trasformare una domanda in assunzione perché il processo si fermerebbe: fermarsi è il
  comportamento corretto.
- Non nascondere il dubbio nel codice con un commento: va nel rapporto di fase.
```

---

## Esempi

### Esempio 1 — assunzione legittima

Il brief non specifica quanti caratteri può avere il nome di un fornitore. L'agente applica
`varchar(255)`, default della Factory, e lo dichiara. Se il dominio richiedesse di più, la correzione
è una migration additiva.

### Esempio 2 — domanda, non assunzione

Il brief non specifica se un fornitore archiviato possa essere riattivato. Le due risposte producono
enum diversi, Policy diverse e flussi diversi: è una domanda.

### Esempio 3 — la tentazione da evitare

Il processo è alla fase 3 e la fermata costa tempo. L'agente sceglie l'opzione «più ragionevole» e
prosegue.

Sei fasi dopo, il committente scopre che il comportamento è sbagliato: schema, Action, Policy,
interfaccia e test vanno rifatti. La fermata sarebbe costata mezza giornata.

---

## Best practice

- Formulare la domanda mentre si incontra il vuoto, non alla fine.
- Includere sempre le conseguenze tecniche: il committente decide meglio se sa cosa cambia.
- Dichiarare la fonte del vuoto nelle assunzioni: aiuta a correggere il brief per i progetti futuri.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Regola di business assunta | Software plausibile e sbagliato | Domanda |
| Domanda generica | Il committente non sa cosa rispondere | Opzioni e conseguenze |
| Assunzione non dichiarata | Errore scoperto in produzione | Sezione obbligatoria |
| Scelta dell'opzione più comoda | Il dominio si adatta al codice, invece del contrario | Chiedere |
| Dubbio lasciato in un commento | Nessuno lo legge | Rapporto di fase |

---

## Checklist

- [ ] Ho applicato il criterio a ogni vuoto incontrato.
- [ ] Le assunzioni dichiarano la fonte del vuoto.
- [ ] Le domande espongono opzioni e conseguenze tecniche.
- [ ] Nessuna regola di business è stata assunta.
- [ ] Nessun requisito normativo è stato assunto.

---

## Riferimenti

- [Protocollo agenti](../../agents/00-agent-protocol.md#gestione-dellincertezza)
- [Business Analyst Agent](../../agents/02-business-analyst-agent.md)
- [Contratto `loop crea`](../loop-crea.md#fermate)
