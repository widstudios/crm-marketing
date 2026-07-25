# Ciclo di vita dello sviluppo

> Dal requisito al codice in produzione: le fasi, chi le presidia, e cosa deve essere vero per
> passare da una all'altra.

---

## Indice

1. [Descrizione](#descrizione)
2. [Le sette fasi](#le-sette-fasi)
3. [Definizione di «pronto»](#definizione-di-pronto)
4. [Definizione di «fatto»](#definizione-di-fatto)
5. [Rapporto con il master workflow](#rapporto-con-il-master-workflow)
6. [Gestione delle eccezioni](#gestione-delle-eccezioni)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Il ciclo descritto qui vale per **una modifica** dentro un progetto esistente (una feature, una
correzione, un modulo aggiuntivo). Il ciclo di creazione di un progetto intero è un'altra cosa,
e sta in [`workflows/00-master-workflow.md`](../../workflows/00-master-workflow.md).

I due condividono i principi ma hanno granularità diverse: qui si parla di ore e giorni, là di
fasi e progetti.

---

## Le sette fasi

### 1. Comprensione

**Obiettivo:** capire il problema, non la soluzione proposta.

Un requisito arriva quasi sempre già tradotto in soluzione («aggiungete un pulsante che esporta
in Excel»). Il lavoro di questa fase è risalire al problema («devo consegnare i dati al
commercialista ogni mese»), perché la soluzione migliore spesso è un'altra.

**Uscita:** il problema è descritto in una frase, i criteri di accettazione sono scritti.

### 2. Progettazione

**Obiettivo:** decidere la forma prima di scrivere.

Domande da chiudere: quali entità, quali Action, quali permessi, quali eventi, quali impatti sullo
schema, quali impatti sulle prestazioni.

Per le modifiche piccole sono dieci minuti mentali. Per quelle che toccano lo schema o
l'architettura serve una nota scritta, e talvolta una ADR di progetto.

**Uscita:** elenco degli artefatti da produrre, con la loro collocazione.

### 3. Implementazione

**Obiettivo:** realizzare, con i controlli a sinistra.

Ordine: test rosso → implementazione minima → riordino → casi limite. Vedi
[workflow quotidiano](../01-getting-started/05-daily-workflow.md).

**Uscita:** codice e test, `composer qa` verde.

### 4. Verifica

**Obiettivo:** verificare ciò che i test automatici non coprono.

| Verifica | Come |
|---|---|
| Comportamento reale | provare la funzionalità nell'interfaccia, con due tenant |
| Prestazioni | contare le query, verificare gli indici sui nuovi filtri |
| Autorizzazione | provare con un utente privo del permesso |
| Isolamento | verificare che il secondo tenant non veda nulla |
| Errori | provare i percorsi di errore, non solo quello felice |

**Uscita:** nessuna sorpresa rispetto a quanto atteso.

### 5. Revisione

**Obiettivo:** un secondo paio di occhi, guidato da una checklist.

Vedi [guida al code review](../04-quality/02-code-review-guide.md).

**Uscita:** approvazione, o richieste bloccanti risolte.

### 6. Integrazione

**Obiettivo:** portare la modifica in `main` senza rompere nulla.

Rebase, CI verde, merge, verifica su `main`, deploy in staging.

**Uscita:** la modifica è in staging e funziona.

### 7. Rilascio e osservazione

**Obiettivo:** portare in produzione e verificare che si comporti come atteso.

Vedi [gestione dei rilasci](../05-operations/02-release-management.md).

**Uscita:** in produzione, metriche e log senza anomalie nelle 24 ore successive.

---

## Definizione di «pronto»

Un requisito **non entra** in sviluppo se manca uno di questi elementi:

- [ ] Il problema è descritto, non solo la soluzione proposta.
- [ ] I criteri di accettazione sono scritti e verificabili.
- [ ] Gli attori coinvolti e i loro permessi sono chiari.
- [ ] I casi limite noti sono elencati.
- [ ] L'impatto sui dati esistenti è stato considerato.
- [ ] Esiste un interlocutore che può rispondere alle domande di dominio.

Iniziare a sviluppare un requisito non pronto è il modo più comune di sprecare una giornata:
si costruisce qualcosa di plausibile che poi va rifatto.

---

## Definizione di «fatto»

Una modifica è **fatta** quando:

- [ ] Il codice rispetta le regole applicabili in `rules/`.
- [ ] I test coprono comportamento normale, casi limite e autorizzazione.
- [ ] I test di isolamento tenant sono verdi.
- [ ] `composer qa` è verde, senza nuove voci in baseline.
- [ ] La documentazione toccata è aggiornata nello stesso commit.
- [ ] Il changelog è aggiornato se la modifica è visibile all'utente.
- [ ] La revisione è approvata.
- [ ] La modifica è verificata in staging.
- [ ] Le traduzioni sono presenti per ogni stringa visibile.

«Fatto» non significa «il codice funziona sulla mia macchina»: significa che qualcun altro può
rilasciarlo senza sapere nulla di come è stato costruito.

---

## Rapporto con il master workflow

| | Ciclo di sviluppo | Master workflow |
|---|---|---|
| Oggetto | una modifica | un progetto intero |
| Durata | ore o giorni | giorni |
| Esecutore | sviluppatore, con o senza agenti | orchestratore + 16 agenti |
| Gate | definizione di «fatto» | quality gate di fase |
| Ripetizione | continua | una volta per progetto |

Il ciclo di sviluppo è ciò che avviene **dopo** che il master workflow ha prodotto il progetto:
è la vita ordinaria dell'applicazione.

---

## Gestione delle eccezioni

| Situazione | Deviazione ammessa | Cosa resta obbligatorio |
|---|---|---|
| Hotfix in produzione | si salta la progettazione formale | test di regressione, revisione, changelog |
| Prototipo esplorativo | si salta la revisione | il codice **non** entra in `main` |
| Correzione di refuso | si saltano progettazione e verifica manuale | `composer qa` |
| Incidente grave | si rilascia prima di revisionare | revisione entro 24 ore, post-mortem |

Ogni deviazione va dichiarata nella PR. Una deviazione non dichiarata diventa la nuova prassi.

---

## Esempi

### Esempio 1 — la comprensione che cambia la soluzione

Richiesta: «aggiungete un pulsante per esportare i movimenti in Excel».

Domanda: a cosa serve l'esportazione? Risposta: il magazziniere la manda ogni lunedì al
responsabile qualità, che controlla i lotti in scadenza.

Soluzione realizzata: un report schedulato che invia automaticamente il riepilogo dei lotti in
scadenza. Il pulsante di esportazione esiste comunque, ma non è più il centro del problema.

### Esempio 2 — requisito non pronto

«Gli utenti devono poter modificare i movimenti.» Manca: quali utenti, entro quanto tempo dalla
registrazione, cosa succede alle giacenze già calcolate, si tiene traccia della modifica.

Quattro domande, cinque minuti di conversazione, mezza giornata risparmiata.

---

## Best practice

- Non iniziare senza la definizione di «pronto» soddisfatta.
- Risalire dal requisito al problema, sempre.
- Progettare per iscritto quando la modifica tocca lo schema o l'architettura.
- Verificare a mano ciò che i test non coprono, con due tenant.
- Dichiarare le deviazioni nella PR.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Implementare la soluzione proposta senza capire il problema | Si risolve il problema sbagliato | Fase di comprensione |
| Iniziare con requisiti incompleti | Rifacimenti | Definizione di «pronto» |
| Considerare «fatto» il codice che compila | Il difetto emerge in produzione | Definizione di «fatto» |
| Saltare la verifica manuale | I difetti di interfaccia passano | Verifica con due tenant |
| Deviazioni non dichiarate | Diventano la prassi | Dichiararle nella PR |

---

## Checklist

- [ ] Definizione di «pronto» soddisfatta prima di iniziare.
- [ ] Problema compreso, non solo la soluzione proposta.
- [ ] Artefatti progettati e collocati secondo il layout standard.
- [ ] Test scritti insieme al codice.
- [ ] Verifica manuale con due tenant.
- [ ] Revisione approvata.
- [ ] Definizione di «fatto» soddisfatta.

---

## Riferimenti

- [Workflow quotidiano](../01-getting-started/05-daily-workflow.md)
- [Guida allo sviluppo di una feature](02-feature-development-guide.md)
- [Master workflow](../../workflows/00-master-workflow.md)
- [Guida al code review](../04-quality/02-code-review-guide.md)
- [Checklist di definizione di fatto](../../checklists/definition-of-done.md)
