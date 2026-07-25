# Eseguire gli agenti

> Come si lancia un agente, come si compone una catena, come si legge il suo output e cosa fare
> quando sbaglia.

---

## Indice

1. [Descrizione](#descrizione)
2. [Modalità di esecuzione](#modalità-di-esecuzione)
3. [Anatomia di un'invocazione](#anatomia-di-uninvocazione)
4. [Esecuzione di una singola fase](#esecuzione-di-una-singola-fase)
5. [Esecuzione a catena](#esecuzione-a-catena)
6. [Il contesto da fornire](#il-contesto-da-fornire)
7. [Leggere l'output](#leggere-loutput)
8. [Quando l'agente sbaglia](#quando-lagente-sbaglia)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Un agente della Factory non è un assistente generico a cui si chiede qualcosa: è un **esecutore di
una fase**, con input dichiarati, output dichiarati e limiti espliciti. Trattarlo come un
assistente generico produce output non ripetibile — che è esattamente ciò che la Factory esiste
per evitare.

La regola operativa è: *un'invocazione, una fase, un insieme di artefatti attesi*.

---

## Modalità di esecuzione

| Modalità | Quando si usa | Chi controlla |
|---|---|---|
| **Fase singola** | serve un artefatto specifico su un progetto esistente | l'operatore, ad ogni passo |
| **Catena parziale** | un blocco di fasi collegate (es. database → backend → test) | l'operatore, ai gate |
| **`loop crea` completo** | nuovo progetto da zero | l'orchestratore, con fermate sulle decisioni di dominio |
| **Manuale** | l'esecutore è una persona | la persona |

Le quattro modalità usano **gli stessi prompt e le stesse checklist**: cambia chi esegue, non il
metodo. È questo che rende confrontabili i risultati.

---

## Anatomia di un'invocazione

Ogni invocazione è composta da quattro parti. Ometterne una produce output degradato.

```
┌─────────────────────────────────────────────────────────┐
│ 1. IDENTITÀ                                             │
│    Quale agente sei, con quali limiti                   │
│    → agents/NN-nome-agent.md                            │
├─────────────────────────────────────────────────────────┤
│ 2. REGOLE APPLICABILI                                   │
│    Le regole vincolanti per l'artefatto richiesto       │
│    → rules/*.md pertinenti                              │
├─────────────────────────────────────────────────────────┤
│ 3. CONTESTO                                             │
│    Brief, artefatti delle fasi precedenti, stato repo   │
├─────────────────────────────────────────────────────────┤
│ 4. COMPITO                                              │
│    Cosa produrre, in quale forma, con quale gate        │
└─────────────────────────────────────────────────────────┘
```

Modello di invocazione:

```markdown
Agisci come **Database Agent** secondo `agents/04-database-agent.md`.

REGOLE APPLICABILI
- rules/sql.md
- rules/database.md
- rules/naming.md
- architecture/04-landlord-database.md
- architecture/05-tenant-databases.md

CONTESTO
- Project Brief: docs/project-brief.md
- Output fase 2 (Architect): docs/architecture/moduli.md
- Stato del repository: struttura Laravel 12 già inizializzata, Foundation installata

COMPITO
Produci lo schema del database per i moduli `inventory` e `suppliers`:
migration landlord, migration tenant, factory, seeder di sistema.

VINCOLI
- Nessuna colonna `tenant_id` nelle tabelle tenant.
- Ogni migration reversibile.
- Indici su tutte le colonne usate nei filtri dichiarati nel brief.

GATE DI USCITA
`checklists/database-checklist.md` — tutte le voci soddisfatte.
```

---

## Esecuzione di una singola fase

Procedura:

1. **Identificare la fase** nel [master workflow](../../workflows/00-master-workflow.md).
2. **Verificare gli input**: se gli artefatti della fase precedente non esistono, la fase non può
   partire. Un agente che «immagina» l'input produce lavoro da buttare.
3. **Comporre l'invocazione** secondo il modello sopra.
4. **Eseguire.**
5. **Verificare il gate** con la checklist della fase.
6. **Registrare l'esito**: artefatti prodotti, deviazioni, domande aperte.

Se il gate fallisce, si torna al punto 4 con il **rapporto di fallimento** in ingresso — non si
riparte da zero: l'agente deve sapere cosa è stato respinto e perché.

---

## Esecuzione a catena

Le fasi si compongono in catene quando gli output di una sono gli input della successiva.

Catene ricorrenti:

| Catena | Fasi | Uso tipico |
|---|---|---|
| Nuovo modulo | Database → Backend → Filament → Testing | aggiungere una funzionalità completa |
| Rientro qualità | Reviewer → Refactoring → Testing | dopo un periodo di sviluppo rapido |
| Preparazione al rilascio | Security → Performance → Deploy | prima di una release |
| Documentazione | Documentation → Reviewer | allineare la documentazione al codice |

Regole della catena:

- Il gate di ogni fase è **bloccante**: non si passa alla successiva con un gate rosso.
- L'handoff è **esplicito**: la fase dichiara gli artefatti prodotti, la successiva li riceve
  come input dichiarati.
- Il rework non supera **tre** tentativi: al terzo, si ferma e si chiede una diagnosi umana.

---

## Il contesto da fornire

L'errore più comune non è nel prompt: è nel **contesto mancante**.

| Sempre | Se pertinente | Mai |
|---|---|---|
| `CLAUDE.md` del progetto | schema del database esistente | file non pertinenti alla fase |
| Project Brief | contratti delle API già esposte | l'intero repository «per sicurezza» |
| Artefatti della fase precedente | ADR di progetto | credenziali o segreti |
| Regole applicabili alla fase | rapporto di revisione precedente | dati reali di clienti |
| Checklist di uscita | log di errore rilevanti | conversazioni non pertinenti |

Fornire troppo contesto è dannoso quanto fornirne troppo poco: diluisce l'attenzione sugli
elementi che contano e aumenta il rischio che l'agente segua un dettaglio irrilevante.

---

## Leggere l'output

Ogni agente produce un output in due parti: gli **artefatti** e il **rapporto di fase**.

Il rapporto dichiara:

```markdown
## Rapporto di fase — Database Agent

### Artefatti prodotti
- database/migrations/tenant/2026_07_25_000001_create_suppliers_table.php
- database/factories/SupplierFactory.php

### Decisioni prese
- Indice composto (status, name) per l'elenco filtrato, che il brief indica come vista principale.

### Assunzioni
- La partita IVA è unica per tenant, non globalmente. **Da confermare.**

### Deviazioni dalle regole
- Nessuna.

### Domande aperte
- I fornitori archiviati devono restare visibili nello storico dei movimenti?

### Gate
- checklists/database-checklist.md: 14/14 soddisfatte.
```

Le sezioni da leggere per prime sono **assunzioni** e **domande aperte**: sono i punti in cui
l'agente ha colmato un vuoto del brief, ed è lì che si annidano gli errori di dominio.

---

## Quando l'agente sbaglia

Diagnosi per sintomo:

| Sintomo | Causa probabile | Rimedio |
|---|---|---|
| Ignora una regola | La regola non era nel contesto | Aggiungerla esplicitamente alle regole applicabili |
| Inventa requisiti | Il brief è incompleto | Completare il brief, non correggere l'output |
| Produce codice non conforme al template | Il template non era nel contesto | Fornire il template |
| Ripete lo stesso errore dopo la correzione | Il prompt dell'agente è carente | Correggere `agents/NN-*.md`, non l'invocazione |
| Va oltre il proprio ambito | I limiti non sono espliciti | Rafforzare la sezione «limiti» dell'agente |
| Output diverso a parità di input | Contesto non deterministico | Fissare gli artefatti di input, non descriverli a voce |
| Tre rework consecutivi | Problema a monte, non nella fase | Fermarsi, diagnosticare la fase precedente |

**Il principio:** un errore isolato si corregge nell'output; un errore che si ripete si corregge
nel **prompt dell'agente**. Correggere sempre l'output significa pagare lo stesso costo ad ogni
progetto.

Ogni correzione al prompt di un agente è un contributo alla Factory, e segue
[`CONTRIBUTING.md`](../../CONTRIBUTING.md).

---

## Esempi

### Esempio 1 — invocazione di fase singola

```markdown
Agisci come **Testing Agent** secondo `agents/13-testing-agent.md`.

REGOLE: rules/testing.md, rules/php.md
CONTESTO: modulo `suppliers` completo in modules/suppliers/, senza test.
COMPITO: suite Pest completa — unit, feature, isolamento tenant, architettura.
GATE: checklists/testing-checklist.md; copertura Action e Policy al 100%.
```

### Esempio 2 — rework dopo gate fallito

```markdown
Il gate della fase Database è fallito. Rapporto:

- `checklists/database-checklist.md` voce 7 non soddisfatta: la migration
  `create_movements_table` non ha `down()`.
- voce 11 non soddisfatta: nessun indice su `movements.batch_id`, usato dal
  filtro principale dichiarato nel brief.

Correggi **solo** questi due punti, senza rigenerare le altre migration.
```

Il rework è chirurgico: indica cosa è stato respinto e cosa **non** va toccato.

### Esempio 3 — correzione strutturale del prompt

Il Backend Agent produce sistematicamente Action che ricevono `Request` invece di DTO, in tre
progetti diversi. Non è un errore di esecuzione: è una lacuna del prompt.

Rimedio: si aggiorna `agents/06-backend-agent.md` con il vincolo esplicito e un esempio corretto,
e si aggiunge la voce alla checklist di fase. Il commit è un contributo alla Factory.

---

## Best practice

- Un'invocazione, una fase. Le richieste composite producono output non verificabile.
- Fornire gli artefatti **come file**, non riassunti a parole: il riassunto perde i dettagli che
  contano.
- Dichiarare sempre il gate di uscita nell'invocazione: l'agente lavora meglio se sa come verrà
  valutato.
- Leggere per prime le sezioni «assunzioni» e «domande aperte» del rapporto.
- Registrare gli esiti: sono la base delle metriche sugli agenti.
- Correggere il prompt quando l'errore si ripete, l'output quando è isolato.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Invocare senza gli artefatti della fase precedente | L'agente inventa il contesto | Verificare gli input prima di partire |
| Chiedere più fasi in una volta | Output non verificabile per fase | Una fase per invocazione |
| Fornire l'intero repository | Attenzione diluita, dettagli irrilevanti seguiti | Contesto pertinente e selezionato |
| Ignorare le «assunzioni» nel rapporto | Errori di dominio scoperti tardi | Leggerle e confermarle |
| Correggere sempre a mano lo stesso difetto | Costo ripetuto ad ogni progetto | Correggere il prompt dell'agente |
| Superare il gate «per fretta» | Il difetto arriva alla fase successiva amplificato | Gate bloccanti |
| Rework generico («rifai meglio») | L'agente rigenera anche ciò che era corretto | Rework chirurgico e specifico |

---

## Checklist

- [ ] Ho identificato la fase e verificato che i suoi input esistano.
- [ ] L'invocazione contiene identità, regole, contesto e compito.
- [ ] Ho dichiarato il gate di uscita.
- [ ] Ho fornito solo il contesto pertinente.
- [ ] Ho letto assunzioni e domande aperte del rapporto.
- [ ] Ho verificato il gate prima di passare alla fase successiva.
- [ ] Se l'errore si è ripetuto, ho corretto il prompt dell'agente.

---

## Riferimenti

- [Indice degli agenti](../../agents/README.md) · [Protocollo agenti](../../agents/00-agent-protocol.md)
- [Contratto `loop crea`](../../prompts/loop-crea.md)
- [Master workflow](../../workflows/00-master-workflow.md)
- [Checklist](../../checklists/README.md)
- [Avviare un nuovo progetto](01-new-project.md)
