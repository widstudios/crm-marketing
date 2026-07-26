# WidStudios AI Factory

> La piattaforma industriale con cui WidStudios progetta, sviluppa, testa, revisiona e rilascia
> **tutti** i propri software — sempre con gli stessi standard, la stessa architettura e la stessa qualità.

![Standard](https://img.shields.io/badge/standard-enterprise-blue)
![Stack](https://img.shields.io/badge/stack-Laravel%2012%20LTS%20%7C%20PHP%208.4-red)
![Architettura](https://img.shields.io/badge/architettura-multitenant-green)

---

## Indice

1. [Che cos'è la AI Factory](#che-cosè-la-ai-factory)
2. [Il comando finale: `loop crea`](#il-comando-finale-loop-crea)
3. [Struttura del repository](#struttura-del-repository)
4. [Come si usa](#come-si-usa)
5. [Lo stack tecnologico](#lo-stack-tecnologico)
6. [I dodici principi](#i-dodici-principi)
7. [Software che nascono da qui](#software-che-nascono-da-qui)
8. [Mappa della documentazione](#mappa-della-documentazione)
9. [Stato del repository](#stato-del-repository)
10. [Convenzioni di contribuzione](#convenzioni-di-contribuzione)
11. [Riferimenti](#riferimenti)

---

## Che cos'è la AI Factory

La AI Factory **non è un software**. È la *fabbrica* che produce software.

È l'insieme di cinque asset che, combinati, permettono a un agente AI (o a uno sviluppatore umano)
di partire da una frase in linguaggio naturale e arrivare a un'applicazione Laravel multitenant
completa, testata e pronta al deploy, senza mai reinventare decisioni già prese:

| Asset | Cartella | Cosa contiene |
|---|---|---|
| **Conoscenza** | `docs/`, `architecture/` | Come si progettano i nostri software, e perché |
| **Regole** | `rules/`, `checklists/` | Cosa è accettabile e cosa non lo è, in modo verificabile |
| **Agenti** | `agents/`, `prompts/` | Chi fa cosa, con quale prompt, con quali limiti |
| **Codice** | `foundation/`, `templates/`, `modules/` | Ciò che non va mai riscritto due volte |
| **Processo** | `workflows/`, `deployment/` | La sequenza che porta dall'idea alla produzione |

Il valore della AI Factory è **la ripetibilità**. Due progetti diversi, avviati a mesi di distanza,
da persone diverse, devono risultare strutturalmente identici: stessa architettura, stessi nomi,
stessi test, stessa pipeline. La creatività si concentra sul dominio applicativo, mai
sull'infrastruttura.

> **Regola zero.** Se una decisione tecnica è già stata presa in questo repository, non si ridiscute
> nel singolo progetto. Si cambia qui, una volta, per tutti.

---

## Il comando finale: `loop crea`

L'obiettivo di lungo periodo è che l'unico input necessario sia:

```
loop crea "Magazzino Sanitario"
```

Da quella riga la Factory esegue il ciclo completo descritto in
[`workflows/00-master-workflow.md`](workflows/00-master-workflow.md):

```
loop crea "Nome Progetto"
        │
        ├─ FASE 0  Foundation Agent .......... scaffolding + foundation + CI
        ├─ FASE 1  Business Analyst Agent .... requisiti, entità, user story
        ├─ FASE 2  Architect Agent ........... moduli, bounded context, ADR
        ├─ FASE 3  Database Agent ............ schema landlord + tenant, migration
        ├─ FASE 4  Backend Agent ............. domain, service, action, repository
        ├─ FASE 5  Filament Agent ............ pannelli, resource, widget
        ├─ FASE 6  Frontend Agent ............ landing, CMS, Livewire, Tailwind
        ├─ FASE 7  Security Agent ............ policy, tenant isolation, hardening
        ├─ FASE 8  Testing Agent ............. Pest, feature, unit, architettura
        ├─ FASE 9  Reviewer + Claude Reviewer  revisione incrociata
        ├─ FASE 10 Performance Agent ......... query, indici, cache, N+1
        ├─ FASE 11 Refactoring Agent ......... debito tecnico, duplicazioni
        ├─ FASE 12 Documentation Agent ....... README, ADR, manuale, API doc
        └─ FASE 13 Deploy Agent .............. Docker, pipeline, release
```

Ogni fase ha **input contrattuali**, **output verificabili** e un **quality gate** che deve passare
prima di avanzare. Il contratto completo è in
[`prompts/loop-crea.md`](prompts/loop-crea.md) e
[`agents/16-orchestrator-agent.md`](agents/16-orchestrator-agent.md).

---

## Struttura del repository

```
widstudios-ai-factory/
├── docs/               Documentazione trasversale: introduzione, guide, reference
├── architecture/       Architettura di riferimento + ADR (Architecture Decision Records)
├── rules/              Standard aziendali vincolanti, uno per tecnologia/pattern
├── agents/             I 16 agenti AI: responsabilità, I/O, limiti, workflow, prompt
├── prompts/            Prompt riutilizzabili: di sistema, di fase, snippet
├── foundation/         Il pacchetto PHP riutilizzabile: codice che non si riscrive mai
├── templates/          Stub pronti all'uso per ogni artefatto Laravel/Filament
├── modules/            Catalogo dei moduli riutilizzabili + blueprint di modulo
├── workflows/          Il processo, fase per fase, con quality gate
├── checklists/         Liste di verifica operative, spuntabili
├── deployment/         Docker, CI/CD, ambienti, runbook operativi
├── examples/           Casi d'uso completi e codice esemplificativo
├── governance/         Versionamento della Factory, ownership, evoluzione
├── tooling/            Script e configurazioni condivise (Pint, PHPStan, Rector…)
└── legacy/             Prototipo CRM PHP preesistente, congelato (vedi legacy/README.md)
```

Ogni cartella ha un proprio `README.md` che ne è l'indice autorevole.

---

## Come si usa

### Scenario A — nuovo progetto (percorso completo)

1. Leggi [`docs/01-getting-started/01-new-project.md`](docs/01-getting-started/01-new-project.md).
2. Compila il [Project Brief](docs/06-reference/01-project-brief-template.md).
3. Esegui `loop crea "Nome Progetto"` (o lancia manualmente gli agenti in sequenza).
4. Ad ogni fase, verifica il quality gate corrispondente in [`checklists/`](checklists/README.md).

### Scenario B — nuovo modulo su progetto esistente

1. Leggi [`modules/README.md`](modules/README.md) e il [blueprint](modules/_blueprint/README.md).
2. Copia il blueprint, compila il `module.json`.
3. Segui [`workflows/20-module-workflow.md`](workflows/20-module-workflow.md).

### Scenario C — sono uno sviluppatore umano

Leggi, in quest'ordine:
[Vision](docs/00-introduction/01-vision.md) →
[Architettura](architecture/README.md) →
[Regole PHP](rules/php.md) e [Laravel](rules/laravel.md) →
[Foundation](foundation/README.md) →
[Checklist di code review](checklists/code-review-checklist.md).

### Scenario D — sono un agente AI

Il tuo punto di ingresso è [`CLAUDE.md`](CLAUDE.md), poi
[`agents/00-agent-protocol.md`](agents/00-agent-protocol.md).
Non iniziare a scrivere codice prima di aver letto entrambi.

---

## Lo stack tecnologico

Lo stack è **vincolante**: nessun progetto lo modifica senza una ADR approvata.

| Livello | Tecnologia | Versione minima |
|---|---|---|
| Linguaggio | PHP | 8.4 |
| Framework | Laravel | 12.x LTS |
| Admin panel | Filament | 4.x |
| Componenti reattivi | Livewire | 3.x |
| CSS | Tailwind CSS | 4.x |
| JS | Alpine.js | 3.x |
| Build | Vite | 6.x |
| DB produzione | MySQL 8 / MariaDB 11 | — |
| DB sviluppo/test | SQLite | 3.45 |
| Cache / Queue / Lock | Redis | 7.x |
| Container | Docker + Compose | — |
| Test | Pest 3 su PHPUnit 11 | — |
| Style | Laravel Pint | — |
| Analisi statica | PHPStan / Larastan livello 8 | — |

Dettagli e motivazioni: [`docs/00-introduction/03-technology-stack.md`](docs/00-introduction/03-technology-stack.md).

---

## I dodici principi

1. **Multitenancy sempre.** Anche un software monocliente nasce multitenant.
2. **Isolamento dei dati prima di tutto.** Un tenant non deve poter vedere l'altro, mai, per costruzione.
3. **Nessuna duplicazione.** Se serve due volte, sta nella Foundation.
4. **Moduli indipendenti.** Un modulo si disinstalla senza rompere il resto.
5. **Il dominio non conosce il framework.** Laravel è un dettaglio infrastrutturale.
6. **Ogni scrittura è un'Action.** Ogni lettura complessa è un Query object.
7. **Niente logica nei controller.** Il controller traduce HTTP, nient'altro.
8. **Test come contratto.** Nessuna feature senza test, nessun bug senza test di regressione.
9. **Tutto tracciato.** Audit log per i dati sensibili, activity log per il comportamento.
10. **Sicuro per default.** Deny-by-default su policy, validazione e configurazione.
11. **Documentazione contestuale.** Il documento vive accanto a ciò che descrive.
12. **Automazione dei controlli.** Se una regola non è verificabile da una pipeline, è un'opinione.

Approfondimento: [`docs/00-introduction/04-principles.md`](docs/00-introduction/04-principles.md).

---

## Software che nascono da qui

La Factory è volutamente **generica**. Questi sono i verticali già previsti:

| Software | Dominio | Moduli riutilizzati previsti |
|---|---|---|
| Magazzino Sanitario | inventario, lotti, scadenze, dispositivi medici | tenancy, auth, catalog, inventory, audit, reporting |
| CRM | vendite, pipeline, contatti | tenancy, auth, contacts, pipeline, activity, reporting |
| Project Management | progetti, task, timesheet | tenancy, auth, projects, tasks, calendar, documents |
| Help Desk | ticket, SLA, knowledge base | tenancy, auth, tickets, sla, cms, notifications |
| CAF | pratiche, scadenze, documenti | tenancy, auth, cases, documents, calendar, audit |
| Gestione Documentale | archiviazione, versioni, firma | tenancy, auth, documents, storage, search, audit |
| Tracciabilità RFID/UWB | asset tracking, telemetria | tenancy, auth, assets, telemetry, geo, reporting |

Nessuno di questi verticali è implementato in questo repository: qui vive solo ciò che è
**comune a tutti**.

---

## Mappa della documentazione

| Se vuoi… | Vai a |
|---|---|
| capire perché esiste la Factory | [`docs/00-introduction/01-vision.md`](docs/00-introduction/01-vision.md) |
| avviare il primo progetto | [`docs/01-getting-started/01-new-project.md`](docs/01-getting-started/01-new-project.md) |
| capire la multitenancy | [`architecture/03-multitenancy-overview.md`](architecture/03-multitenancy-overview.md) |
| conoscere le regole di codice | [`rules/README.md`](rules/README.md) |
| lanciare o scrivere un agente | [`agents/README.md`](agents/README.md) |
| riusare codice | [`foundation/README.md`](foundation/README.md) |
| generare un artefatto | [`templates/README.md`](templates/README.md) |
| aggiungere un modulo | [`modules/README.md`](modules/README.md) |
| rilasciare in produzione | [`deployment/README.md`](deployment/README.md) |
| verificare la qualità | [`checklists/README.md`](checklists/README.md) |

Indice completo e navigabile: [`docs/README.md`](docs/README.md).

---

## Stato del repository

Questo repository è in **costruzione incrementale**. Lo stato di avanzamento per area è tracciato in
[`governance/roadmap.md`](governance/roadmap.md); il registro delle modifiche in
[`CHANGELOG.md`](CHANGELOG.md).

Il prototipo CRM in PHP vanilla che occupava questo repository è stato spostato, senza modifiche,
in [`legacy/`](legacy/README.md). Non è più il progetto di riferimento: resta come archivio storico
e come esempio di ciò che la Factory sostituisce.

---

## Convenzioni di contribuzione

- Ogni modifica alla Factory segue [`CONTRIBUTING.md`](CONTRIBUTING.md).
- Le decisioni strutturali si registrano come ADR in [`architecture/decisions/`](architecture/decisions/README.md).
- Il formato dei commit è definito in [`rules/commit.md`](rules/commit.md).
- La Factory è versionata: vedi [`governance/versioning.md`](governance/versioning.md).

---

## Riferimenti

**Da dove si comincia**

| Domanda | Documento |
|---|---|
| Che cos'è questo repository | [`docs/00-introduction/02-what-is-ai-factory.md`](docs/00-introduction/02-what-is-ai-factory.md) |
| Devo lavorarci: da dove parto | [`docs/06-reference/06-reading-paths.md`](docs/06-reference/06-reading-paths.md) |
| Sono un agente AI | [`CLAUDE.md`](CLAUDE.md) → [`agents/00-agent-protocol.md`](agents/00-agent-protocol.md) |
| Devo avviare un progetto nuovo | [`docs/01-getting-started/01-new-project.md`](docs/01-getting-started/01-new-project.md) |

**Gli indici delle aree**

- [`docs/README.md`](docs/README.md) — spiegazioni, guide, riferimento
- [`architecture/README.md`](architecture/README.md) — architettura e ADR
- [`rules/README.md`](rules/README.md) — standard vincolanti
- [`agents/README.md`](agents/README.md) — i sedici agenti
- [`prompts/README.md`](prompts/README.md) — `loop crea` e la libreria dei prompt
- [`foundation/README.md`](foundation/README.md) — il pacchetto PHP riutilizzabile
- [`templates/README.md`](templates/README.md) — gli stub per ogni artefatto
- [`modules/README.md`](modules/README.md) — il catalogo dei moduli
- [`workflows/README.md`](workflows/README.md) — le quattordici fasi
- [`checklists/README.md`](checklists/README.md) — i quality gate
- [`governance/README.md`](governance/README.md) — ruoli, versionamento, roadmap

---

*WidStudios — costruire una volta, riusare per anni.*
