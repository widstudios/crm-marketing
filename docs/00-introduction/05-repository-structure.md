# Struttura del repository

> Cosa va dove, perché sta lì, e come si decide la collocazione di un nuovo artefatto.

---

## Indice

1. [Descrizione](#descrizione)
2. [Vista d'insieme](#vista-dinsieme)
3. [Le cartelle, una per una](#le-cartelle-una-per-una)
4. [Convenzioni di nomenclatura](#convenzioni-di-nomenclatura)
5. [Regole di collocazione](#regole-di-collocazione)
6. [Come si aggiunge una cartella](#come-si-aggiunge-una-cartella)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

La struttura del repository non è organizzativa: è **semantica**. La cartella in cui vive un
documento ne dichiara l'autorità e la natura. Lo stesso testo in `rules/` è vincolante, in `docs/`
è esplicativo, in `examples/` è illustrativo.

Per questo la collocazione non è una questione di gusto: sbagliare cartella significa cambiare il
significato di ciò che si è scritto.

---

## Vista d'insieme

```
widstudios-ai-factory/
│
├── README.md               Punto di ingresso umano
├── CLAUDE.md               Punto di ingresso per agenti AI
├── CONTRIBUTING.md         Come si contribuisce
├── CHANGELOG.md            Registro delle modifiche
├── CODEOWNERS              Assegnazione nominale delle aree
│
├── docs/                   ─┐
├── architecture/            │ CONOSCENZA e REGOLE
├── rules/                   │
├── checklists/             ─┘
│
├── agents/                 ─┐ AUTOMAZIONE
├── prompts/                 │
├── workflows/              ─┘
│
├── foundation/             ─┐ CODICE RIUTILIZZABILE
├── templates/               │
├── modules/                ─┘
│
├── deployment/             ─┐ INDUSTRIALIZZAZIONE
├── tooling/                 │
├── .github/                ─┘
│
├── examples/               ─┐ SUPPORTO
├── governance/              │
└── legacy/                 ─┘
```

---

## Le cartelle, una per una

### `docs/` — documentazione trasversale

**Natura**: esplicativa e procedurale. **Autorità**: media (non vincolante).

Contiene ciò che serve a capire e a fare, organizzato per funzione del lettore. Non contiene
regole vincolanti: quelle stanno in `rules/`.

Sottocartelle numerate per ordine di lettura: `00-introduction/`, `01-getting-started/`,
`02-conventions/`, `03-development/`, `04-quality/`, `05-operations/`, `06-reference/`.

→ [`docs/README.md`](../README.md)

### `architecture/` — architettura di riferimento

**Natura**: descrittiva e prescrittiva. **Autorità**: alta.

Descrive l'architettura che **ogni** progetto deve avere: livelli, multitenancy, moduli, API,
integrazioni. Contiene le ADR, che sono la fonte di verità più alta della Factory.

I file sono numerati perché l'ordine è di dipendenza concettuale, non alfabetico.

→ [`architecture/README.md`](../../architecture/README.md)

### `rules/` — standard vincolanti

**Natura**: normativa. **Autorità**: massima (dopo le ADR).

Un file per tecnologia o pattern. Ogni regola è prescrittiva e dichiara il proprio criterio di
verifica. Se un contenuto non è verificabile, non appartiene a questa cartella.

Nomi senza prefisso numerico: le regole non hanno un ordine di lettura, si consultano.

→ [`rules/README.md`](../../rules/README.md)

### `checklists/` — verifiche operative

**Natura**: operativa. **Autorità**: alta.

Liste spuntabili usate come quality gate tra le fasi del workflow e in code review. Sono la
controparte eseguibile di `rules/`.

→ [`checklists/README.md`](../../checklists/README.md)

### `agents/` — definizione degli agenti AI

**Natura**: specifica di comportamento. **Autorità**: alta.

Un file per agente, con responsabilità, input, output, limiti, workflow e prompt completo.
Numerati secondo l'ordine di intervento nel master workflow.

→ [`agents/README.md`](../../agents/README.md)

### `prompts/` — prompt riutilizzabili

**Natura**: operativa. **Autorità**: alta.

Prompt di sistema, prompt di fase, snippet componibili, e il contratto del comando `loop crea`.
Separati dagli agenti perché un prompt può essere usato da più agenti e va versionato a parte.

→ [`prompts/README.md`](../../prompts/README.md)

### `workflows/` — processo

**Natura**: procedurale. **Autorità**: alta.

La sequenza delle fasi, i loro input e output, i quality gate. Include i workflow speciali:
modulo, hotfix, rilascio, migrazione.

→ [`workflows/README.md`](../../workflows/README.md)

### `foundation/` — codice riutilizzabile

**Natura**: codice eseguibile. **Autorità**: alta (è la specifica più precisa).

Il pacchetto PHP `widstudios/foundation`, installato dai progetti via Composer. Contiene contratti,
concerns, tenancy, action base, DTO, repository, servizi, eccezioni, più la propria documentazione
e i propri test.

→ [`foundation/README.md`](../../foundation/README.md)

### `templates/` — stub da istanziare

**Natura**: materiale di partenza. **Autorità**: media.

File `.stub` con segnaposto, uno per tipo di artefatto. Non sono esempi: sono ciò che gli agenti
istanziano realmente.

→ [`templates/README.md`](../../templates/README.md)

### `modules/` — catalogo dei moduli

**Natura**: specifica funzionale e tecnica. **Autorità**: alta.

Il blueprint di modulo e le specifiche dei moduli riutilizzabili. Ogni modulo dichiara database,
backend, frontend, API, test, documentazione e checklist.

→ [`modules/README.md`](../../modules/README.md)

### `deployment/` — infrastruttura e rilascio

**Natura**: operativa. **Autorità**: alta.

Docker, pipeline CI/CD, definizione degli ambienti, runbook operativi.

→ [`deployment/README.md`](../../deployment/README.md)

### `tooling/` — strumenti condivisi

**Natura**: configurazione e script. **Autorità**: alta.

Configurazioni di Pint, PHPStan, Rector, script di verifica della Factory stessa.

→ [`tooling/README.md`](../../tooling/README.md)

### `examples/` — esempi applicati

**Natura**: illustrativa. **Autorità**: **nulla**.

Casi d'uso completi e frammenti di codice. Gli esempi **non vincolano mai**: mostrano
un'applicazione possibile delle regole in un contesto specifico.

→ [`examples/README.md`](../../examples/README.md)

### `governance/` — evoluzione della Factory

**Natura**: normativa di processo. **Autorità**: alta.

Versionamento, roadmap, ownership, processo decisionale, metriche, guide di migrazione.

→ [`governance/README.md`](../../governance/README.md)

### `legacy/` — archivio congelato

**Natura**: storica. **Autorità**: nulla.

Il prototipo CRM preesistente. Non riceve sviluppo, non è riferimento per nulla.

→ [`legacy/README.md`](../../legacy/README.md)

---

## Convenzioni di nomenclatura

| Elemento | Convenzione | Esempio |
|---|---|---|
| Cartelle | `kebab-case`, singolare per concetto, plurale per collezioni | `architecture/`, `rules/`, `checklists/` |
| Documenti con ordine | `NN-nome-in-kebab-case.md` | `03-multitenancy-overview.md` |
| Documenti senza ordine | `nome-in-kebab-case.md` | `php.md`, `commit.md` |
| Indice di cartella | sempre `README.md` | `rules/README.md` |
| ADR | `NNNN-titolo-breve.md` | `0002-tenant-isolation-strategy.md` |
| Template | `Nome.tipo.stub` | `Action.php.stub` |
| Checklist | `<ambito>-checklist.md` | `code-review-checklist.md` |
| Agenti | `NN-nome-agent.md` | `04-database-agent.md` |

Il prefisso numerico si usa **solo** dove l'ordine di lettura è significativo. Applicarlo ovunque
crea l'illusione di una sequenza che non esiste e complica gli inserimenti.

---

## Regole di collocazione

Albero di decisione per un nuovo artefatto:

```
È codice eseguibile riutilizzabile?
├── sì → è uno stub da istanziare?
│        ├── sì → templates/
│        └── no → è un'unità funzionale completa?
│                 ├── sì → modules/
│                 └── no → foundation/
└── no → è vincolante?
         ├── sì → è una verifica spuntabile?
         │        ├── sì → checklists/
         │        └── no → riguarda l'architettura?
         │                 ├── sì → architecture/
         │                 └── no → rules/
         └── no → descrive un processo?
                  ├── sì → workflows/ (fasi) o deployment/ (rilascio)
                  └── no → è un esempio?
                           ├── sì → examples/
                           └── no → docs/
```

Tre domande di controllo prima di collocare:

1. **Autorità**: chi legge questo documento deve *obbedire* o *capire*?
2. **Durata**: cambierà con lo stack (Factory) o con il cliente (progetto)?
3. **Lettore**: umano che studia, umano che esegue, o agente che genera?

---

## Come si aggiunge una cartella

Aggiungere una cartella di primo livello è una **decisione strutturale**: richiede ADR.

Prima di proporla, verificare che il contenuto non appartenga a una cartella esistente. Nella
maggior parte dei casi appartiene: la tentazione di creare una cartella nuova nasce dal non voler
scegliere tra due esistenti.

Se la ADR viene accettata:

1. Creare la cartella con il suo `README.md` indice.
2. Aggiungerla alla vista d'insieme di questo documento.
3. Aggiungerla alla tabella di [`docs/README.md`](../README.md#documentazione-fuori-da-docs).
4. Assegnarne l'ownership in [`governance/ownership.md`](../../governance/ownership.md) e in
   `CODEOWNERS`.
5. Aggiungerla alla roadmap con il proprio stato.
6. Incrementare la **major** della Factory (la struttura è cambiata).

---

## Esempi

### Esempio 1 — «Dove metto la guida al debug delle code?»

Non è vincolante (spiega come fare, non cosa è obbligatorio) → non `rules/`.
Non è architettura → non `architecture/`.
È una guida operativa di sviluppo → `docs/03-development/07-debugging-guide.md`.

### Esempio 2 — «Dove metto la regola sul prefisso delle chiavi di cache?»

È vincolante e verificabile (si può controllare con un test) → `rules/cache.md`.
La *spiegazione* del perché le chiavi devono essere tenant-scoped sta in
`architecture/22-caching-strategy.md`, e la regola vi rimanda.

### Esempio 3 — «Dove metto il codice che risolve il tenant dalla richiesta?»

È codice eseguibile riutilizzabile, non è uno stub, non è un'unità funzionale autonoma →
`foundation/src/Tenancy/`.

### Esempio 4 — «Dove metto l'esempio completo di un progetto generato?»

È illustrativo, non vincola → `examples/walkthroughs/`.

---

## Best practice

- Prima di creare un file, cercare se l'argomento esiste già: nella maggior parte dei casi va
  esteso un documento, non creato uno nuovo.
- Ogni file nuovo va aggiunto all'indice della sua cartella **nello stesso commit**.
- Un documento che supera le ~500 righe si divide in più file collegati, mantenendo un indice
  che li lega.
- Non spostare file senza aggiornare i riferimenti: usare una ricerca sul percorso vecchio.
- Se non riesci a decidere tra due cartelle, il documento probabilmente contiene due cose diverse:
  dividilo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Regola scritta in `docs/` | Nessuno la considera vincolante | Spostarla in `rules/` |
| Spiegazione lunga dentro `rules/` | La regola si perde nel testo | Regola in `rules/`, spiegazione in `architecture/` o `docs/` |
| Esempio trattato come normativo | Si replicano scelte contestuali | Gli esempi non vincolano mai |
| Nuova cartella senza ADR | Struttura che si frammenta | Verificare le cartelle esistenti, poi ADR |
| File non linkato dall'indice | Diventa invisibile | Aggiornare il `README.md` di cartella |
| Prefissi numerici ovunque | Ordine falso, inserimenti difficili | Numerare solo dove l'ordine conta |
| Spostamento senza aggiornare i link | Navigazione rotta | Ricerca e sostituzione dei percorsi |

---

## Checklist

- [ ] Il file è nella cartella che riflette la sua **autorità**.
- [ ] Il nome segue le convenzioni della tabella.
- [ ] È linkato dall'indice della sua cartella.
- [ ] Se supera le 500 righe, è stato diviso in file collegati.
- [ ] Se ho spostato qualcosa, ho aggiornato tutti i riferimenti.
- [ ] Se ho creato una cartella di primo livello, esiste la ADR.

---

## Riferimenti

- [Che cos'è la AI Factory](02-what-is-ai-factory.md)
- [Indice della documentazione](../README.md)
- [Guida al contributo](../../CONTRIBUTING.md)
- [Convenzioni redazionali](../02-conventions/01-documentation-style.md)
- [Struttura di un progetto generato](../02-conventions/02-project-layout.md)
