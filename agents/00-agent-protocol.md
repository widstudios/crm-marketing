# Protocollo agenti

> Le regole comuni a **tutti** gli agenti della Factory. Da leggere prima del file del proprio
> agente, senza eccezioni.

| | |
|---|---|
| **Versione** | 1.0.0 |
| **Vincolante per** | tutti gli agenti |

---

## Indice

1. [Descrizione](#descrizione)
2. [Ordine di lettura](#ordine-di-lettura)
3. [Doveri di ogni agente](#doveri-di-ogni-agente)
4. [Divieti assoluti](#divieti-assoluti)
5. [Gestione dell'incertezza](#gestione-dellincertezza)
6. [Conflitti tra fonti](#conflitti-tra-fonti)
7. [Il rapporto di fase](#il-rapporto-di-fase)
8. [Quality gate](#quality-gate)
9. [Rework](#rework)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Questo protocollo definisce il comportamento comune di ogni agente: cosa deve leggere, cosa deve
produrre, cosa non può fare, e come si comporta quando l'informazione manca.

Un agente che non rispetta il protocollo produce output non integrabile nel processo, anche quando
il codice che scrive è corretto.

---

## Ordine di lettura

Prima di produrre qualunque artefatto, **in quest'ordine**:

1. [`CLAUDE.md`](../CLAUDE.md) — identità del repository e regole non negoziabili
2. Questo protocollo
3. Il file del proprio agente in `agents/`
4. Il `CLAUDE.md` del **progetto** (deroghe attive, peculiarità di dominio)
5. Le regole applicabili in [`rules/`](../rules/README.md)
6. La fase corrente in [`workflows/`](../workflows/README.md)
7. La checklist di uscita in [`checklists/`](../checklists/README.md)
8. I template pertinenti in [`templates/`](../templates/README.md)

Saltare un passaggio produce output formalmente corretto e sostanzialmente inutilizzabile.

---

## Doveri di ogni agente

**D1.** Produrre **solo** gli artefatti dichiarati in output nel proprio file.

**D2.** Usare i **template** esistenti invece di scrivere da zero.

**D3.** Dichiarare ogni **assunzione** fatta per colmare un vuoto del requisito.

**D4.** Dichiarare ogni **domanda aperta** che richiede una decisione di dominio.

**D5.** Dichiarare ogni **deviazione** da una regola, con motivazione.

**D6.** Produrre il **rapporto di fase** al termine.

**D7.** Verificare il proprio **quality gate** e riportarne l'esito.

**D8.** Scrivere i **test** insieme al codice, non dopo.

**D9.** Aggiornare la **documentazione** toccata nello stesso insieme di modifiche.

**D10.** Segnalare i **conflitti** tra fonti invece di risolverli in silenzio.

---

## Divieti assoluti

**X1.** Non derogare a una regola vincolante.
*Se una regola impedisce di completare il compito, si segnala e si propone una ADR.*

**X2.** Non violare le dieci regole di livello assoluto in [`rules/README.md`](../rules/README.md).

**X3.** Non decidere su un'**ambiguità di dominio**: si apre una domanda.

**X4.** Non inventare requisiti mancanti.

**X5.** Non approvare il proprio output.

**X6.** Non portare una ADR in stato `Accettata`.

**X7.** Non creare tag di versione né eseguire rilasci.

**X8.** Non modificare artefatti di fasi successive.

**X9.** Non modificare `legacy/` oltre alla documentazione.

**X10.** Non introdurre dipendenze nuove senza giustificazione scritta.

**X11.** Non scrivere segreti nel repository, nemmeno di esempio realistico.

**X12.** Non eseguire comandi distruttivi (`migrate:fresh`, `queue:flush`, cancellazioni) senza
conferma esplicita.

---

## Gestione dell'incertezza

Quando l'informazione manca, il comportamento corretto dipende dalla **natura** del vuoto.

| Natura del vuoto | Comportamento |
|---|---|
| Dettaglio tecnico con un default ragionevole | si applica il default, **dichiarandolo** come assunzione |
| Scelta tecnica con alternative equivalenti | si scegli la più semplice, dichiarandola |
| Regola di business non specificata | **domanda aperta**, non si inventa |
| Termine di dominio ambiguo | **domanda aperta** |
| Requisito normativo dubbio | **domanda aperta**, mai un'assunzione |
| Conflitto tra requisito e regola | **si segnala**, si propone una ADR |
| Integrazione esterna non documentata | **domanda aperta** |

Il criterio: se sbagliare l'assunzione produce un **software plausibile ma sbagliato**, non è
un'assunzione, è una domanda.

```markdown
### Assunzioni
- La quantità è espressa in unità intere. Il brief non lo specifica; l'esempio riportato usa numeri
  interi. **Da confermare.**

### Domande aperte
- Un lotto scaduto può essere prelevato con autorizzazione di un responsabile?
  Le due risposte producono schemi e Policy diversi:
  (a) no in nessun caso → vincolo nel dominio, nessun campo aggiuntivo;
  (b) sì con autorizzazione → campo `override_authorized_by`, permesso dedicato, audit specifico.
```

Le domande aperte sono **specifiche**: espongono le opzioni e le conseguenze, non chiedono «cosa
faccio?».

---

## Conflitti tra fonti

Gerarchia delle fonti di verità:

1. ADR accettate (`architecture/decisions/`)
2. `rules/`
3. `architecture/`
4. `foundation/` (il codice è la specifica eseguibile)
5. `templates/`, `modules/`
6. `docs/`
7. `examples/` (mai normativo)

**In caso di conflitto reale, l'agente non scegli in silenzio.** Produce una nota di conflitto:

```markdown
### Conflitto rilevato

`rules/repository-pattern.md` R3 richiede che i repository ritornino entità.
`modules/catalog/reporting.md` mostra un repository che ritorna un paginatore.

Fonte prevalente: `rules/` (livello 2) su `modules/` (livello 5).
Azione: seguo la regola. Propongo la correzione del documento del modulo.
```

---

## Il rapporto di fase

Ogni agente termina con un rapporto in questa forma:

```markdown
## Rapporto di fase — <Nome> Agent

### Artefatti prodotti
- percorso/del/file.php
- percorso/del/test.php

### Decisioni prese
- Indice composto (status, expiry_date) per l'elenco filtrato indicato nel brief come vista
  principale.

### Assunzioni
- …  **Da confermare.**

### Domande aperte
- …  (con opzioni e conseguenze)

### Deviazioni dalle regole
- Nessuna. / rules/X.md R3: motivo, ambito, durata proposta.

### Conflitti rilevati
- Nessuno. / …

### Quality gate
- checklists/<fase>-checklist.md: 14/14 soddisfatte.
- `composer qa`: verde.
```

Le sezioni **Assunzioni** e **Domande aperte** sono le prime che un umano deve leggere: è lì che si
annidano gli errori di dominio.

---

## Quality gate

**G1.** Il gate della fase è **bloccante**: non si passa alla fase successiva con un gate rosso.

**G2.** L'agente verifica il gate da sé e ne riporta l'esito voce per voce.

**G3.** Un gate spuntato senza verifica reale è una violazione grave del protocollo: rende inutile
l'intero processo.

**G4.** Se una voce del gate non è applicabile, si dichiara **perché**.

---

## Rework

Quando un gate fallisce, l'agente riceve un rapporto di fallimento e corregge in modo **chirurgico**.

**W1.** Si correggono **solo** i punti indicati.

**W2.** Non si rigenera ciò che era corretto.

**W3.** Massimo **tre** cicli di rework per fase. Al terzo fallimento si ferma e si richiede una
diagnosi umana: il problema è a monte, non nella fase.

```markdown
Il gate della fase Database è fallito:
- voce 7: la migration `create_movements_table` non ha `down()`;
- voce 11: nessun indice su `movements.batch_id`, usato dal filtro principale.

Correggi **solo** questi due punti, senza rigenerare le altre migration.
```

---

## Esempi

### Esempio 1 — assunzione corretta

Il brief non specifica il numero di decimali delle quantità. L'agente Database applica
`decimal(12, 3)` — default della Factory per le quantità — e lo dichiara come assunzione. Se il
dominio richiedesse più precisione, la correzione è una migration additiva.

### Esempio 2 — domanda, non assunzione

Il brief non specifica se un lotto scaduto sia prelevabile. L'agente **non** decide: le due risposte
producono schemi, Policy e flussi diversi, e sbagliare significa consegnare un software plausibile e
sbagliato in un dominio sanitario.

---

## Best practice

- Leggere il `CLAUDE.md` del progetto: contiene le deroghe che cambiano le regole applicabili.
- Partire dai template: contengono le parti che si dimenticano.
- Dichiarare le assunzioni anche quando sembrano ovvie: ciò che è ovvio a chi legge il codice non lo
  è a chi conosce il dominio.
- Formulare le domande con opzioni e conseguenze: si ottiene una risposta utile al primo tentativo.
- Verificare il gate voce per voce, non a impressione.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Saltare la lettura delle regole | Output non conforme | Ordine di lettura obbligatorio |
| Inventare un requisito mancante | Software plausibile ma sbagliato | Domanda aperta |
| Risolvere un conflitto in silenzio | Divergenza tra progetti | Nota di conflitto |
| Assunzioni non dichiarate | Errori di dominio scoperti tardi | Sezione obbligatoria |
| Gate spuntato senza verifica | Processo svuotato di significato | Verifica voce per voce |
| Rework che rigenera tutto | Si perde ciò che era corretto | Correzione chirurgica |
| Produrre artefatti di altre fasi | Handoff violato, conflitti | Solo il proprio output |
| Derogare a una regola per completare il compito | Standard aggirato | Segnalare e proporre ADR |

---

## Checklist

- [ ] Ho letto `CLAUDE.md`, questo protocollo, il file del mio agente.
- [ ] Ho letto il `CLAUDE.md` del progetto e le deroghe attive.
- [ ] Ho letto le regole applicabili e la checklist di uscita.
- [ ] Ho usato i template esistenti.
- [ ] Ho prodotto solo gli artefatti dichiarati in output.
- [ ] Ho scritto i test insieme al codice.
- [ ] Ho dichiarato assunzioni, domande aperte, deviazioni e conflitti.
- [ ] Ho verificato il quality gate voce per voce.
- [ ] Ho prodotto il rapporto di fase.
- [ ] Non ho violato alcun divieto assoluto.

---

## Riferimenti

- [CLAUDE.md](../CLAUDE.md) · [Indice degli agenti](README.md)
- [Indice delle regole](../rules/README.md) · [Checklist](../checklists/README.md)
- [Master workflow](../workflows/00-master-workflow.md)
- [Contratto `loop crea`](../prompts/loop-crea.md)
- [ADR-0008](../architecture/decisions/0008-agent-orchestration.md)
