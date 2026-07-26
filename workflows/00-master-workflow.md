# Master workflow

> Le quattordici fasi che portano da un Project Brief a un progetto pronto per staging.

---

## Indice

1. [Descrizione](#descrizione) 2. [Vista d'insieme](#vista-dinsieme) 3. [Le fasi](#le-fasi)
4. [Dipendenze](#dipendenze) 5. [Gate e rework](#gate-e-rework) 6. [Fermate](#fermate)
7. [Durata](#durata) 8. [Esempi](#esempi) 9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni) 11. [Checklist](#checklist) 12. [Riferimenti](#riferimenti)

---

## Descrizione

Il master workflow è la sequenza completa di produzione di un progetto. Ogni fase ha un agente
responsabile, input e output contrattuali, e un quality gate bloccante.

L'ordine non è arbitrario: ogni fase dipende solo dalle precedenti. Invertirne due significa
costruire su un fondamento che non esiste ancora.

---

## Vista d'insieme

```
Project Brief
     │
     ▼
┌─────────────────────────────────────────────────────────────────┐
│ 0  FONDAZIONE      scheletro, Foundation, Docker, CI            │
│ 1  ANALISI         requisiti, entità, casi d'uso, glossario     │
│ 2  ARCHITETTURA    contesti, moduli, contratti, ADR             │
│ 3  DATABASE        schema, migration, seeder, factory           │
│ 4  BACKEND         dominio, Action, Query, repository           │
│ 5  AMMINISTRAZIONE pannelli, resource, widget                   │
│ 6  FRONTEND        landing, CMS, portali                        │
│ 7  SICUREZZA       policy, permessi, isolamento verificato      │
│ 8  TESTING         suite completa                               │
│ 9  REVISIONE       conformità + revisione indipendente          │
│ 10 PRESTAZIONI     misurazione, ottimizzazione, regressioni     │
│ 11 REFACTORING     duplicazioni, debito registrato              │
│ 12 DOCUMENTAZIONE  README, API, manuale, changelog              │
│ 13 DEPLOY          immagini, pipeline, rollback, runbook        │
└─────────────────────────────────────────────────────────────────┘
     │
     ▼
Progetto pronto per staging
```

---

## Le fasi

| # | Fase | Agente | Output principale | Gate |
|---|---|---|---|---|
| 0 | [Fondazione](01-phase-foundation.md) | Foundation | scheletro funzionante e vuoto | [foundation](../checklists/foundation-checklist.md) |
| 1 | [Analisi](02-phase-analysis.md) | Business Analyst | requisiti verificabili | [analysis](../checklists/analysis-checklist.md) |
| 2 | [Architettura](03-phase-architecture.md) | Architect | moduli, contratti, ADR | [architecture](../checklists/architecture-checklist.md) |
| 3 | [Database](04-phase-database.md) | Database | schema completo | [database](../checklists/database-checklist.md) |
| 4 | [Backend](05-phase-backend.md) | Backend | sistema funzionante senza interfaccia | [backend](../checklists/backend-checklist.md) |
| 5 | [Amministrazione](06-phase-filament.md) | Filament | pannelli operativi | [filament](../checklists/filament-checklist.md) |
| 6 | [Frontend](07-phase-frontend.md) | Frontend | presenza pubblica e portali | [frontend](../checklists/frontend-checklist.md) |
| 7 | [Sicurezza](08-phase-security.md) | Security | isolamento verificato | [security](../checklists/security-checklist.md) |
| 8 | [Testing](09-phase-testing.md) | Testing | suite entro le soglie | [testing](../checklists/testing-checklist.md) |
| 9 | [Revisione](10-phase-review.md) | Reviewer + Claude Reviewer | rapporti di revisione | [code-review](../checklists/code-review-checklist.md) |
| 10 | [Prestazioni](11-phase-performance.md) | Performance | obiettivi raggiunti | [performance](../checklists/performance-checklist.md) |
| 11 | [Refactoring](12-phase-refactoring.md) | Refactoring | struttura ripulita | — |
| 12 | [Documentazione](13-phase-documentation.md) | Documentation | documentazione completa | [documentation](../checklists/documentation-checklist.md) |
| 13 | [Deploy](14-phase-deploy.md) | Deploy | rilascio pronto | [release](../checklists/release-checklist.md) |

---

## Dipendenze

```
0 ──▶ 1 ──▶ 2 ──▶ 3 ──▶ 4 ──┬──▶ 5 ──┐
                             │        ├──▶ 7 ──▶ 8 ──▶ 9 ──▶ 10 ──▶ 11 ──▶ 12 ──▶ 13
                             └──▶ 6 ──┘
```

| Dipendenza | Motivo |
|---|---|
| 1 dopo 0 | serve un repository dove scrivere i requisiti |
| 2 dopo 1 | l'architettura discende dai requisiti |
| 3 dopo 2 | lo schema discende dai bounded context |
| 4 dopo 3 | il codice persiste su uno schema esistente |
| 5 e 6 dopo 4 | le interfacce invocano Action già esistenti |
| 5 e 6 in parallelo | non dipendono l'una dall'altra |
| 7 dopo 5 e 6 | la sicurezza verifica anche i punti di ingresso |
| 8 dopo 7 | i test coprono anche le Policy |
| 9 dopo 8 | si revisiona un progetto completo |
| 10 dopo 9 | si ottimizza dopo aver corretto |
| 11 dopo 10 | si rifattorizza dopo aver ottimizzato |
| 12 dopo 11 | si documenta lo stato finale |
| 13 dopo 12 | si rilascia ciò che è documentato |

Le fasi 5 e 6 sono l'unica parallelizzazione ammessa.

---

## Gate e rework

```
FASE N ──▶ GATE ──┬── verde ──▶ FASE N+1
                  │
                  └── rosso ──▶ REWORK ──┬── tentativo ≤ 3 ──▶ GATE
                                          │
                                          └── tentativo = 4 ──▶ FERMATA
```

Il rework è **chirurgico**: indica i punti respinti e dichiara cosa non toccare.

Al quarto tentativo il processo si ferma: tre fallimenti consecutivi indicano che il problema è
nella fase **precedente**, non in quella corrente.

---

## Fermate

| # | Caso | Riprende con |
|---|---|---|
| 1 | Brief incompleto nelle sezioni bloccanti | brief completato |
| 2 | Ambiguità di dominio | risposta del committente |
| 3 | Regola di business non specificata | risposta del committente |
| 4 | Conflitto tra requisito e regola vincolante | ADR o deroga approvata |
| 5 | Requisito normativo dubbio | risposta del committente |
| 6 | Tre rework falliti | diagnosi umana |

---

## Durata

Ordini di grandezza per un progetto di media complessità (5-8 entità, 3-4 moduli):

| Fase | Durata indicativa |
|---|---|
| 0 Fondazione | 30-60 min |
| 1 Analisi | 2-4 h (dipende dalla qualità del brief) |
| 2 Architettura | 1-2 h |
| 3 Database | 1-3 h |
| 4 Backend | 4-8 h |
| 5 Amministrazione | 2-4 h |
| 6 Frontend | 2-6 h |
| 7 Sicurezza | 1-3 h |
| 8 Testing | 2-4 h |
| 9 Revisione | 1-2 h |
| 10 Prestazioni | 1-3 h |
| 11 Refactoring | 1-2 h |
| 12 Documentazione | 2-4 h |
| 13 Deploy | 2-4 h |

Le fermate non sono conteggiate: dipendono dai tempi di risposta del committente, e sono spesso la
voce più lunga.

---

## Esempi

### Esempio 1 — esecuzione con fermata

```
✓ Fase 0  Foundation ........... gate 13/13, primo tentativo, 42 min
✓ Fase 1  Business Analyst ..... gate 11/11, primo tentativo, 3 h
⏸ Fase 2  Architect ............ FERMATA (domanda di dominio)
   → risposta ricevuta dopo 1 giorno
✓ Fase 2  Architect ............ gate 13/13, primo tentativo, 1 h 20
✓ Fase 3  Database ............. gate 16/18 → rework → 18/18, 2 h 10
…
```

### Esempio 2 — esecuzione parziale deliberata

Su un dominio nuovo, si esegue `loop crea --to=4`: fondazione, analisi, architettura, database,
backend. Si rivede il dominio con il committente **prima** di costruirci sopra le interfacce.

Costa una pausa; evita di rifare cinque fasi se il modello di dominio è sbagliato.

---

## Best practice

- Investire sulla fase 1: ogni ambiguità non risolta si amplifica nelle fasi successive.
- Su un dominio nuovo, fermarsi dopo la fase 4 per una revisione umana del modello.
- Non saltare la fase 7: è quella che si salta più spesso e protegge dai difetti più gravi.
- Registrare i tempi e gli esiti dei gate: servono a migliorare i prompt.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Avviare con brief incompleto | Assunzioni su regole di business | Verifica delle sezioni bloccanti |
| Invertire due fasi | Si costruisce su un fondamento inesistente | Rispettare le dipendenze |
| Saltare la fase 7 | Funzionalità aperte o invisibili | Sequenza completa |
| Gate saltato | Difetto amplificato a valle | Gate bloccanti |
| Quarto rework | Il problema resta a monte | Fermata e diagnosi |
| Tempi non registrati | Impossibile migliorare le stime | Registro di esecuzione |

---

## Checklist

- [ ] Precondizioni verificate prima della fase 0.
- [ ] Ogni fase ha ricevuto gli input dichiarati.
- [ ] Ogni gate è stato verificato voce per voce.
- [ ] I rework sono stati chirurgici, massimo tre per fase.
- [ ] Le fermate hanno prodotto domande specifiche con opzioni.
- [ ] Il registro di esecuzione è completo.
- [ ] Tutte le postcondizioni di `loop crea` sono soddisfatte.

---

## Riferimenti

- [Workflow](README.md) · [Contratto `loop crea`](../prompts/loop-crea.md)
- [Orchestrator Agent](../agents/16-orchestrator-agent.md)
- [Checklist](../checklists/README.md)
- [ADR-0008](../architecture/decisions/0008-agent-orchestration.md)
