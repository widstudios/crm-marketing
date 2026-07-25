# Documentazione della AI Factory

> Indice generale e navigabile di tutta la conoscenza della piattaforma. Se non sai da dove
> iniziare, inizia da qui.

---

## Indice

1. [Descrizione](#descrizione)
2. [Come è organizzata la documentazione](#come-è-organizzata-la-documentazione)
3. [Percorsi di lettura](#percorsi-di-lettura)
4. [Indice completo](#indice-completo)
5. [Documentazione fuori da `docs/`](#documentazione-fuori-da-docs)
6. [Best practice di consultazione](#best-practice-di-consultazione)
7. [Errori comuni](#errori-comuni)
8. [Checklist](#checklist)
9. [Riferimenti](#riferimenti)

---

## Descrizione

La documentazione della Factory è divisa per **funzione del lettore**, non per tecnologia. Un
lettore non cerca «Laravel»: cerca «come si sviluppa una feature», «come si rilascia», «perché
facciamo così».

Di conseguenza esistono quattro tipi di documento, con scopi distinti che non vanno mescolati:

| Tipo | Risponde a | Dove vive | Tono |
|---|---|---|---|
| **Spiegazione** | Perché? | `docs/00-introduction/`, `architecture/` | discorsivo, motiva |
| **Guida pratica** | Come si fa? | `docs/01-getting-started/`, `docs/03-development/` | procedurale, passo-passo |
| **Regola** | Cosa è obbligatorio? | `rules/` | prescrittivo, verificabile |
| **Riferimento** | Qual è il valore esatto? | `docs/06-reference/` | tabellare, consultabile |

Confondere i tipi è l'errore più frequente: una guida che diventa normativa, o una regola che
diventa un saggio, perdono entrambe la loro utilità.

---

## Come è organizzata la documentazione

```
docs/
├── 00-introduction/    Perché esiste la Factory, principi, stack, glossario
├── 01-getting-started/ Dai primi comandi al primo modulo funzionante
├── 02-conventions/     Convenzioni redazionali e di struttura dei progetti
├── 03-development/     Guide operative di sviluppo, per attività
├── 04-quality/         Testing, review, analisi statica, performance, sicurezza
├── 05-operations/      Ambienti, rilasci, monitoraggio, incidenti, tenant
└── 06-reference/       Template, cataloghi, comandi, configurazioni, troubleshooting
```

---

## Percorsi di lettura

### Sono nuovo in azienda (~3 ore)

1. [Visione](00-introduction/01-vision.md)
2. [Che cos'è la AI Factory](00-introduction/02-what-is-ai-factory.md)
3. [I dodici principi](00-introduction/04-principles.md)
4. [Stack tecnologico](00-introduction/03-technology-stack.md)
5. [Struttura del repository](00-introduction/05-repository-structure.md)
6. [Glossario](00-introduction/06-glossary.md)
7. [Ambiente locale](01-getting-started/02-local-environment.md)

### Devo sviluppare la mia prima feature (~2 ore)

1. [Ciclo di vita dello sviluppo](03-development/01-development-lifecycle.md)
2. [Guida allo sviluppo di una feature](03-development/02-feature-development-guide.md)
3. [Regole PHP](../rules/php.md) e [Laravel](../rules/laravel.md)
4. [Action Pattern](../rules/action-pattern.md)
5. [Strategia di testing](04-quality/01-testing-strategy.md)
6. [Checklist di code review](../checklists/code-review-checklist.md)

### Devo avviare un progetto nuovo (~4 ore)

1. [Nuovo progetto](01-getting-started/01-new-project.md)
2. [Project Brief](06-reference/01-project-brief-template.md)
3. [Master workflow](../workflows/00-master-workflow.md)
4. [Contratto `loop crea`](../prompts/loop-crea.md)
5. [Architettura di riferimento](../architecture/README.md)

### Devo mandare in produzione (~2 ore)

1. [Ambienti](05-operations/01-environments.md)
2. [Gestione dei rilasci](05-operations/02-release-management.md)
3. [Deployment](../deployment/README.md)
4. [Checklist di rilascio](../checklists/release-checklist.md)
5. [Gestione degli incidenti](05-operations/05-incident-management.md)

### Sono un agente AI

1. [CLAUDE.md](../CLAUDE.md)
2. [Protocollo agenti](../agents/00-agent-protocol.md)
3. Il file del proprio agente in [`agents/`](../agents/README.md)
4. Le [regole](../rules/README.md) pertinenti
5. La [checklist](../checklists/README.md) di uscita della fase

Percorsi dettagliati con tempi e prerequisiti: [`06-reference/06-reading-paths.md`](06-reference/06-reading-paths.md).

---

## Indice completo

### 00 — Introduzione

| Documento | Contenuto |
|---|---|
| [01-vision.md](00-introduction/01-vision.md) | Il problema che la Factory risolve e dove vogliamo arrivare |
| [02-what-is-ai-factory.md](00-introduction/02-what-is-ai-factory.md) | Anatomia della piattaforma, cosa è e cosa non è |
| [03-technology-stack.md](00-introduction/03-technology-stack.md) | Stack vincolante, versioni, motivazioni, alternative scartate |
| [04-principles.md](00-introduction/04-principles.md) | I dodici principi, con implicazioni pratiche |
| [05-repository-structure.md](00-introduction/05-repository-structure.md) | Cosa va dove, e perché |
| [06-glossary.md](00-introduction/06-glossary.md) | Terminologia comune, italiano e inglese |
| [07-faq.md](00-introduction/07-faq.md) | Domande ricorrenti e obiezioni |

### 01 — Getting started

| Documento | Contenuto |
|---|---|
| [01-new-project.md](01-getting-started/01-new-project.md) | Dalla frase iniziale al progetto avviato |
| [02-local-environment.md](01-getting-started/02-local-environment.md) | Docker, servizi, comandi, primo avvio |
| [03-first-module.md](01-getting-started/03-first-module.md) | Creare un modulo completo, passo per passo |
| [04-running-the-agents.md](01-getting-started/04-running-the-agents.md) | Come si eseguono gli agenti, singolarmente e in catena |
| [05-daily-workflow.md](01-getting-started/05-daily-workflow.md) | La giornata tipo di uno sviluppatore sulla piattaforma |

### 02 — Convenzioni

| Documento | Contenuto |
|---|---|
| [01-documentation-style.md](02-conventions/01-documentation-style.md) | Come si scrive un documento della Factory |
| [02-project-layout.md](02-conventions/02-project-layout.md) | Struttura standard di un progetto generato |
| [03-language-policy.md](02-conventions/03-language-policy.md) | Italiano e inglese: cosa in quale lingua |
| [04-project-versioning.md](02-conventions/04-project-versioning.md) | Versionamento dei progetti generati |

### 03 — Sviluppo

| Documento | Contenuto |
|---|---|
| [01-development-lifecycle.md](03-development/01-development-lifecycle.md) | Dal ticket al merge |
| [02-feature-development-guide.md](03-development/02-feature-development-guide.md) | Sviluppo end-to-end di una feature |
| [03-database-workflow.md](03-development/03-database-workflow.md) | Migration landlord e tenant, seed, dati di prova |
| [04-api-development-guide.md](03-development/04-api-development-guide.md) | Progettare ed esporre una API REST |
| [05-filament-development-guide.md](03-development/05-filament-development-guide.md) | Pannelli, resource, form, table, widget |
| [06-frontend-development-guide.md](03-development/06-frontend-development-guide.md) | Livewire, Tailwind, Alpine, Vite |
| [07-debugging-guide.md](03-development/07-debugging-guide.md) | Diagnosi in locale, staging e produzione |
| [08-refactoring-guide.md](03-development/08-refactoring-guide.md) | Rientrare dal debito tecnico in sicurezza |

### 04 — Qualità

| Documento | Contenuto |
|---|---|
| [01-testing-strategy.md](04-quality/01-testing-strategy.md) | Piramide dei test, cosa si testa e come |
| [02-code-review-guide.md](04-quality/02-code-review-guide.md) | Come si revisiona e come si riceve una revisione |
| [03-static-analysis.md](04-quality/03-static-analysis.md) | PHPStan, Pint, Rector: uso e configurazione |
| [04-performance-guide.md](04-quality/04-performance-guide.md) | Diagnosi e correzione dei problemi di prestazione |
| [05-security-guide.md](04-quality/05-security-guide.md) | Superficie d'attacco e contromisure standard |

### 05 — Operations

| Documento | Contenuto |
|---|---|
| [01-environments.md](05-operations/01-environments.md) | Locale, CI, staging, produzione |
| [02-release-management.md](05-operations/02-release-management.md) | Versioni, finestre, rollback |
| [03-monitoring-and-logging.md](05-operations/03-monitoring-and-logging.md) | Cosa si osserva e cosa si allarma |
| [04-backup-and-restore.md](05-operations/04-backup-and-restore.md) | Strategia di backup multitenant e prove di ripristino |
| [05-incident-management.md](05-operations/05-incident-management.md) | Gestione degli incidenti e post-mortem |
| [06-tenant-operations.md](05-operations/06-tenant-operations.md) | Provisioning, sospensione, migrazione, cancellazione tenant |

### 06 — Riferimento

| Documento | Contenuto |
|---|---|
| [01-project-brief-template.md](06-reference/01-project-brief-template.md) | Il modulo da compilare prima di `loop crea` |
| [02-artifact-catalog.md](06-reference/02-artifact-catalog.md) | Catalogo di tutti gli artefatti producibili |
| [03-command-reference.md](06-reference/03-command-reference.md) | Comandi standard di progetto e di Factory |
| [04-configuration-reference.md](06-reference/04-configuration-reference.md) | Chiavi di configurazione della Foundation |
| [05-troubleshooting.md](06-reference/05-troubleshooting.md) | Sintomo → causa → rimedio |
| [06-reading-paths.md](06-reference/06-reading-paths.md) | Percorsi di lettura per ruolo |

---

## Documentazione fuori da `docs/`

Non tutta la documentazione vive qui: quella **normativa** e quella **contestuale** stanno accanto
a ciò che descrivono.

| Cartella | Cosa contiene | Autorità |
|---|---|---|
| [`architecture/`](../architecture/README.md) | Architettura di riferimento e ADR | alta: vincola il progetto |
| [`rules/`](../rules/README.md) | Standard aziendali | massima: vincolante |
| [`agents/`](../agents/README.md) | Definizione degli agenti AI | alta |
| [`prompts/`](../prompts/README.md) | Prompt operativi | alta |
| [`workflows/`](../workflows/README.md) | Processo e quality gate | alta |
| [`checklists/`](../checklists/README.md) | Verifiche operative | alta |
| [`foundation/`](../foundation/README.md) | Documentazione del codice riutilizzabile | alta |
| [`modules/`](../modules/README.md) | Specifiche dei moduli | alta |
| [`deployment/`](../deployment/README.md) | Infrastruttura e rilascio | alta |
| [`examples/`](../examples/README.md) | Esempi applicati | nulla: illustrativi |
| [`governance/`](../governance/README.md) | Evoluzione della Factory | alta |

In caso di conflitto vale la gerarchia definita in [CLAUDE.md](../CLAUDE.md#gerarchia-delle-fonti-di-verità).

---

## Best practice di consultazione

- **Parti dall'indice della cartella**, non dalla ricerca testuale: la struttura contiene informazione.
- **Segui i riferimenti**: ogni documento termina con i collegamenti pertinenti, non è un caso.
- **Se un documento ti sembra sbagliato, probabilmente lo è**: apri un contributo invece di
  aggirarlo nel tuo progetto.
- **Non copiare testo tra documenti**: linka. La duplicazione è il modo in cui la documentazione
  inizia a mentire.
- **Distingui regola da guida**: se stai per derogare, controlla se stai derogando a una regola
  (serve ADR) o a un consiglio (basta il giudizio).

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Cercare la regola nelle guide | Si trova un consiglio e lo si prende per obbligo (o viceversa) | Le regole stanno solo in `rules/` |
| Leggere solo il proprio pezzo | Si reimplementa qualcosa che esiste già | Percorsi di lettura completi |
| Documentare una feature nel proprio progetto invece che qui | Il sapere resta isolato | Se è generale, sale nella Factory |
| Aggiungere un documento senza linkarlo | Diventa invisibile | Aggiornare questo indice |
| Fidarsi di `examples/` come normativa | Si replicano scelte contestuali | Gli esempi non vincolano mai |

---

## Checklist

- [ ] Ho identificato il **tipo** di documento che mi serve (spiegazione, guida, regola, riferimento).
- [ ] Ho consultato l'indice della cartella pertinente.
- [ ] Ho verificato se esiste già una regola vincolante sull'argomento.
- [ ] Se ho creato un documento, l'ho aggiunto a questo indice.
- [ ] Se ho spostato un documento, ho aggiornato tutti i riferimenti.

---

## Riferimenti

- [README della Factory](../README.md)
- [CLAUDE.md](../CLAUDE.md)
- [Guida al contributo](../CONTRIBUTING.md)
- [Indice delle regole](../rules/README.md)
- [Master workflow](../workflows/00-master-workflow.md)
