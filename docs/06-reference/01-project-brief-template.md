# Project Brief — modello

> Il documento da compilare prima di avviare un progetto. È l'unico input sostanziale di
> `loop crea`: la qualità del risultato dipende dalla sua qualità.

---

## Indice

1. [Descrizione](#descrizione)
2. [Come si compila](#come-si-compila)
3. [Il modello](#il-modello)
4. [Sezioni bloccanti](#sezioni-bloccanti)
5. [Esempi](#esempi)
6. [Best practice](#best-practice)
7. [Errori comuni](#errori-comuni)
8. [Checklist](#checklist)
9. [Riferimenti](#riferimenti)

---

## Descrizione

Il Project Brief traduce una richiesta commerciale in specifiche che un esecutore — persona o
agente — può usare senza dover indovinare. Non è un documento di vendita né un capitolato: è la
descrizione operativa di cosa il software deve fare, per chi, con quali vincoli.

Ogni ora spesa qui ne risparmia diverse nelle fasi successive, perché ogni ambiguità non risolta
si moltiplica: viene ereditata dall'architettura, dallo schema, dal codice e dai test.

---

## Come si compila

1. Copiare il modello in `docs/project-brief.md` nel repository del nuovo progetto.
2. Compilare con l'interlocutore che conosce il dominio, non da soli.
3. Marcare esplicitamente ciò che non si sa: `DA DEFINIRE` è un'informazione, un vuoto no.
4. Far rileggere al committente prima di avviare.
5. Aggiornarlo quando emergono chiarimenti: resta la fonte di verità del dominio.

---

## Il modello

```markdown
# Project Brief — <Nome Progetto>

| | |
|---|---|
| Committente | |
| Referente di dominio | |
| Data | |
| Versione del brief | |
| Factory di riferimento | factory-vX.Y.Z |

## 1. Scopo

In tre frasi: che problema risolve, per chi, e come si capisce che funziona.

## 2. Dominio

Descrizione del contesto operativo. Come lavorano oggi le persone che useranno il software.

## 3. Attori e ruoli

| Ruolo | Chi è | Cosa fa | Cosa NON deve poter fare |
|---|---|---|---|

## 4. Entità principali

| Entità | Descrizione | Attributi principali | Ciclo di vita / stati |
|---|---|---|---|

## 5. Casi d'uso primari

Per ciascuno: attore, precondizioni, flusso, esito, casi di errore.

### CU-01 — <titolo>
- **Attore**:
- **Precondizioni**:
- **Flusso**:
- **Esito**:
- **Errori possibili**:

## 6. Regole di business

Regole che valgono sempre, indipendentemente dall'interfaccia.

| # | Regola | Conseguenza se violata |
|---|---|---|

## 7. Vincoli normativi

Obblighi di legge o di settore: tracciabilità, conservazione, privacy, accessibilità.

| Vincolo | Fonte | Impatto tecnico |
|---|---|---|

## 8. Volumi attesi

| Grandezza | Anno 1 | Anno 3 | Tenant più grande |
|---|---|---|---|
| Tenant | | | — |
| Utenti per tenant | | | |
| Righe nella tabella principale | | | |
| Operazioni al giorno | | | |
| Documenti / file | | | |

## 9. Integrazioni esterne

| Sistema | Direzione | Protocollo | Frequenza | Criticità |
|---|---|---|---|---|

## 10. Requisiti non funzionali

| Requisito | Valore |
|---|---|
| Tempo di risposta atteso | |
| Disponibilità | |
| RPO / RTO | |
| Lingue | |
| Accessibilità | |
| Dispositivi | |

## 11. Che cosa il software NON fa

Elenco esplicito di ciò che resta fuori dall'ambito.

## 12. Moduli previsti

| Modulo | Origine (catalogo / dominio) | Note |
|---|---|---|

## 13. Rischi noti

| Rischio | Probabilità | Impatto | Mitigazione |
|---|---|---|---|

## 14. Domande aperte

| # | Domanda | A chi | Entro quando |
|---|---|---|---|
```

---

## Sezioni bloccanti

Il processo **non parte** se queste sezioni sono vuote:

| Sezione | Perché è bloccante |
|---|---|
| 1. Scopo | senza, non è verificabile se il risultato è corretto |
| 3. Attori e ruoli | determinano il modello di autorizzazione |
| 4. Entità principali | sono l'ossatura dello schema |
| 5. Casi d'uso primari | determinano le Action da generare |
| 6. Regole di business | determinano le invarianti del dominio |
| 7. Vincoli normativi | determinano audit, conservazione, cifratura |
| 8. Volumi attesi | determinano indici, cache e code |
| 11. Cosa NON fa | delimita l'ambito |

La colonna «Cosa NON deve poter fare» nella tabella degli attori è la più trascurata: è quella che
produce le Policy corrette invece che permissive.

---

## Esempi

### Esempio 1 — entità ben descritta

```markdown
| Entità | Descrizione | Attributi principali | Ciclo di vita |
|---|---|---|---|
| Lotto | Insieme omogeneo di un articolo con la stessa scadenza | articolo, numero, scadenza, quantità, ubicazione | disponibile → in esaurimento → esaurito / scaduto |
```

Da questa riga discendono: tabella, indici su scadenza, enum di stato con le transizioni, e la
regola che un lotto scaduto non è movimentabile in uscita.

### Esempio 2 — regola di business ben posta

```markdown
| # | Regola | Conseguenza se violata |
|---|---|---|
| RB-04 | Un lotto scaduto non può essere prelevato, nemmeno con autorizzazione | Rischio sanitario e sanzione in caso di ispezione |
```

La conseguenza dichiarata dice al progettista quanto rigidamente va imposta la regola: qui, con
un vincolo nel dominio e non solo un avviso nell'interfaccia.

### Esempio 3 — «cosa NON fa» che evita lavoro inutile

```markdown
## 11. Che cosa il software NON fa

- Non gestisce la fatturazione: resta sul gestionale contabile esistente.
- Non gestisce gli ordini a fornitore nella prima versione.
- Non calcola il valore di magazzino: solo quantità.
```

Tre righe che evitano la generazione di tre moduli e di una decina di entità.

---

## Best practice

- Compilare insieme all'interlocutore di dominio, non per interpretazione.
- Scrivere `DA DEFINIRE` invece di lasciare vuoto: rende visibile ciò che manca.
- Descrivere il ciclo di vita di ogni entità: è da lì che nascono gli enum.
- Dichiarare cosa ogni ruolo **non** deve poter fare.
- Indicare i volumi del **tenant più grande**, non la media.
- Aggiornare il brief quando emergono chiarimenti.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Brief scritto senza l'esperto di dominio | Software plausibile ma sbagliato | Compilazione congiunta |
| Sezione «cosa non fa» omessa | Ambito che si espande senza controllo | Sezione obbligatoria |
| Volumi indicati come media | Dimensionamento errato | Riferirsi al tenant più grande |
| Regole di business implicite | Ogni interfaccia le reinterpreta | Elencarle esplicitamente |
| Nessun vincolo normativo dichiarato | Audit e conservazione mancanti | Verificare con il committente |
| Brief non aggiornato dopo i chiarimenti | Fonte di verità divergente | Aggiornamento continuo |

---

## Checklist

- [ ] Tutte le sezioni bloccanti sono compilate.
- [ ] Ogni entità ha attributi e ciclo di vita.
- [ ] Ogni ruolo dichiara cosa non deve poter fare.
- [ ] Le regole di business sono numerate e hanno una conseguenza dichiarata.
- [ ] I vincoli normativi sono verificati con il committente.
- [ ] I volumi si riferiscono al tenant più grande.
- [ ] La sezione «cosa NON fa» è compilata.
- [ ] Le domande aperte hanno destinatario e scadenza.
- [ ] Il committente ha riletto e confermato.

---

## Riferimenti

- [Avviare un nuovo progetto](../01-getting-started/01-new-project.md)
- [Contratto `loop crea`](../../prompts/loop-crea.md)
- [Agente Business Analyst](../../agents/02-business-analyst-agent.md)
- [Master workflow](../../workflows/00-master-workflow.md)
