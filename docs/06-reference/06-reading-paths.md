# Percorsi di lettura

> Cosa leggere, in quale ordine e con quale impegno, secondo il ruolo e l'obiettivo.

---

## Indice

1. [Descrizione](#descrizione)
2. [Come usare i percorsi](#come-usare-i-percorsi)
3. [Percorso: nuovo in azienda](#percorso-nuovo-in-azienda)
4. [Percorso: sviluppatore backend](#percorso-sviluppatore-backend)
5. [Percorso: sviluppatore frontend](#percorso-sviluppatore-frontend)
6. [Percorso: architetto](#percorso-architetto)
7. [Percorso: DevOps](#percorso-devops)
8. [Percorso: avvio di un progetto](#percorso-avvio-di-un-progetto)
9. [Percorso: agente AI](#percorso-agente-ai)
10. [Letture di riferimento continuo](#letture-di-riferimento-continuo)
11. [Esempi](#esempi)
12. [Best practice](#best-practice)
13. [Errori comuni](#errori-comuni)
14. [Checklist](#checklist)
15. [Riferimenti](#riferimenti)

---

## Descrizione

La documentazione della Factory è ampia perché copre molti aspetti, ma **nessuno deve leggerla
tutta**. Ogni ruolo ha un nucleo indispensabile e un resto da consultare quando serve.

I tempi indicati sono di lettura attenta, non di studio approfondito.

---

## Come usare i percorsi

| Simbolo | Significato |
|---|---|
| **[base]** | indispensabile, da leggere prima di iniziare |
| [approfondimento] | da leggere entro le prime settimane |
| [consultazione] | da conoscere per posizione, da leggere quando serve |

---

## Percorso: nuovo in azienda

**Tempo: ~3 ore.** Obiettivo: capire dove si è capitati.

| # | Documento | Tipo | Tempo |
|---|---|---|---|
| 1 | [Visione](../00-introduction/01-vision.md) | **[base]** | 20 min |
| 2 | [Che cos'è la AI Factory](../00-introduction/02-what-is-ai-factory.md) | **[base]** | 20 min |
| 3 | [I dodici principi](../00-introduction/04-principles.md) | **[base]** | 30 min |
| 4 | [Stack tecnologico](../00-introduction/03-technology-stack.md) | **[base]** | 20 min |
| 5 | [Struttura del repository](../00-introduction/05-repository-structure.md) | **[base]** | 15 min |
| 6 | [Glossario](../00-introduction/06-glossary.md) | **[base]** | 20 min |
| 7 | [FAQ](../00-introduction/07-faq.md) | [approfondimento] | 15 min |
| 8 | [Ambiente locale](../01-getting-started/02-local-environment.md) | **[base]** | 30 min + pratica |
| 9 | [Workflow quotidiano](../01-getting-started/05-daily-workflow.md) | **[base]** | 20 min |

---

## Percorso: sviluppatore backend

**Tempo: ~5 ore** oltre al percorso base.

| # | Documento | Tipo |
|---|---|---|
| 1 | [Struttura di progetto](../02-conventions/02-project-layout.md) | **[base]** |
| 2 | [Regole PHP](../../rules/php.md) | **[base]** |
| 3 | [Regole Laravel](../../rules/laravel.md) | **[base]** |
| 4 | [Naming](../../rules/naming.md) | **[base]** |
| 5 | [Action Pattern](../../rules/action-pattern.md) | **[base]** |
| 6 | [DTO](../../rules/dto.md) | **[base]** |
| 7 | [Repository Pattern](../../rules/repository-pattern.md) | **[base]** |
| 8 | [Policies](../../rules/policies.md) | **[base]** |
| 9 | [Sviluppare una feature](../03-development/02-feature-development-guide.md) | **[base]** |
| 10 | [Il primo modulo](../01-getting-started/03-first-module.md) | **[base]** |
| 11 | [Strategia di testing](../04-quality/01-testing-strategy.md) | **[base]** |
| 12 | [Workflow del database](../03-development/03-database-workflow.md) | **[base]** |
| 13 | [Multitenancy](../../architecture/03-multitenancy-overview.md) | **[base]** |
| 14 | [Events](../../rules/events.md) · [Queue](../../rules/queue.md) | [approfondimento] |
| 15 | [Foundation](../../foundation/README.md) | [approfondimento] |
| 16 | [Debug](../03-development/07-debugging-guide.md) | [consultazione] |

---

## Percorso: sviluppatore frontend

**Tempo: ~3 ore** oltre al percorso base.

| # | Documento | Tipo |
|---|---|---|
| 1 | [Sviluppare con Filament](../03-development/05-filament-development-guide.md) | **[base]** |
| 2 | [Regole Filament](../../rules/filament.md) | **[base]** |
| 3 | [Sviluppare il frontend](../03-development/06-frontend-development-guide.md) | **[base]** |
| 4 | [Regole frontend](../../rules/frontend.md) · [Tailwind](../../rules/tailwind.md) | **[base]** |
| 5 | [Livewire](../../rules/livewire.md) · [Alpine](../../rules/alpine.md) | **[base]** |
| 6 | [UI](../../rules/ui.md) · [UX](../../rules/ux.md) | **[base]** |
| 7 | [Accessibilità](../../rules/accessibility.md) | **[base]** |
| 8 | [Internazionalizzazione](../../rules/i18n.md) | [approfondimento] |
| 9 | [CMS e landing page](../../architecture/17-cms-landing.md) | [approfondimento] |

---

## Percorso: architetto

**Tempo: ~6 ore** oltre al percorso base.

| # | Documento | Tipo |
|---|---|---|
| 1 | [Architettura: panoramica](../../architecture/01-overview.md) | **[base]** |
| 2 | [Livelli](../../architecture/02-layers.md) | **[base]** |
| 3 | [Multitenancy](../../architecture/03-multitenancy-overview.md) e documenti collegati | **[base]** |
| 4 | [Sistema modulare](../../architecture/10-modular-system.md) | **[base]** |
| 5 | [Tutte le ADR](../../architecture/decisions/README.md) | **[base]** |
| 6 | [Processo decisionale](../../governance/decision-process.md) | **[base]** |
| 7 | [Foundation](../../foundation/README.md) | **[base]** |
| 8 | [Catalogo moduli](../../modules/README.md) | **[base]** |
| 9 | [Governance](../../governance/README.md) | [approfondimento] |
| 10 | [Metriche di qualità](../../governance/quality-metrics.md) | [approfondimento] |

---

## Percorso: DevOps

**Tempo: ~4 ore** oltre al percorso base.

| # | Documento | Tipo |
|---|---|---|
| 1 | [Ambienti](../05-operations/01-environments.md) | **[base]** |
| 2 | [Docker](../../deployment/docker/README.md) | **[base]** |
| 3 | [Pipeline CI](../../deployment/ci/README.md) | **[base]** |
| 4 | [Gestione dei rilasci](../05-operations/02-release-management.md) | **[base]** |
| 5 | [Backup e ripristino](../05-operations/04-backup-and-restore.md) | **[base]** |
| 6 | [Monitoraggio e log](../05-operations/03-monitoring-and-logging.md) | **[base]** |
| 7 | [Gestione degli incidenti](../05-operations/05-incident-management.md) | **[base]** |
| 8 | [Operazioni sui tenant](../05-operations/06-tenant-operations.md) | **[base]** |
| 9 | [Runbook](../../deployment/runbooks/README.md) | **[base]** |
| 10 | [Regole di deployment](../../rules/deployment.md) | [approfondimento] |

---

## Percorso: avvio di un progetto

**Tempo: ~4 ore.**

| # | Documento | Tipo |
|---|---|---|
| 1 | [Avviare un nuovo progetto](../01-getting-started/01-new-project.md) | **[base]** |
| 2 | [Project Brief](01-project-brief-template.md) | **[base]** |
| 3 | [Master workflow](../../workflows/00-master-workflow.md) | **[base]** |
| 4 | [Contratto `loop crea`](../../prompts/loop-crea.md) | **[base]** |
| 5 | [Eseguire gli agenti](../01-getting-started/04-running-the-agents.md) | **[base]** |
| 6 | [Catalogo moduli](../../modules/README.md) | **[base]** |
| 7 | [Checklist](../../checklists/README.md) | [consultazione] |
| 8 | [Walkthrough completo](../../examples/walkthroughs/README.md) | [approfondimento] |

---

## Percorso: agente AI

Ordine obbligatorio, non facoltativo:

| # | Documento |
|---|---|
| 1 | [CLAUDE.md](../../CLAUDE.md) |
| 2 | [Protocollo agenti](../../agents/00-agent-protocol.md) |
| 3 | Il file del proprio agente in [`agents/`](../../agents/README.md) |
| 4 | Le regole applicabili alla fase in [`rules/`](../../rules/README.md) |
| 5 | La fase corrente in [`workflows/`](../../workflows/README.md) |
| 6 | La checklist di uscita in [`checklists/`](../../checklists/README.md) |
| 7 | I template pertinenti in [`templates/`](../../templates/README.md) |

---

## Letture di riferimento continuo

Documenti da conoscere per posizione, da consultare quando serve:

| Documento | Quando |
|---|---|
| [Glossario](../00-introduction/06-glossary.md) | scegliendo un nome |
| [Catalogo artefatti](02-artifact-catalog.md) | prima di produrre qualcosa |
| [Riferimento comandi](03-command-reference.md) | operando su un progetto |
| [Configurazione](04-configuration-reference.md) | configurando |
| [Troubleshooting](05-troubleshooting.md) | quando qualcosa non funziona |
| [Indice delle regole](../../rules/README.md) | in ogni dubbio normativo |

---

## Esempi

### Un percorso seguito per intero

Chi arriva sul progetto come sviluppatore backend legge, in quest'ordine:
[principi](../00-introduction/04-principles.md) → [multitenancy](../../architecture/03-multitenancy-overview.md)
→ [livelli](../../architecture/02-layers.md) → [action-pattern](../../rules/action-pattern.md) →
[Foundation](../../foundation/README.md).

Sono cinque documenti, circa due ore. Al termine sa **perché** un'Action riceve un DTO, e non deve
chiederlo alla prima revisione.

### Un percorso saltato

Chi comincia da `rules/laravel.md` trova un elenco di divieti senza le ragioni: sa che non deve
mettere logica nei controller, non sa perché, e alla prima scadenza stretta il divieto perde contro
la fretta.

L'ordine dei percorsi non è una cortesia: le regole senza il contesto che le motiva vengono
applicate finché non costano, e abbandonate quando costano.

---

## Best practice

- Seguire l'ordine indicato: i documenti presuppongono i precedenti.
- Leggere il nucleo **[base]** prima di scrivere codice, non durante.
- Alternare lettura e pratica: dopo l'ambiente locale, provare subito.
- Segnalare i punti poco chiari: sono difetti della documentazione.
- Rileggere i principi dopo il primo mese: si capiscono meglio con il contesto.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Leggere tutto prima di iniziare | Settimane senza produrre, nozioni dimenticate | Nucleo base, poi pratica |
| Saltare i principi | Regole apprese a memoria e applicate male | I principi vengono prima |
| Non leggere il glossario | Nomi incoerenti fin dal primo commit | Lettura obbligatoria |
| Cercare le regole nelle guide | Si scambia un consiglio per un obbligo | Le regole stanno in `rules/` |
| Non segnalare i punti oscuri | La documentazione resta poco chiara per tutti | Aprire un contributo |

---

## Checklist

- [ ] Ho identificato il mio percorso.
- [ ] Ho completato il nucleo **[base]**.
- [ ] Ho provato in pratica ciò che ho letto.
- [ ] Conosco i documenti di riferimento continuo.
- [ ] Ho segnalato i punti poco chiari.

---

## Riferimenti

- [Indice della documentazione](../README.md)
- [Visione](../00-introduction/01-vision.md) · [Principi](../00-introduction/04-principles.md)
- [Indice delle regole](../../rules/README.md) · [Agenti](../../agents/README.md)
