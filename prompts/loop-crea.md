# Contratto del comando `loop crea`

> La specifica formale del comando che genera un progetto completo. Definisce cosa serve prima, cosa
> accade durante, cosa si ottiene alla fine, e quando il processo si ferma.

| | |
|---|---|
| **Versione contratto** | 1.0.0 |
| **Esecutore** | [Orchestrator Agent](../agents/16-orchestrator-agent.md) |

---

## Indice

1. [Descrizione](#descrizione)
2. [Sintassi](#sintassi)
3. [Precondizioni](#precondizioni)
4. [Le quattordici fasi](#le-quattordici-fasi)
5. [Postcondizioni](#postcondizioni)
6. [Fermate](#fermate)
7. [Rework](#rework)
8. [Esiti possibili](#esiti-possibili)
9. [Comandi correlati](#comandi-correlati)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

`loop crea` è il comando che avvia la generazione completa di un progetto: dall'analisi dei requisiti
alla preparazione del rilascio, attraverso quattordici fasi con quality gate bloccanti.

Non è un generatore di codice: è l'esecuzione di un **processo**, con verifiche intermedie e fermate
esplicite quando serve una decisione umana.

---

## Sintassi

```
loop crea "<Nome Progetto>" [opzioni]
```

| Opzione | Effetto | Default |
|---|---|---|
| `--brief=<percorso>` | percorso del Project Brief | `docs/project-brief.md` |
| `--from=<fase>` | riprende da una fase specifica | 0 |
| `--to=<fase>` | si ferma dopo una fase | 13 |
| `--only=<fase>` | esegue una sola fase | — |
| `--dry-run` | produce il piano senza eseguire | disattivo |
| `--strict` | qualunque gate fallito interrompe, senza rework | disattivo |

Comandi derivati:

```
loop fase <n>              esegue una singola fase
loop rivedi                esegue la sola fase 9 (revisione)
loop modulo "<nome>"       aggiunge un modulo a un progetto esistente
loop stato                 mostra lo stato di avanzamento
```

---

## Precondizioni

Il comando **non parte** se una di queste non è soddisfatta:

| # | Precondizione | Verifica |
|---|---|---|
| 1 | Repository del progetto esistente e vuoto | `git status` |
| 2 | Project Brief presente | esistenza del file |
| 3 | Sezioni bloccanti del brief compilate | analisi del contenuto |
| 4 | Ambiente di sviluppo funzionante | Docker, Composer, Node |
| 5 | Accesso al registro Composer privato | `composer config` |
| 6 | Versione della Factory dichiarata | tag o parametro |
| 7 | Interlocutore di dominio disponibile | dichiarazione |

Sezioni bloccanti del brief: scopo, attori e ruoli, entità principali, casi d'uso primari, regole di
business, vincoli normativi, volumi attesi, cosa il software **non** fa.

La settima precondizione è la più trascurata: senza qualcuno che possa rispondere alle domande di
dominio, il processo si ferma alla prima ambiguità e resta fermo.

---

## Le quattordici fasi

| # | Fase | Agente | Output principale | Gate |
|---|---|---|---|---|
| 0 | Fondazione | [Foundation](../agents/01-foundation-agent.md) | scheletro funzionante | [foundation](../checklists/foundation-checklist.md) |
| 1 | Analisi | [Business Analyst](../agents/02-business-analyst-agent.md) | requisiti verificabili | [analysis](../checklists/analysis-checklist.md) |
| 2 | Architettura | [Architect](../agents/03-architect-agent.md) | moduli, contratti, ADR | [architecture](../checklists/architecture-checklist.md) |
| 3 | Database | [Database](../agents/04-database-agent.md) | schema, migration, factory | [database](../checklists/database-checklist.md) |
| 4 | Backend | [Backend](../agents/06-backend-agent.md) | dominio, Action, Query | [backend](../checklists/backend-checklist.md) |
| 5 | Amministrazione | [Filament](../agents/07-filament-agent.md) | pannelli e resource | [filament](../checklists/filament-checklist.md) |
| 6 | Frontend | [Frontend](../agents/05-frontend-agent.md) | landing, CMS, portali | [frontend](../checklists/frontend-checklist.md) |
| 7 | Sicurezza | [Security](../agents/08-security-agent.md) | policy, isolamento verificato | [security](../checklists/security-checklist.md) |
| 8 | Testing | [Testing](../agents/13-testing-agent.md) | suite completa | [testing](../checklists/testing-checklist.md) |
| 9 | Revisione | [Reviewer](../agents/11-reviewer-agent.md) + [Claude Reviewer](../agents/12-claude-reviewer.md) | rapporti di revisione | [code-review](../checklists/code-review-checklist.md) |
| 10 | Prestazioni | [Performance](../agents/09-performance-agent.md) | ottimizzazioni misurate | [performance](../checklists/performance-checklist.md) |
| 11 | Refactoring | [Refactoring](../agents/15-refactoring-agent.md) | struttura ripulita, debito registrato | — |
| 12 | Documentazione | [Documentation](../agents/10-documentation-agent.md) | documentazione completa | [documentation](../checklists/documentation-checklist.md) |
| 13 | Deploy | [Deploy](../agents/14-deploy-agent.md) | pipeline e procedure | [release](../checklists/release-checklist.md) |

Dettaglio di ciascuna: [`workflows/`](../workflows/README.md).

---

## Postcondizioni

Al termine con esito `completato`, il progetto soddisfa **tutte** queste condizioni:

| # | Condizione | Verifica |
|---|---|---|
| 1 | `composer qa` verde | esecuzione |
| 2 | Copertura ≥ 80%; Action e Policy al 100% | rapporto |
| 3 | Test di isolamento tenant verdi per ogni entità | esecuzione |
| 4 | Test di architettura verdi | esecuzione |
| 5 | Pipeline CI verde | esecuzione |
| 6 | Pannelli Super Admin e Tenant Admin funzionanti | prova |
| 7 | API documentate in OpenAPI | esistenza e validazione |
| 8 | Documentazione completa, link verificati | script |
| 9 | Rollback provato su staging | registro |
| 10 | Nessuna domanda di dominio senza risposta | `open-questions.md` |
| 11 | Nessuna assunzione non confermata su regole di business | registro |
| 12 | Deroghe attive dichiarate con scadenza | `CLAUDE.md` |

Il progetto è **pronto per staging**, non per la produzione: il rilascio è un atto autorizzato.

---

## Fermate

Il processo si ferma e chiede in sei casi:

| # | Caso | Riprende con |
|---|---|---|
| 1 | Brief incompleto | brief completato |
| 2 | Ambiguità di dominio | risposta del committente |
| 3 | Regola di business non specificata | risposta del committente |
| 4 | Conflitto tra requisito e regola vincolante | ADR o deroga approvata |
| 5 | Requisito normativo dubbio | risposta del committente |
| 6 | Tre rework falliti | diagnosi umana |

Una fermata **non è un fallimento**: è il funzionamento corretto del processo su una decisione che
non compete a un agente.

Formato: la domanda espone le **opzioni** e le loro **conseguenze tecniche**, e indica chi può
rispondere. Vedi [Orchestrator Agent](../agents/16-orchestrator-agent.md#fermate).

---

## Rework

Quando un gate fallisce:

```
tentativo 1 → rework chirurgico sui punti respinti
tentativo 2 → rework chirurgico
tentativo 3 → rework chirurgico
tentativo 4 → FERMATA: il problema è a monte, serve diagnosi umana
```

Il rework è **chirurgico**: indica i punti respinti e dichiara cosa **non** toccare. Un rework
generico («rifai meglio») fa rigenerare anche ciò che era corretto.

---

## Esiti possibili

| Esito | Significato | Azione |
|---|---|---|
| `completato` | 14 fasi con gate verde, nessuna domanda aperta | verifica umana, poi staging |
| `in attesa` | fermata su una domanda | rispondere e riprendere con `--from` |
| `bloccato` | tre rework falliti su una fase | diagnosi della fase precedente |
| `interrotto` | precondizione non soddisfatta | soddisfare la precondizione |
| `parziale` | eseguito con `--to`, fasi rimanenti da fare | riprendere con `--from` |

---

## Esempi

### Esempio 1 — esecuzione completa

```
loop crea "Magazzino Sanitario"
```

```
✓ Precondizioni verificate
✓ Fase 0  Foundation ................ gate 13/13, primo tentativo
✓ Fase 1  Business Analyst .......... gate 11/11, primo tentativo
⏸ Fase 2  Architect ................. FERMATA

  Domanda: i lotti di articoli diversi possono condividere un'ubicazione?
  (a) sì → ubicazione con relazione molti-a-molti verso i lotti
  (b) no → ubicazione con relazione uno-a-molti, vincolo di unicità

  Chi può rispondere: responsabile di magazzino.
  Stato: fasi 0-1 completate, fase 2 sospesa.
```

Dopo la risposta:

```
loop crea "Magazzino Sanitario" --from=2
```

### Esempio 2 — esecuzione parziale

```
loop crea "Help Desk" --to=4
```

Esegue fondazione, analisi, architettura, database e backend. Si ferma prima delle interfacce, per
consentire una revisione umana del dominio prima di costruirci sopra.

### Esempio 3 — singola fase su progetto esistente

```
loop fase 10
```

Esegue la sola fase prestazioni su un progetto già generato.

---

## Best practice

- Investire sul Project Brief: è l'unico punto in cui un'ora in più cambia il risultato.
- Usare `--to=4` sul primo progetto di un dominio nuovo: rivedere il dominio prima delle interfacce.
- Rispondere alle domande in modo specifico e scritto: la risposta diventa documentazione.
- Non modificare a mano gli artefatti durante l'esecuzione: la fase successiva potrebbe rigenerarli.
- Leggere il registro di esecuzione: le assunzioni sono lì.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Avviare con brief incompleto | Il processo si ferma subito, o produce assunzioni | Completare le sezioni bloccanti |
| Rispondere «fai tu» a una domanda di dominio | Decisione di business presa da un agente | Rispondere in modo specifico |
| Modificare a mano durante l'esecuzione | Conflitti con la fase successiva | Attendere il termine della fase |
| Considerare il progetto pronto per la produzione | Il rilascio è un atto autorizzato | Verifica umana e staging |
| Ignorare le assunzioni nel registro | Errori di dominio scoperti tardi | Leggerle e confermarle |
| Riavviare da zero dopo una fermata | Si perde il lavoro fatto | `--from=<fase>` |

---

## Checklist

**Prima di eseguire**
- [ ] Repository creato, brief compilato nelle sezioni bloccanti.
- [ ] Ambiente funzionante, accesso al registro Composer.
- [ ] Interlocutore di dominio disponibile.

**Durante**
- [ ] Rispondo alle fermate in modo specifico e scritto.
- [ ] Non modifico a mano gli artefatti.

**Dopo**
- [ ] Tutte le postcondizioni soddisfatte.
- [ ] Assunzioni lette e confermate.
- [ ] Nessuna domanda aperta residua.
- [ ] Verifica umana prima di staging.

---

## Riferimenti

- [Orchestrator Agent](../agents/16-orchestrator-agent.md) · [Indice degli agenti](../agents/README.md)
- [Master workflow](../workflows/00-master-workflow.md)
- [Project Brief](../docs/06-reference/01-project-brief-template.md)
- [Avviare un nuovo progetto](../docs/01-getting-started/01-new-project.md)
- [ADR-0008](../architecture/decisions/0008-agent-orchestration.md)
