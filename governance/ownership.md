# Ownership delle aree

> Chi risponde di ogni parte della Factory, quali decisioni può prendere da solo, e cosa succede
> quando un'area resta senza presidio.

---

## Indice

1. [Descrizione](#descrizione)
2. [Ruoli](#ruoli)
3. [Mappa delle aree](#mappa-delle-aree)
4. [Autorità decisionale](#autorità-decisionale)
5. [Ruolo degli agenti AI](#ruolo-degli-agenti-ai)
6. [Aree orfane](#aree-orfane)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Un documento senza proprietario invecchia. Una regola senza proprietario viene aggirata.
L'ownership qui non è gerarchia: è la garanzia che qualcuno **noti** quando un pezzo della Factory
non corrisponde più alla realtà.

I nomi delle persone non sono scritti in questo file (cambiano). Sono scritti i **ruoli**, e la
mappa ruolo → area. L'assegnazione nominale vive nel file `CODEOWNERS` del repository, che è
l'unico posto da aggiornare quando cambia una persona.

---

## Ruoli

| Ruolo | Responsabilità | Decide da solo | Deve consultare |
|---|---|---|---|
| **Factory Owner** | Coerenza complessiva, stack, struttura del repository | Approvazione ADR, rilascio versioni | Area Owner coinvolti per le major |
| **Architecture Owner** | `architecture/`, ADR, contratti della Foundation | Modifiche architetturali non breaking | Factory Owner per le breaking |
| **Standards Owner** | `rules/`, `checklists/` | Nuove regole non vincolanti, chiarimenti | Factory Owner per regole vincolanti |
| **Agents Owner** | `agents/`, `prompts/`, `workflows/` | Miglioramenti di prompt, nuovi snippet | Architecture Owner se cambia il flusso |
| **Foundation Owner** | `foundation/`, `templates/`, `modules/` | Aggiunte retrocompatibili | Architecture Owner per i contratti |
| **DevOps Owner** | `deployment/`, `tooling/`, `.github/` | Pipeline, immagini, script | Factory Owner per cambi di ambiente |
| **Docs Owner** | `docs/`, `examples/`, qualità redazionale | Riorganizzazione documentale | Owner dell'area di contenuto |

Una persona può ricoprire più ruoli; nessun ruolo può restare vacante per più di una versione minor.

---

## Mappa delle aree

| Area | Owner | Revisori minimi | Frequenza di revisione |
|---|---|---|---|
| `README.md`, `CLAUDE.md`, `CONTRIBUTING.md` | Factory Owner | 1 | ad ogni major |
| `governance/` | Factory Owner | 1 | ad ogni major |
| `docs/` | Docs Owner | 1 | ogni 2 minor |
| `architecture/` | Architecture Owner | 2 | ad ogni major |
| `architecture/decisions/` | Architecture Owner | 2 | mai riscritte, solo superate |
| `rules/` | Standards Owner | 2 per regole vincolanti | ogni 2 minor |
| `agents/`, `prompts/` | Agents Owner | 2 | ad ogni minor |
| `workflows/` | Agents Owner | 2 | ad ogni minor |
| `checklists/` | Standards Owner | 1 | ogni 2 minor |
| `foundation/` | Foundation Owner | 2 + test verdi | continua |
| `templates/` | Foundation Owner | 1 + prova d'uso | ogni minor |
| `modules/` | Foundation Owner | 2 | ogni minor |
| `deployment/`, `tooling/`, `.github/` | DevOps Owner | 1 | continua |
| `examples/` | Docs Owner | 1 | ogni minor |
| `legacy/` | Factory Owner | 1 | congelato |

---

## Autorità decisionale

Tre categorie di decisione:

### 1. Decisione locale

Riguarda un solo file, non cambia comportamento né vincoli.
→ L'Area Owner decide e procede. Nessuna ADR.

*Esempi:* correggere un esempio, aggiungere una voce a una checklist, riformulare una spiegazione.

### 2. Decisione d'area

Cambia il modo in cui si fa qualcosa dentro un'area, senza impatto fuori.
→ L'Area Owner decide, con una revisione. ADR facoltativa ma consigliata se non ovvia.

*Esempi:* aggiungere un nuovo template, cambiare la struttura di un prompt, aggiungere una regola
non vincolante.

### 3. Decisione strutturale

Impatta più aree, i progetti esistenti, o lo stack.
→ ADR obbligatoria, approvazione del Factory Owner, due revisioni.

*Esempi:* cambiare strategia di multitenancy, aggiornare Laravel a una major, introdurre un nuovo
livello architetturale, rimuovere un agente dal workflow.

---

## Ruolo degli agenti AI

Un agente AI è sempre **contributore**, mai owner. In concreto:

| Può | Non può |
|---|---|
| Proporre e implementare modifiche | Approvare la propria modifica |
| Scrivere una ADR in stato `Proposta` | Portare una ADR in stato `Accettata` |
| Segnalare incoerenze tra documenti | Risolvere l'incoerenza scegliendo in silenzio |
| Applicare le regole | Derogare a una regola vincolante |
| Aggiornare indici e changelog | Creare tag di versione |

Quando un agente rileva un conflitto normativo, il comportamento corretto è: **fermarsi, produrre
una nota di conflitto, proporre una ADR**. Vedi [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md).

---

## Aree orfane

Un'area è orfana quando il suo owner non è più assegnato o non la revisiona da due versioni minor.

Procedura:

1. L'area viene marcata `⚠️ orfana` nella [roadmap](roadmap.md).
2. Le modifiche all'area richiedono l'approvazione del Factory Owner.
3. Entro una minor: riassegnazione, oppure archiviazione dell'area.

Un'area orfana **non** viene semplicemente lasciata lì: o ha un presidio, o esce dalla Factory.

---

## Esempi

### Esempio 1 — decisione locale

Un agente nota che `rules/queue.md` cita un metodo rimosso da Laravel. Corregge, apre una PR con
`docs(rules): aggiorna esempio queue a dispatchSync`. L'Area Owner approva. Nessuna ADR.

### Esempio 2 — decisione strutturale

Si propone di passare da «un database per tenant» a «schema condiviso con discriminante».
Impatta architettura, foundation, moduli, deploy e tutti i progetti esistenti.
→ ADR obbligatoria, con analisi di impatto, alternative, piano di migrazione. Approvazione del
Factory Owner con il parere di Architecture e DevOps Owner.

---

## Best practice

- Tenere `CODEOWNERS` allineato: è l'unico posto con i nomi.
- Un'area, un owner. La co-proprietà diluisce la responsabilità.
- L'owner **non** deve scrivere tutto: deve garantire che ciò che c'è sia corretto.
- Le revisioni periodiche vanno calendarizzate, non lasciate all'occasione.
- Se un owner blocca sistematicamente i contributi, il problema è la regola, non il contributore.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Nomi delle persone dentro i documenti | Documenti obsoleti ad ogni cambio | Solo ruoli qui, nomi in `CODEOWNERS` |
| Owner che è anche unico revisore | Nessun controllo reale | Revisore diverso dall'autore, sempre |
| Decisione strutturale presa in una PR | Motivazione persa in sei mesi | ADR obbligatoria |
| Area senza owner tollerata a lungo | Documentazione che mente | Procedura aree orfane |
| Agente che «decide» un conflitto | Divergenza silenziosa tra regole | Nota di conflitto + ADR |

---

## Checklist

- [ ] Ogni area della mappa ha un owner assegnato in `CODEOWNERS`.
- [ ] Nessuna area è orfana da più di una minor.
- [ ] Le decisioni strutturali dell'ultima versione hanno una ADR.
- [ ] Nessun documento contiene nomi di persone.
- [ ] Le revisioni periodiche previste sono state effettuate.

---

## Riferimenti

- [Governance](README.md) · [Roadmap](roadmap.md) · [Versionamento](versioning.md)
- [Processo decisionale](decision-process.md)
- [Protocollo agenti](../agents/00-agent-protocol.md)
- [ADR](../architecture/decisions/README.md)
