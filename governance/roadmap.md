# Roadmap e stato della Factory

> Che cosa esiste, che cosa manca, in che ordine si costruisce. Documento vivo: si aggiorna ad
> ogni contributo rilevante.

---

## Indice

1. [Descrizione](#descrizione)
2. [Stato per area](#stato-per-area)
3. [Fasi di costruzione](#fasi-di-costruzione)
4. [Criteri di completamento](#criteri-di-completamento)
5. [Backlog](#backlog)
6. [Storico versioni](#storico-versioni)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

La Factory si costruisce in ordine di **dipendenza**, non di visibilità: prima le regole e
l'architettura (che vincolano tutto il resto), poi gli agenti e i prompt (che le applicano), poi
codice e template (che le materializzano), infine gli esempi (che le dimostrano).

Legenda stato: ✅ completo · 🟡 in corso · ⬜ da fare

---

## Stato per area

| # | Area | Stato | Contenuto atteso | Documento indice |
|---|---|---|---|---|
| 1 | Root e governance | ✅ | README, CLAUDE.md, CONTRIBUTING, CHANGELOG, governance | [`governance/README.md`](README.md) |
| 2 | `docs/` | 🟡 | introduzione, getting started, convenzioni, sviluppo, qualità, operations, reference | [`docs/README.md`](../docs/README.md) |
| 3 | `architecture/` | ⬜ | architettura di riferimento, multitenancy, moduli, ADR | [`architecture/README.md`](../architecture/README.md) |
| 4 | `rules/` | ⬜ | standard vincolanti per linguaggio, framework, pattern, processo | [`rules/README.md`](../rules/README.md) |
| 5 | `agents/` | ⬜ | protocollo + 16 agenti con prompt completi | [`agents/README.md`](../agents/README.md) |
| 6 | `prompts/` | ⬜ | system prompt, `loop crea`, prompt di fase, snippet | [`prompts/README.md`](../prompts/README.md) |
| 7 | `foundation/` | ⬜ | pacchetto PHP riutilizzabile + documentazione | [`foundation/README.md`](../foundation/README.md) |
| 8 | `templates/` | ⬜ | stub per ogni artefatto Laravel/Filament | [`templates/README.md`](../templates/README.md) |
| 9 | `modules/` | ⬜ | blueprint di modulo + catalogo | [`modules/README.md`](../modules/README.md) |
| 10 | `workflows/` | ⬜ | master workflow + workflow di fase | [`workflows/README.md`](../workflows/README.md) |
| 11 | `checklists/` | ⬜ | quality gate operativi | [`checklists/README.md`](../checklists/README.md) |
| 12 | `deployment/` | ⬜ | Docker, CI/CD, ambienti, runbook | [`deployment/README.md`](../deployment/README.md) |
| 13 | `tooling/` | ⬜ | configurazioni condivise e script | [`tooling/README.md`](../tooling/README.md) |
| 14 | `examples/` | ⬜ | walkthrough end-to-end e codice esemplificativo | [`examples/README.md`](../examples/README.md) |

---

## Fasi di costruzione

### Fase A — Fondamenta normative

Obiettivo: rendere le decisioni esplicite prima di scrivere una riga di codice.

- Architettura di riferimento e ADR iniziali
- Regole per linguaggio, framework e pattern
- Convenzioni di naming e di repository

**Uscita**: un agente sa già *come* deve essere fatto qualunque artefatto, prima di riceverne richiesta.

### Fase B — Automazione cognitiva

Obiettivo: rendere le regole eseguibili da agenti.

- Protocollo agenti e contratti di I/O
- I 16 agenti con prompt completi
- Il contratto del comando `loop crea`
- Workflow di fase con quality gate

**Uscita**: `loop crea` produce un piano di lavoro corretto anche senza intervento umano.

### Fase C — Materiale riutilizzabile

Obiettivo: eliminare la riscrittura.

- Foundation: contratti, concerns, tenancy, action, DTO, repository
- Template per ogni artefatto
- Blueprint di modulo e primi moduli di catalogo

**Uscita**: un progetto nuovo parte già con il 60–70% dell'infrastruttura pronta.

### Fase D — Industrializzazione

Obiettivo: rendere ripetibile la consegna.

- Docker, ambienti, pipeline CI/CD
- Runbook operativi e procedure di incident
- Checklist di rilascio

**Uscita**: il deploy è una procedura, non un evento.

### Fase E — Dimostrazione e affinamento

Obiettivo: verificare la Factory sul campo.

- Walkthrough completo di un progetto reale
- Metriche di qualità e revisione delle regole che non hanno funzionato

**Uscita**: la Factory è validata da almeno un progetto end-to-end.

---

## Criteri di completamento

Un'area si considera completa quando:

- [ ] Ha un `README.md` che indicizza **tutti** i suoi file.
- [ ] Ogni documento contiene le sezioni obbligatorie previste da [`CONTRIBUTING.md`](../CONTRIBUTING.md).
- [ ] Ogni regola dichiarata è **verificabile** (manualmente o in pipeline).
- [ ] Non esistono link interni rotti.
- [ ] Un agente che legge solo quell'area sa produrre l'artefatto corrispondente senza domande.
- [ ] Esiste almeno un esempio applicato.

---

## Backlog

Idee accettate ma non ancora pianificate:

| Voce | Area | Motivazione | Priorità |
|---|---|---|---|
| Generatore CLI `factory:new` | tooling | rendere `loop crea` eseguibile localmente | alta |
| Test di architettura (Pest Arch) di serie | rules/testing | impedire violazioni strutturali in automatico | alta |
| Modulo `billing` (abbonamenti multitenant) | modules | ricorre in tutti i SaaS | media |
| Design system Filament condiviso | templates | coerenza visiva tra prodotti | media |
| Catalogo di query analitiche riusabili | modules/reporting | i report si somigliano tutti | media |
| Supporto multi-regione per i tenant | architecture | requisito futuro di compliance | bassa |
| Integrazione con provider di firma digitale | modules/documents | richiesto da CAF e Documentale | bassa |

---

## Storico versioni

| Versione | Data | Contenuto principale |
|---|---|---|
| `factory-v0.1.0` | 2026-07-25 | Struttura del repository, documenti di root, governance |

---

## Best practice

- Aggiornare questo documento **nello stesso commit** che completa un'area.
- Non segnare un'area come completa se manca l'indice o un esempio.
- Tenere il backlog corto: le idee che restano ferme per due versioni si eliminano o si promuovono.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Roadmap aggiornata «a fine progetto» | Nessuno sa a che punto siamo | Aggiornare ad ogni contributo |
| Area segnata completa senza indice | I file diventano irraggiungibili | Applicare i criteri di completamento |
| Backlog infinito | Perde valore informativo | Potatura ad ogni versione minor |
| Costruire gli esempi prima delle regole | Gli esempi cristallizzano scelte non decise | Rispettare l'ordine delle fasi |

---

## Checklist

- [ ] Lo stato per area riflette il contenuto reale del repository.
- [ ] Le aree completate soddisfano tutti i criteri di completamento.
- [ ] Lo storico versioni è allineato a `CHANGELOG.md`.
- [ ] Il backlog è stato potato nell'ultima versione.

---

## Riferimenti

- [Governance](README.md) · [Versionamento](versioning.md) · [Ownership](ownership.md)
- [CHANGELOG](../CHANGELOG.md)
- [Master workflow](../workflows/00-master-workflow.md)
