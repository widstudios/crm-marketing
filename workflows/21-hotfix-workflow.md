# Workflow — hotfix

> Correzione urgente di un difetto in produzione: percorso accelerato, standard invariati.

| | |
|---|---|
| **Durata indicativa** | 1-4 ore |
| **Prompt** | [`prompts/library/fix-bug.md`](../prompts/library/fix-bug.md) |

---

## Indice

1. [Descrizione](#descrizione) 2. [Quando si usa](#quando-si-usa) 2. [Cosa cambia rispetto al percorso ordinario](#cosa-cambia-rispetto-al-percorso-ordinario)
3. [Sequenza](#sequenza) 4. [Quality gate](#quality-gate) 5. [Dopo l'hotfix](#dopo-lhotfix)
6. [Esempi](#esempi) 7. [Best practice](#best-practice) 8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist) 10. [Riferimenti](#riferimenti)

---

## Descrizione

Un hotfix è il percorso ordinario a cui sono state tolte alcune fasi, con una decisione esplicita
su quali e perché. Non è il percorso ordinario fatto in fretta: la differenza è che le omissioni
sono dichiarate e recuperate dopo, invece di essere scoperte più avanti da qualcun altro.

Questo documento dice quali fasi restano obbligatorie anche sotto pressione — il test rosso prima
della correzione e il gate di sicurezza non si saltano mai — e che cosa va ripreso una volta
rientrata l'urgenza.

---

## Quando si usa

| Situazione | Percorso |
|---|---|
| Difetto che blocca l'operatività in produzione | **hotfix** |
| Difetto di sicurezza | **hotfix**, con procedura di incidente |
| Difetto che produce dati errati | **hotfix**, con valutazione dei dati storici |
| Difetto non bloccante | correzione ordinaria, prossimo rilascio |
| Comportamento mancante | non è un difetto: è una feature |

L'urgenza si valuta sull'**impatto operativo**, non sulla pressione della segnalazione.

---

## Cosa cambia rispetto al percorso ordinario

| Aspetto | Ordinario | Hotfix |
|---|---|---|
| Branch | da `main` | da `main`, con merge anche su eventuali branch di release |
| Progettazione formale | sì | ridotta |
| Revisione | prima del merge | **prima del merge**, invariata |
| Test di regressione | obbligatorio | **obbligatorio**, invariato |
| Finestra di rilascio | rispettata | qualunque momento |
| Changelog | al rilascio | immediato |
| Comunicazione al cliente | secondo preavviso | immediata |

Ciò che **non** cambia: il test di regressione, la revisione, `composer qa`, il changelog. Un hotfix
senza test di regressione è un difetto che tornerà.

---

## Sequenza

```
 1. Valutazione dell'impatto: quanti utenti, quali dati, da quando
 2. Mitigazione immediata, se possibile: rollback, disabilitazione della funzionalità
 3. Comunicazione al cliente: cosa sta succedendo, cosa stiamo facendo
 4. Riproduzione in staging
 5. Test rosso
 6. Diagnosi della causa
 7. Correzione minima
 8. Test verde, suite verde
 9. Revisione (ridotta ma presente)
10. Rilascio
11. Verifica in produzione
12. Comunicazione di risoluzione
13. Valutazione dei dati storici: serve una correzione?
14. Post-mortem, entro 5 giorni
```

Il passo 2 precede la diagnosi: **ripristinare il servizio viene prima di capire la causa**.

---

## Quality gate

Ridotto ma non assente:

- [ ] Difetto riprodotto.
- [ ] Test di regressione scritto **prima** della correzione, ora verde.
- [ ] Suite verde senza modifiche ai test esistenti.
- [ ] `composer qa` verde.
- [ ] Revisione da parte di una seconda persona.
- [ ] Impatto sui dati valutato.
- [ ] Changelog aggiornato.
- [ ] Cliente informato.

---

## Dopo l'hotfix

| Attività | Entro |
|---|---|
| Verifica in produzione | immediata |
| Comunicazione di risoluzione | immediata |
| Correzione dei dati storici, se necessaria | secondo l'impatto |
| Post-mortem | 5 giorni lavorativi |
| Azioni correttive del post-mortem | secondo la scadenza assegnata |
| Verifica che il test di regressione sia nella suite principale | al merge |

Il post-mortem è **senza attribuzione di colpa**: se le persone temono conseguenze, le informazioni
che servono non emergono, e il prossimo incidente sarà uguale.

---

## Esempi

### Esempio 1 — hotfix gestito bene

```
14:12  primi errori 500 sull'inserimento movimenti
14:18  allarme «tasso di errore > 5%»
14:22  valutazione: tutti i tenant, funzionalità principale bloccata
14:25  comunicazione ai clienti
14:28  decisione: rollback, causa da individuare dopo
14:35  rollback completato, errori azzerati
14:40  comunicazione di ripristino
15:40  causa individuata: relazione non caricata su un percorso specifico
16:00  test rosso scritto
16:20  correzione, test verde, suite verde
16:40  revisione, merge
17:00  rilascio, verifica
+3 gg  post-mortem con tre azioni correttive
```

### Esempio 2 — errore da evitare

```
14:12  errori in produzione
14:20  correzione «al volo» direttamente in produzione, senza test
14:25  sembra funzionare
15:00  il difetto si ripresenta in una forma diversa
15:30  seconda correzione al volo
        …
```

Nessun test di regressione, nessuna revisione, nessuna traccia di cosa è stato cambiato.

---

## Best practice

- Mitigare prima di diagnosticare: il rollback è quasi sempre la mitigazione più rapida.
- Comunicare entro quindici minuti, anche senza avere risposte.
- Scrivere il test di regressione anche sotto pressione: costa dieci minuti ed evita la ricomparsa.
- Valutare sempre l'impatto sui dati storici.
- Fare il post-mortem anche quando la causa sembra ovvia.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Diagnosticare prima di mitigare | Disservizio prolungato | Mitigazione immediata |
| Correzione senza test di regressione | Il difetto torna | Test rosso prima |
| Correzione diretta in produzione | Nessuna traccia, nessuna revisione | Percorso con branch e merge |
| Nessuna revisione «perché è urgente» | Difetti introdotti dalla correzione | Revisione ridotta ma presente |
| Impatto sui dati non valutato | Dati errati restano | Valutazione obbligatoria |
| Post-mortem saltato | Lo stesso incidente si ripete | Entro 5 giorni |
| Correzione di dati non comunicata | Il cliente lo scopre da solo | Comunicazione esplicita |

---

## Checklist

- [ ] Impatto valutato: utenti, dati, durata.
- [ ] Mitigazione applicata prima della diagnosi.
- [ ] Cliente informato entro i tempi previsti.
- [ ] Difetto riprodotto in staging.
- [ ] Test di regressione scritto prima della correzione.
- [ ] Suite verde senza modifiche ai test esistenti.
- [ ] Revisione effettuata.
- [ ] Rilascio verificato in produzione.
- [ ] Dati storici valutati.
- [ ] Changelog aggiornato, comunicazione di risoluzione inviata.
- [ ] Post-mortem pianificato entro 5 giorni.

---

## Riferimenti

- [Workflow](README.md) · [Prompt correggi difetto](../prompts/library/fix-bug.md)
- [Gestione degli incidenti](../docs/05-operations/05-incident-management.md)
- [Gestione dei rilasci](../docs/05-operations/02-release-management.md)
- [Git](../rules/git.md) · [Deployment](../rules/deployment.md)
