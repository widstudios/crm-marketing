# Prompt — correggere un difetto

> Test di regressione prima della correzione, sempre.

| | |
|---|---|
| **Versione** | 1.0.0 |
| **Agenti** | Backend (o competente) → Testing |

---

## Indice

1. [Quando si usa](#quando-si-usa) 2. [Prerequisiti](#prerequisiti) 3. [Sequenza](#sequenza)
4. [Il prompt](#il-prompt) 5. [Definizione di «fatto»](#definizione-di-fatto) 6. [Esempi](#esempi)
7. [Best practice](#best-practice) 8. [Errori comuni](#errori-comuni) 9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Quando si usa

| Copre | Non copre |
|---|---|
| Difetto in un comportamento esistente | comportamento mancante → `add-feature.md` |
| Regressione | requisito frainteso → analisi, non correzione |
| Difetto di prestazioni | ottimizzazione pianificata → Performance Agent |
| Vulnerabilità | incidente in corso → procedura di incidente |

---

## Prerequisiti

- [ ] Il difetto è **riproducibile**: senza riproduzione non è diagnosticabile.
- [ ] È noto il tenant e il contesto in cui si verifica.
- [ ] È noto da quale versione si manifesta, se possibile.

---

## Sequenza

```
1. Riproduzione        in locale o in staging, con i dati che lo scatenano
2. Test rosso          scrive il test che descrive il comportamento corretto: deve fallire
3. Diagnosi            individuazione della causa, non del sintomo
4. Correzione          minima, sulla causa
5. Test verde          il test rosso passa; la suite resta verde
6. Verifica            manuale, con due tenant
7. Documentazione      changelog; se ha alterato dati, comunicazione esplicita
```

Il passo 2 precede il 3 deliberatamente: scrivere il test dimostra di aver capito il comportamento
atteso, e impedisce che il difetto torni.

---

## Il prompt

```markdown
Correggi un difetto nel progetto «{{ NOME_PROGETTO }}», seguendo `prompts/library/fix-bug.md`.

## Segnalazione

{{ DESCRIZIONE }}

Tenant: {{ TENANT }}
Contesto: {{ SCHERMATA_O_ENDPOINT }}
Comportamento osservato: {{ OSSERVATO }}
Comportamento atteso: {{ ATTESO }}
Da quando: {{ VERSIONE_O_DATA }}

## Procedura

### 1. Riproduci

Riproduci il difetto in locale o in staging con i dati che lo scatenano. Se non riesci a riprodurlo,
**fermati**: senza riproduzione la correzione è un tentativo.

Verifica per primo il **contesto tenant**: molte segnalazioni di «dati spariti» sono diagnosi
sbagliate su un tenant diverso da quello atteso.

### 2. Scrivi il test rosso

Prima di correggere, scrivi il test che descrive il comportamento **corretto**. Verificalo fallire.

Il test va nel livello appropriato: unitario se riguarda una regola di dominio, di feature se
riguarda un percorso completo, di isolamento se riguarda la separazione tra tenant.

### 3. Diagnostica la causa

Non il sintomo. Domande utili:
- il difetto è nel dominio, nell'applicazione, nell'infrastruttura o nella presentazione?
- perché i test esistenti non lo hanno intercettato?
- esistono altri percorsi che possono produrre lo stesso effetto?

L'ultima domanda spesso rivela che la correzione va fatta in un punto diverso da quello dove il
difetto si manifesta.

### 4. Correggi

Correzione **minima**, sulla causa. Non rifattorizzare contestualmente: sono commit diversi.

Se la correzione richiede una modifica allo schema, applica il pattern in tre rilasci se la tabella
ha dati in produzione.

### 5. Verifica

    composer qa
    php artisan test --env=testing-mysql

Il test rosso deve passare; la suite deve restare verde **senza modifiche ai test esistenti**.
Se devi modificarne uno, quel test descriveva il comportamento sbagliato: dichiaralo nel rapporto.

Verifica manualmente con **due tenant**.

### 6. Valuta l'impatto sui dati

Se il difetto ha prodotto dati errati:
- quanti record sono interessati?
- serve un comando di correzione dei dati storici?
- il cliente va informato?

Un calcolo errato corretto in silenzio produce un cliente che scopre da solo che i dati storici
erano sbagliati: è un esito peggiore della segnalazione.

## Vincoli

- **Non modificare i test esistenti** per far passare la correzione, salvo dichiararlo.
- **Non rifattorizzare** nello stesso commit.
- **Non correggere il sintomo** se la causa è altrove.

## Output

- il test di regressione;
- la correzione;
- l'eventuale comando di correzione dei dati storici;
- il rapporto con: causa, perché i test non l'hanno intercettato, impatto sui dati, necessità di
  comunicazione al cliente.
```

---

## Definizione di «fatto»

- [ ] Difetto riprodotto.
- [ ] Test di regressione scritto **prima** della correzione, e ora verde.
- [ ] Causa individuata e corretta, non il sintomo.
- [ ] Suite verde senza modifiche ai test esistenti (o modifiche dichiarate).
- [ ] Verifica manuale con due tenant.
- [ ] Impatto sui dati valutato; comando di correzione se necessario.
- [ ] Changelog aggiornato; se i dati storici cambiano, comunicazione esplicita.
- [ ] Analisi del perché i test non lo avevano intercettato.

---

## Esempi

### Esempio 1 — correzione completa

Segnalazione: «la giacenza mostra valori superiori a quelli reali».

```
Riproduzione   lotto con movimenti annullati: la somma li include
Test rosso     it('esclude i movimenti annullati dal calcolo della giacenza')
Diagnosi       StockCalculator somma tutti i movimenti senza filtrare per stato
Correzione     filtro per stato nel calcolo
Impatto dati   47 lotti su 3 tenant hanno valori errati → comando stock:recalculate
Comunicazione  changelog: «corretto il calcolo della giacenza, che non considerava i movimenti
               annullati; i valori sono stati ricalcolati»
Analisi        nessun test copriva i movimenti annullati: aggiunto anche il caso alla factory
```

### Esempio 2 — diagnosi sbagliata evitata

Segnalazione: «i fornitori sono spariti».

Prima verifica: `tenant()->id` nel contesto della richiesta. Risultato: il dominio usato non è
associato al tenant atteso. Nessun dato perso, nessuna correzione al codice: va associato il dominio
nel landlord, e aggiunto un test sulla risoluzione.

Dieci secondi di verifica al posto di ore di diagnosi.

---

## Best practice

- Verificare per primo il contesto tenant: è la causa più frequente di diagnosi errate.
- Scrivere il test prima: se non riesci a scriverlo, non hai capito il problema.
- Chiedersi sempre perché i test esistenti non l'hanno intercettato: la risposta migliora la suite.
- Comunicare le correzioni che alterano dati storici, sempre.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Correggere senza riprodurre | Si corregge il sintomo, il difetto resta | Riproduzione obbligatoria |
| Nessun test di regressione | Il difetto torna | Test rosso prima |
| Modificare i test esistenti | Il comportamento cambia senza dichiararlo | Dichiararlo |
| Rifattorizzare insieme | Impossibile isolare una regressione | Commit separati |
| Impatto sui dati non valutato | Dati errati restano in produzione | Valutazione obbligatoria |
| Correzione di dati non comunicata | Il cliente lo scopre da solo | Comunicazione esplicita |

---

## Checklist

- [ ] Difetto riprodotto, contesto tenant verificato.
- [ ] Test rosso scritto prima della correzione.
- [ ] Causa corretta, non sintomo.
- [ ] Suite verde senza modifiche ai test esistenti.
- [ ] Impatto sui dati valutato e gestito.
- [ ] Changelog e comunicazione aggiornati.

---

## Riferimenti

- [Libreria](README.md) · [Guida al debug](../../docs/03-development/07-debugging-guide.md)
- [Testing](../../rules/testing.md) · [Testing Agent](../../agents/13-testing-agent.md)
- [Troubleshooting](../../docs/06-reference/05-troubleshooting.md)
