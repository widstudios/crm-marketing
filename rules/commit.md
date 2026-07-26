# Regole — Messaggi di commit

> Conventional Commits in italiano. Il messaggio spiega **perché**, il diff mostra **cosa**.

---

## Indice

1. [Descrizione](#descrizione)
2. [Formato](#formato)
3. [Tipi](#tipi)
4. [Ambito](#ambito)
5. [Oggetto](#oggetto)
6. [Corpo](#corpo)
7. [Piè di pagina](#piè-di-pagina)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

Il messaggio di commit è l'unico posto in cui si registra il **motivo** di una modifica. Il diff
mostra cosa è cambiato; nessuno strumento può ricostruire perché.

Il formato strutturato serve anche a generare changelog e a individuare le modifiche incompatibili.

---

## Formato

```
<tipo>(<ambito>): <oggetto>

<corpo facoltativo>

<piè di pagina facoltativo>
```

**R1.** La prima riga non supera i **72 caratteri**.
*Verifica:* hook locale, verifica in CI.

**R2.** Il tipo è obbligatorio e appartiene all'elenco ammesso.
*Verifica:* verifica in CI.

**R3.** L'oggetto è in **italiano**, all'imperativo, senza punto finale.
*Verifica:* revisione.

**R4.** Le righe del corpo non superano i 100 caratteri.
*Verifica:* revisione.

---

## Tipi

| Tipo | Uso | Nel changelog |
|---|---|---|
| `feat` | nuova funzionalità | Novità |
| `fix` | correzione di un difetto | Correzioni |
| `perf` | miglioramento di prestazioni | Miglioramenti |
| `refactor` | ristrutturazione senza cambio di comportamento | no |
| `docs` | sola documentazione | no |
| `test` | soli test | no |
| `chore` | manutenzione, dipendenze, configurazione | no |
| `ci` | pipeline | no |
| `build` | build e asset | no |
| `style` | formattazione senza cambio di comportamento | no |
| `revert` | annullamento di un commit precedente | Correzioni |

**R5.** `feat` e `fix` sono gli unici tipi che descrivono modifiche visibili all'utente: da essi si
genera il changelog.
*Verifica:* generazione del changelog.

---

## Ambito

**R6.** L'ambito è il modulo o l'area toccata, in `kebab-case`.

```
feat(suppliers): …
fix(inventory): …
chore(deps): …
docs(rules): …
ci(pipeline): …
```

**R7.** L'ambito è facoltativo solo per le modifiche trasversali.
*Verifica:* revisione.

---

## Oggetto

**R8.** L'oggetto descrive il **risultato**, non l'attività svolta.

```
✗  feat(suppliers): modifiche al modello fornitori
✓  feat(suppliers): archiviazione con vincolo sui movimenti recenti
```

*Verifica:* revisione.

**R9.** Nessun oggetto generico: «aggiornamenti», «modifiche varie», «wip», «fix», «sistemato».
*Verifica:* verifica in CI su un elenco di parole vietate. *Livello: vincolante.*

**R10.** L'oggetto è comprensibile senza leggere il diff.
*Verifica:* revisione.

---

## Corpo

Obbligatorio quando la modifica non è autoevidente. Risponde a tre domande:

1. **Perché** era necessaria?
2. **Come** è stata affrontata, se non è ovvio?
3. Quali **conseguenze** ha?

```
fix(inventory): considera i movimenti annullati nel calcolo della giacenza

La giacenza sommava tutti i movimenti, compresi quelli annullati: i valori
mostrati erano superiori a quelli reali per tutti i lotti con annullamenti.

Il calcolo ora filtra per stato del movimento. I valori storici vengono
ricalcolati dal comando stock:recalculate, da eseguire dopo il deploy.

Refs: #482
```

**R11.** Il corpo è obbligatorio per `fix` che alterano dati esistenti.
*Verifica:* revisione.

---

## Piè di pagina

| Marcatore | Uso |
|---|---|
| `Refs: #123` | riferimento a un ticket |
| `Closes: #123` | chiude un ticket |
| `BREAKING CHANGE:` | modifica incompatibile, con descrizione della migrazione |
| `Co-Authored-By:` | contributo condiviso |
| `Reverts: <hash>` | annullamento |

**R12.** `BREAKING CHANGE:` è obbligatorio per ogni modifica incompatibile, con la descrizione
dell'azione richiesta.

```
feat(api): richiede il campo warehouse_id nei movimenti

BREAKING CHANGE: POST /api/v1/movements richiede warehouse_id.
I consumatori devono aggiornare le proprie integrazioni. La versione v1 senza
il campo resta attiva fino al 2027-08-01 e restituisce l'header Sunset.
```

*Verifica:* verifica in CI, generazione del changelog. *Livello: vincolante.*

---

## Esempi

### Esempio 1 — conformi

```
feat(inventory): notifica dei lotti in scadenza entro 30 giorni

fix(suppliers): impedisce la riattivazione di un fornitore archiviato

perf(inventory): elimina l'N+1 sull'elenco dei lotti

Le colonne articolo e ubicazione eseguivano una query per riga. Con 50 righe
l'elenco produceva 101 query; ora sono 3.

refactor(inventory): estrae StockCalculator da InventoryService

docs(rules): aggiunge le regole sulla cache tenant-scoped

chore(deps): aggiorna Laravel a 12.14.2

Corregge una vulnerabilità nella validazione dei file caricati.
```

### Esempio 2 — non conformi

```
✗  fix                                    → nessun ambito, nessun oggetto
✗  wip                                    → parola vietata
✗  Aggiornato il modello fornitori.       → nessun tipo, punto finale, descrive l'attività
✗  feat: varie modifiche al magazzino     → oggetto generico
✗  feat(inventory): aggiunto campo        → non si capisce quale campo né perché
✗  FIX(Inventory): Corretto Bug           → maiuscole non conformi
```

---

## Best practice

- Scrivere il messaggio **prima** di committare, non come formalità finale.
- Chiedersi: chi legge questo messaggio tra tre anni capisce cosa e perché?
- Un commit, un cambiamento concettuale: se il messaggio contiene «e», probabilmente sono due
  commit.
- Citare il ticket, ma non affidarsi ad esso: il tracker può cambiare, la storia di Git resta.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| «wip», «fix», «modifiche» | Storia illeggibile | Oggetto descrittivo |
| Oggetto che descrive l'attività | Non dice cosa è cambiato | Descrivere il risultato |
| Nessun corpo su modifiche non ovvie | Motivazione perduta | Corpo con il perché |
| `BREAKING CHANGE` omesso | Consumatori rotti senza preavviso | Marcatore obbligatorio |
| Commit che contiene due modifiche | Rollback selettivo impossibile | Commit separati |
| Solo il numero di ticket come messaggio | Dipendenza da un sistema esterno | Messaggio autosufficiente |
| Correzione di dati non descritta | Il cliente scopre da solo che i valori erano errati | Corpo esplicito |

---

## Checklist

- [ ] Tipo valido, ambito presente dove pertinente.
- [ ] Prima riga sotto i 72 caratteri, all'imperativo, senza punto.
- [ ] Oggetto comprensibile senza leggere il diff.
- [ ] Nessuna parola vietata.
- [ ] Corpo presente se la modifica non è autoevidente.
- [ ] `BREAKING CHANGE` dichiarato dove applicabile.
- [ ] Riferimento al ticket nel piè di pagina.
- [ ] Un commit, un cambiamento concettuale.

---

## Riferimenti

- [Git](git.md) · [Code review](code-review.md)
- [Versionamento dei progetti](../docs/02-conventions/04-project-versioning.md)
- [Guida al contributo](../CONTRIBUTING.md)
