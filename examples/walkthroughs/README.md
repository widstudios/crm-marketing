# Walkthrough

> Percorsi completi, dal brief al rilascio, con le decisioni prese e le fermate incontrate.

---

## Indice

1. [Descrizione](#descrizione) 2. [I walkthrough](#i-walkthrough) 3. [Come sono strutturati](#come-sono-strutturati)
4. [Che cosa cercarci](#che-cosa-cercarci) 5. [Esempi](#esempi) 6. [Best practice](#best-practice)
7. [Errori comuni](#errori-comuni) 8. [Checklist](#checklist) 9. [Riferimenti](#riferimenti)

---

## Descrizione

Il [master workflow](../../workflows/00-master-workflow.md) descrive le quattordici fasi in
astratto: input, output, quality gate. Un walkthrough le mostra **su un caso reale**, con le cose
che in astratto non si vedono — il requisito ambiguo che nessuno aveva notato, il gate che è
diventato rosso, il rework che ha cambiato lo schema.

La parte più utile non è ciò che è andato bene: sono le **fermate**. I punti in cui il processo si è
interrotto per chiedere a una persona sono quelli che insegnano dove passa il confine tra ciò che un
agente può decidere e ciò che non deve decidere.

---

## I walkthrough

| Walkthrough | Verticale | Fasi | Fermate |
|---|---|---|---|
| [01 — Magazzino Sanitario](01-magazzino-sanitario.md) | sanità | 14 su 14 | 2 |

---

## Come sono strutturati

Ogni walkthrough segue la stessa forma:

| Sezione | Contenuto |
|---|---|
| Il brief | ciò che il committente ha effettivamente scritto, non una versione ripulita |
| Fase per fase | input, che cosa ha prodotto l'agente, esito del gate |
| Le fermate | la domanda, perché non era decidibile, la risposta ricevuta |
| Le decisioni | le ADR prodotte, con l'alternativa scartata |
| I rework | che cosa è tornato indietro, e perché |
| Il risultato | artefatti prodotti, copertura, durata |
| Che cosa si sarebbe potuto fare meglio | la sezione più onesta e la più utile |

L'ultima sezione esiste perché un walkthrough che racconta un percorso senza errori non è un
resoconto: è materiale pubblicitario, e non insegna nulla a chi il percorso lo deve fare davvero.

---

## Che cosa cercarci

| Se stai cercando | Guarda |
|---|---|
| come si concatenano le fasi | la sequenza completa, in ordine |
| dove un agente deve fermarsi | la sezione **Fermate** |
| come si scrive una ADR su un caso reale | la sezione **Decisioni** |
| quanto costa davvero un rework | la sezione **Rework** |
| che cosa aspettarsi come risultato | la sezione **Risultato** |

**Non** cercarci il dominio: le entità di un walkthrough appartengono al suo verticale, e copiarle
altrove produce un modello che risponde a domande che nessuno ha posto.

---

## Esempi

### La fermata come informazione

> *Fase 1. Il brief dice: «i lotti scaduti non si possono usare».*
>
> Domanda dell'agente: **«non si possono usare» significa che non si possono scaricare, o che non si
> possono nemmeno vedere?** E un lotto scaduto già impegnato in un ordine in corso, che fine fa?
>
> Non è una richiesta di chiarimento formale: le due letture producono software diversi, ed entrambi
> sembrano corretti a chi non conosce il dominio. Deciderla da soli avrebbe prodotto un sistema
> plausibile e sbagliato.

È il criterio che distingue un'**assunzione** — dichiarabile e proseguibile — da una **domanda
aperta**, che non lo è: se una scelta sbagliata produrrebbe software plausibile e sbagliato, non si
sceglie.

---

## Best practice

- Leggere il walkthrough dopo le regole, non al posto delle regole.
- Partire dalle fermate: sono la parte che non si trova altrove.
- Confrontare la durata dichiarata con la propria stima, prima di iniziare un progetto simile.
- Leggere «che cosa si sarebbe potuto fare meglio» per primo, se si sta per iniziare.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Dominio del walkthrough copiato | Entità che rispondono a domande di un altro settore | Copiare il metodo |
| Walkthrough letto come procedura | Si segue una sequenza tarata su altri vincoli | È illustrativo |
| Fermate saltate nella lettura | Si perde la parte più istruttiva | Leggerle per prime |
| Walkthrough senza errori | Materiale pubblicitario, non insegna nulla | Sezione onesta obbligatoria |

---

## Checklist

- [ ] Ho letto le regole pertinenti prima del walkthrough.
- [ ] Ho letto la sezione delle fermate.
- [ ] Sto adottando il metodo, non il dominio.
- [ ] Ho verificato che il walkthrough sia coerente con la Factory attuale.

---

## Riferimenti

- [Examples](../README.md) · [Frammenti di codice](../code/README.md)
- [Master workflow](../../workflows/00-master-workflow.md) · [`loop crea`](../../prompts/loop-crea.md)
- [Orchestratore](../../agents/16-orchestrator-agent.md) · [Protocollo agenti](../../agents/00-agent-protocol.md)
