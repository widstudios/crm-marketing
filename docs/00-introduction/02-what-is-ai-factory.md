# Che cos'è la AI Factory

> Anatomia della piattaforma: i cinque asset che la compongono, come interagiscono, e i confini
> precisi di ciò che vi appartiene.

---

## Indice

1. [Descrizione](#descrizione)
2. [Definizione operativa](#definizione-operativa)
3. [I cinque asset](#i-cinque-asset)
4. [Come gli asset interagiscono](#come-gli-asset-interagiscono)
5. [Cosa la Factory non è](#cosa-la-factory-non-è)
6. [Il confine: Factory o progetto?](#il-confine-factory-o-progetto)
7. [Il ciclo di feedback](#il-ciclo-di-feedback)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

La AI Factory è una **piattaforma di produzione software**: un sistema di conoscenza, regole,
automazione e codice che trasforma una richiesta di dominio in un'applicazione conforme.

L'analogia industriale è precisa e vale la pena di prenderla sul serio. In una fabbrica reale
esistono:

| Elemento industriale | Corrispettivo nella Factory |
|---|---|
| Progetto del prodotto | `docs/`, `architecture/` |
| Specifiche di capitolato | `rules/` |
| Stampi e semilavorati | `foundation/`, `templates/`, `modules/` |
| Macchine e operatori | `agents/`, `prompts/` |
| Ciclo di lavorazione | `workflows/` |
| Controllo qualità | `checklists/`, quality gate |
| Logistica e spedizione | `deployment/` |

Una fabbrica non produce un solo pezzo: produce **una classe di pezzi**, con tolleranze note.
Lo stesso vale qui.

---

## Definizione operativa

La AI Factory è l'insieme minimo di artefatti che rende vera questa affermazione:

> Dato un dominio applicativo descritto in linguaggio naturale, un esecutore competente — umano o
> agente — può produrre un'applicazione multitenant conforme agli standard aziendali **senza
> prendere alcuna decisione infrastrutturale**.

Ogni parte della definizione è un requisito verificabile:

- *«senza prendere alcuna decisione infrastrutturale»* → ogni decisione infrastrutturale ricorrente
  deve essere già presa e registrata (ADR, regole).
- *«conforme agli standard aziendali»* → gli standard devono esistere in forma scritta e
  verificabile.
- *«un esecutore competente, umano o agente»* → la conoscenza deve essere leggibile da entrambi:
  strutturata, esplicita, senza sottintesi.

---

## I cinque asset

### 1. Conoscenza — `docs/`, `architecture/`

Il *perché* e il *come mai*. Spiega le scelte, descrive l'architettura di riferimento, registra le
decisioni con le loro alternative.

**Caratteristica distintiva**: non dice cosa fare, dice perché si fa così. Serve a chi deve
prendere una decisione **nuova** in modo coerente con quelle vecchie.

**Se manca**: le regole diventano arbitrarie, vengono aggirate appena diventano scomode.

### 2. Regole — `rules/`, `checklists/`

Il *cosa è obbligatorio*. Standard prescrittivi per linguaggio, framework, pattern, processo,
ciascuno con il proprio criterio di verifica.

**Caratteristica distintiva**: ogni regola è **verificabile**. Se non si può controllare che sia
rispettata, non è una regola: è un'opinione, e va scritta altrove.

**Se manca**: ogni progetto diverge, la revisione diventa una questione di gusto personale.

### 3. Agenti — `agents/`, `prompts/`

Il *chi fa cosa*. Sedici agenti specializzati, ciascuno con responsabilità, input, output, limiti,
workflow e prompt completo.

**Caratteristica distintiva**: gli agenti hanno **limiti espliciti**. Un agente che può fare tutto
non è affidabile; un agente che può fare una cosa sola, bene, e sa quando fermarsi, lo è.

**Se manca**: l'automazione produce risultati non ripetibili, impossibili da revisionare.

### 4. Codice — `foundation/`, `templates/`, `modules/`

Il *già fatto*. La Foundation è codice PHP installabile; i template sono stub da istanziare;
i moduli sono unità funzionali complete e riutilizzabili.

**Caratteristica distintiva**: è codice **eseguibile e testato**, non pseudo-codice. È la
specifica più precisa che esista, perché o funziona o non funziona.

**Se manca**: le regole restano teoriche e ogni progetto le reinterpreta.

### 5. Processo — `workflows/`, `deployment/`

Il *in quale ordine*. La sequenza di fasi, i quality gate tra una fase e l'altra, le procedure di
rilascio e di esercizio.

**Caratteristica distintiva**: definisce le **transizioni**, non solo le attività. Il valore sta
nei gate: cosa deve essere vero perché la fase successiva possa iniziare.

**Se manca**: gli agenti lavorano ma nessuno sa quando un progetto è pronto.

---

## Come gli asset interagiscono

```
                          ┌──────────────────┐
                          │   CONOSCENZA     │  perché
                          │ docs/ architecture│
                          └────────┬─────────┘
                                   │ giustifica
                                   ▼
                          ┌──────────────────┐
                          │     REGOLE       │  cosa è obbligatorio
                          │ rules/ checklists│
                          └────┬────────┬────┘
                   vincolano   │        │   vincolano
                     ┌─────────┘        └─────────┐
                     ▼                            ▼
          ┌──────────────────┐          ┌──────────────────┐
          │     AGENTI       │  usano   │     CODICE       │
          │ agents/ prompts/ │─────────▶│ foundation/      │
          └────────┬─────────┘          │ templates/       │
                   │                    │ modules/         │
                   │ eseguono           └──────────────────┘
                   ▼                            │
          ┌──────────────────┐                  │ istanzia
          │    PROCESSO      │◀─────────────────┘
          │ workflows/       │
          │ deployment/      │
          └────────┬─────────┘
                   │ produce
                   ▼
          ┌──────────────────┐
          │ PROGETTO GENERATO│
          └────────┬─────────┘
                   │ genera apprendimento
                   └──────────────▶ ritorna nella CONOSCENZA
```

Le relazioni fondamentali:

- La conoscenza **giustifica** le regole. Una regola senza giustificazione viene aggirata.
- Le regole **vincolano** agenti e codice. Entrambi devono poterle leggere.
- Gli agenti **usano** il codice, non lo reinventano.
- Il processo **orchestra** gli agenti e verifica il risultato.
- Il progetto generato **restituisce apprendimento** alla conoscenza. Senza questo anello, la
  Factory invecchia.

---

## Cosa la Factory non è

| Non è | Perché è importante distinguerlo |
|---|---|
| **Un framework** | Non sostituisce Laravel: lo usa e ne prescrive l'uso |
| **Un boilerplate da copiare** | La copia diverge; la Foundation è una dipendenza versionata |
| **Un generatore di codice** | Un generatore produce codice; la Factory produce *progetti conformi*, con test, documentazione e pipeline |
| **Un'applicazione** | Non ha utenti finali, non si installa, non si rilascia in produzione |
| **Un prodotto verticale** | Non contiene logica di magazzino, CRM o ticket |
| **Una raccolta di best practice generiche** | Le regole sono specifiche di WidStudios e vincolanti |
| **Un progetto una tantum** | È un prodotto interno con manutenzione continua |

---

## Il confine: Factory o progetto?

La domanda si presenta ogni giorno. Il test è a tre passaggi.

### Test 1 — generalità

> «Questo artefatto sarebbe utile, **identico**, in almeno tre software diversi?»

- Sì → candidato per la Factory.
- No → appartiene al progetto.

Attenzione al «identico»: se serve in tre progetti ma ogni volta diverso, non è ancora pronto per
essere astratto. È un pattern, non un componente.

### Test 2 — indipendenza dal dominio

> «Per capire cosa fa, serve conoscere il dominio applicativo?»

- No → Factory.
- Sì → progetto.

`ExportCsvAction` non richiede di sapere cosa si esporta: Factory.
`CalcolaScadenzaLottoAction` richiede di conoscere la normativa sui dispositivi medici: progetto.

### Test 3 — stabilità

> «Cambierà per motivi di dominio o per motivi tecnici?»

- Tecnici → Factory (evolve con lo stack, lentamente).
- Di dominio → progetto (evolve con il cliente, rapidamente).

Un componente che cambia per motivi di dominio ma vive nella Factory costringe a rilasciare una
versione della piattaforma ogni volta che un cliente cambia idea.

### Tabella di decisione

| Artefatto | Test 1 | Test 2 | Test 3 | Destinazione |
|---|---|---|---|---|
| Gestione ruoli e permessi | sì | no | tecnico | Foundation |
| Risoluzione del tenant dalla richiesta | sì | no | tecnico | Foundation |
| Export CSV di una qualsiasi collezione | sì | no | tecnico | Foundation |
| Modulo notifiche multicanale | sì | no | tecnico | Modulo di catalogo |
| Widget «fatturato del mese» | no | sì | dominio | Progetto |
| Calcolo scadenza lotto sanitario | no | sì | dominio | Progetto |
| Template di Filament Resource | sì | no | tecnico | Template |
| Pipeline CI per Laravel multitenant | sì | no | tecnico | Deployment |
| Regola «tutte le Action sono `final`» | sì | no | tecnico | Regola |

Nei casi dubbi vale il principio conservativo: **resta nel progetto**. Promuovere dopo è facile;
rimuovere dalla Foundation qualcosa che tre progetti già usano è una migrazione breaking.

---

## Il ciclo di feedback

La Factory che non riceve ritorno dai progetti diventa, nel giro di un anno, un manuale che
descrive un'azienda che non esiste più.

```
Factory ──genera──▶ Progetto ──produce──▶ Esperienza reale
   ▲                                            │
   │                                            │
   └──────── promozione / correzione ◀──────────┘
```

Tre forme di ritorno, tutte obbligatorie:

1. **Promozione di codice.** Alla terza occorrenza dello stesso problema risolto in modo simile,
   la soluzione sale nella Foundation.
2. **Correzione di regole.** Una regola sistematicamente derogata è una regola sbagliata: si
   corregge, non si impone più forte.
3. **Affinamento dei prompt.** Se il Reviewer respinge sempre lo stesso tipo di output, il difetto
   è nel prompt a monte, non nell'esecuzione.

Chi lavora su un progetto ha il **dovere** di riportare: senza questo, il ciclo si spezza.

---

## Esempi

### Esempio 1 — promozione corretta

Tre progetti implementano l'esportazione CSV con gli stessi problemi (memoria su dataset grandi,
encoding, intestazioni tradotte). La terza volta si promuove: nasce il modulo `import-export`,
con streaming, encoding configurabile e traduzione delle intestazioni. I tre progetti migrano al
modulo nella loro successiva manutenzione.

### Esempio 2 — promozione sbagliata (da non imitare)

Un progetto implementa un motore di workflow approvativo. Sembra generale, viene subito promosso
nella Foundation. Sei mesi dopo, il secondo progetto che ne ha bisogno scopre che l'astrazione
non regge: gli servono approvazioni parallele, non previste. La modifica è breaking, la migrazione
costa più della riscrittura.

*Lezione:* una sola occorrenza non basta, per quanto l'astrazione sembri evidente.

### Esempio 3 — regola sbagliata riconosciuta

La regola «ogni Repository espone solo metodi che ritornano entità di dominio» viene derogata in
sette casi su dieci per i report, che hanno bisogno di proiezioni. La regola non va imposta con
più forza: va corretta, distinguendo i Repository (entità) dai Query object (proiezioni).

---

## Best practice

- Rileggere il test dei tre passaggi **prima** di ogni promozione, anche quando sembra ovvia.
- Tenere gli asset separati: la conoscenza non entra nelle regole, le regole non entrano nel codice
  come commenti.
- Ogni asset ha un indice: se un artefatto non è nell'indice, per la Factory non esiste.
- Considerare l'anello di feedback parte del lavoro di progetto, non un'attività extra.
- Quando un artefatto è dubbio, lasciarlo nel progetto e annotarlo come candidato.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Promuovere alla prima occorrenza | Astrazione sbagliata, costosa da correggere | Regola della terza occorrenza |
| Portare dominio nella Factory | La piattaforma diventa specifica e inutile agli altri | Applicare il test di indipendenza dal dominio |
| Copiare la Foundation nel progetto | Divergenza immediata e silenziosa | Dipendenza Composer versionata |
| Regole senza giustificazione | Vengono aggirate appena scomode | Collegare ogni regola alla sua ADR o motivazione |
| Nessun ritorno dai progetti | La Factory descrive un'azienda che non esiste più | Revisione periodica con chi sviluppa |
| Agenti senza limiti espliciti | Output non ripetibile, non revisionabile | Sezione «limiti» obbligatoria per ogni agente |

---

## Checklist

- [ ] So elencare i cinque asset e cosa contiene ciascuno.
- [ ] So applicare il test dei tre passaggi per decidere Factory o progetto.
- [ ] Conosco la regola della terza occorrenza.
- [ ] So che cosa la Factory **non** è, e non la uso in quel modo.
- [ ] So come si riporta un apprendimento dal progetto alla Factory.

---

## Riferimenti

- [Visione](01-vision.md)
- [Struttura del repository](05-repository-structure.md)
- [I dodici principi](04-principles.md)
- [Foundation](../../foundation/README.md) · [Moduli](../../modules/README.md)
- [Governance](../../governance/README.md)
