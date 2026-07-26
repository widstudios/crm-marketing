# Agenti AI della Factory

> I sedici ruoli specializzati che eseguono il processo di produzione. Ognuno con responsabilità
> ristrette, input e output dichiarati, e limiti espliciti.

---

## Indice

1. [Descrizione](#descrizione)
2. [Indice degli agenti](#indice-degli-agenti)
3. [Struttura di un file di agente](#struttura-di-un-file-di-agente)
4. [Perché agenti specializzati](#perché-agenti-specializzati)
5. [Handoff tra agenti](#handoff-tra-agenti)
6. [Limiti comuni](#limiti-comuni)
7. [Come si esegue un agente](#come-si-esegue-un-agente)
8. [Come si corregge un agente](#come-si-corregge-un-agente)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Un agente della Factory non è un assistente generico: è l'**esecutore di una fase**, con un compito
ristretto e verificabile. La specializzazione non è organizzativa, è funzionale: un agente con
responsabilità ristrette produce output ripetibile, che è la condizione perché il processo abbia
valore.

Decisione di riferimento: [ADR-0008](../architecture/decisions/0008-agent-orchestration.md).

---

## Indice degli agenti

| Fase | # | Agente | Responsabilità principale |
|---|---|---|---|
| — | 00 | [Protocollo agenti](00-agent-protocol.md) | regole comuni a tutti |
| 0 | 01 | [Foundation Agent](01-foundation-agent.md) | scaffolding, Foundation, ambiente, CI |
| 1 | 02 | [Business Analyst Agent](02-business-analyst-agent.md) | requisiti, entità, user story, glossario |
| 2 | 03 | [Architect Agent](03-architect-agent.md) | moduli, bounded context, ADR di progetto |
| 3 | 04 | [Database Agent](04-database-agent.md) | schema, migration, seeder, factory |
| 4 | 06 | [Backend Agent](06-backend-agent.md) | dominio, Action, Query, repository |
| 5 | 07 | [Filament Agent](07-filament-agent.md) | pannelli, resource, widget |
| 6 | 05 | [Frontend Agent](05-frontend-agent.md) | landing, CMS, Livewire, componenti |
| 7 | 08 | [Security Agent](08-security-agent.md) | policy, permessi, isolamento, hardening |
| 8 | 13 | [Testing Agent](13-testing-agent.md) | suite Pest completa |
| 9 | 11 | [Reviewer Agent](11-reviewer-agent.md) | revisione secondo le regole |
| 9 | 12 | [Claude Reviewer](12-claude-reviewer.md) | revisione indipendente |
| 10 | 09 | [Performance Agent](09-performance-agent.md) | query, indici, cache, code |
| 11 | 15 | [Refactoring Agent](15-refactoring-agent.md) | debito tecnico, duplicazioni |
| 12 | 10 | [Documentation Agent](10-documentation-agent.md) | README, ADR, manuale, API doc |
| 13 | 14 | [Deploy Agent](14-deploy-agent.md) | Docker, pipeline, rilascio |
| — | 16 | [Orchestrator Agent](16-orchestrator-agent.md) | coordinamento, `loop crea` |

La numerazione dei file segue l'ordine di catalogo; la colonna «Fase» segue l'ordine di esecuzione
del [master workflow](../workflows/00-master-workflow.md).

---

## Struttura di un file di agente

Ogni file contiene, obbligatoriamente:

| Sezione | Contenuto |
|---|---|
| **Identità** | chi è l'agente, in una frase |
| **Responsabilità** | cosa deve produrre, in modo verificabile |
| **Input** | artefatti richiesti, con la fase che li produce |
| **Output** | artefatti prodotti, con il percorso |
| **Limiti** | cosa **non** deve fare, esplicitamente |
| **Regole applicabili** | i documenti di `rules/` che lo vincolano |
| **Workflow** | la sequenza di passaggi che esegue |
| **Quality gate** | la checklist di uscita |
| **Prompt completo** | il prompt operativo, pronto all'uso |
| **Errori comuni** | difetti ricorrenti di quell'agente |

La sezione **Limiti** è obbligatoria e non decorativa: un agente che può fare tutto non è
affidabile.

---

## Perché agenti specializzati

| Un agente generico | Agenti specializzati |
|---|---|
| Output non ripetibile | output ripetibile per fase |
| Contesto diluito | contesto pertinente |
| Nessuna verifica intermedia | quality gate per fase |
| Revisione da parte di chi ha prodotto | revisione indipendente |
| Difetti amplificati a valle | difetti intercettati dove nascono |
| Impossibile capire dove migliorare | metriche per fase |

Il beneficio decisivo è l'ultimo: quando un difetto ricorre, le metriche per fase dicono **quale
prompt** correggere.

---

## Handoff tra agenti

Il passaggio tra fasi è **contrattuale**: la fase dichiara gli artefatti prodotti, la successiva li
riceve come input dichiarati.

```
Fase N                                    Fase N+1
──────                                    ────────
produce artefatti          ──────▶        riceve artefatti dichiarati
dichiara assunzioni        ──────▶        eredita le assunzioni
apre domande               ──────▶        eredita le domande aperte
supera il quality gate     ──────▶        può iniziare
```

**Nessuna fase inizia senza gli artefatti dichiarati in input.** Un agente che «immagina» l'input
produce lavoro da buttare.

---

## Limiti comuni

Validi per **tutti** gli agenti, oltre a quelli specifici:

| Limite | Motivo |
|---|---|
| Non deroga a una regola vincolante | non ha l'autorità |
| Non approva la propria modifica | serve una revisione indipendente |
| Non porta una ADR in stato `Accettata` | è una decisione umana |
| Non crea tag di versione | è un atto di rilascio |
| Non decide su ambiguità di dominio | serve il committente |
| Non modifica gli artefatti di una fase successiva | violerebbe l'handoff |
| Non nasconde un conflitto tra regole | deve segnalarlo |
| Non inventa requisiti mancanti | li dichiara come domande aperte |

---

## Come si esegue un agente

Ogni invocazione contiene quattro parti:

```markdown
Agisci come **<Nome> Agent** secondo `agents/NN-nome-agent.md`.

REGOLE APPLICABILI
- rules/…
- architecture/…

CONTESTO
- Project Brief: docs/project-brief.md
- Output della fase precedente: …
- Stato del repository: …

COMPITO
<cosa produrre, in quale forma>

VINCOLI
<vincoli specifici di questa invocazione>

GATE DI USCITA
checklists/…-checklist.md
```

Dettagli: [`docs/01-getting-started/04-running-the-agents.md`](../docs/01-getting-started/04-running-the-agents.md).

---

## Come si corregge un agente

| Sintomo | Correzione |
|---|---|
| Errore isolato | si corregge l'**output** |
| Errore che si ripete su progetti diversi | si corregge il **prompt dell'agente** |
| Errore che dipende da una regola non chiara | si corregge la **regola** |
| Errore che dipende da un requisito mancante | si corregge il **brief** |

Correggere sempre l'output significa pagare lo stesso costo ad ogni progetto. Ogni correzione a un
prompt è un contributo alla Factory e segue [`CONTRIBUTING.md`](../CONTRIBUTING.md).

I prompt sono **versionati**: ogni file dichiara la propria versione, e le modifiche compaiono nel
changelog.

---

## Esempi

### Esempio 1 — handoff corretto

Il Database Agent conclude la fase 3 dichiarando:

```markdown
### Artefatti prodotti
- database/migrations/tenant/2026_07_25_000001_create_batches_table.php
- database/factories/BatchFactory.php

### Assunzioni
- La partita IVA è unica per tenant, non globalmente. **Da confermare.**

### Domande aperte
- I lotti scaduti restano visibili nello storico dei movimenti?
```

Il Backend Agent riceve gli artefatti **e** le assunzioni: se progettasse ignorando la prima, il suo
lavoro sarebbe incoerente con lo schema.

### Esempio 2 — correzione al posto giusto

Il Backend Agent produce Action che ricevono `Request` invece di DTO, in tre progetti diversi.

Non è un errore di esecuzione: è una lacuna del prompt. Si aggiorna
`agents/06-backend-agent.md` con il vincolo esplicito e un esempio corretto, e si aggiunge la voce
alla checklist di fase.

---

## Best practice

- Un'invocazione, una fase.
- Fornire gli artefatti come file, non riassunti a parole.
- Dichiarare sempre il gate di uscita nell'invocazione.
- Leggere per prime le sezioni «assunzioni» e «domande aperte» del rapporto di fase.
- Registrare gli esiti: sono la base delle metriche sugli agenti.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Invocare senza gli artefatti della fase precedente | L'agente inventa il contesto | Verificare gli input |
| Chiedere più fasi in una volta | Output non verificabile per fase | Una fase per invocazione |
| Fornire l'intero repository | Attenzione diluita | Contesto pertinente |
| Ignorare le assunzioni dichiarate | Errori di dominio scoperti tardi | Leggerle e confermarle |
| Correggere sempre l'output | Costo ripetuto ad ogni progetto | Correggere il prompt |
| Superare un gate «per fretta» | Difetto amplificato a valle | Gate bloccanti |
| Agenti senza limiti espliciti | Output non revisionabile | Sezione «limiti» obbligatoria |

---

## Checklist

- [ ] Ho identificato la fase e l'agente competente.
- [ ] Gli artefatti di input esistono.
- [ ] L'invocazione contiene identità, regole, contesto, compito e gate.
- [ ] Il contesto fornito è pertinente e selezionato.
- [ ] Ho letto assunzioni e domande aperte del rapporto.
- [ ] Ho verificato il quality gate prima della fase successiva.
- [ ] Se l'errore si è ripetuto, ho corretto il prompt.

---

## Riferimenti

- [Protocollo agenti](00-agent-protocol.md)
- [Contratto `loop crea`](../prompts/loop-crea.md) · [Prompt](../prompts/README.md)
- [Master workflow](../workflows/00-master-workflow.md) · [Checklist](../checklists/README.md)
- [ADR-0008](../architecture/decisions/0008-agent-orchestration.md)
- [Eseguire gli agenti](../docs/01-getting-started/04-running-the-agents.md)
