# Versionamento dei progetti

> Come si numerano, si taggano e si rilasciano le versioni di un'applicazione generata dalla
> Factory, e come si tiene traccia della Foundation che incorpora.

---

## Indice

1. [Descrizione](#descrizione)
2. [Schema di versionamento](#schema-di-versionamento)
3. [Cosa conta come modifica per l'utente](#cosa-conta-come-modifica-per-lutente)
4. [Tag e branch](#tag-e-branch)
5. [Changelog di progetto](#changelog-di-progetto)
6. [Allineamento con la Factory](#allineamento-con-la-factory)
7. [Versionamento delle API](#versionamento-delle-api)
8. [Versionamento dello schema](#versionamento-dello-schema)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Un progetto generato ha utenti reali: la sua versione comunica **a loro**, non agli sviluppatori.
Per questo il criterio non è la dimensione della modifica tecnica, ma il suo effetto su chi usa
il software.

Il progetto porta con sé anche due versionamenti tecnici che non coincidono con il proprio: quello
della Foundation che incorpora e quello delle API che espone.

---

## Schema di versionamento

`MAJOR.MINOR.PATCH`, con criteri orientati all'utente:

| Incremento | Significato per l'utente | Esempi |
|---|---|---|
| **MAJOR** | devi imparare qualcosa di nuovo o rifare qualcosa | nuovo flusso di lavoro, rimozione di una funzione, cambio di terminologia, migrazione dati che richiede fermo |
| **MINOR** | c'è qualcosa in più, il resto funziona come prima | nuovo modulo, nuovo report, nuovo campo facoltativo |
| **PATCH** | qualcosa che non andava ora funziona | correzioni, prestazioni, testi, sicurezza |

Un refactoring interno, per quanto imponente, che non cambia nulla per l'utente è un **PATCH**.
Un'etichetta cambiata in tutta l'interfaccia, tecnicamente banale, può essere un **MAJOR**.

---

## Cosa conta come modifica per l'utente

| Modifica | Versione | Perché |
|---|---|---|
| Nuovo modulo funzionale | MINOR | aggiunge, non toglie |
| Nuovo campo obbligatorio in un form esistente | **MAJOR** | i flussi esistenti si interrompono |
| Nuovo campo facoltativo | MINOR | nessun impatto sull'esistente |
| Rimozione di un report | **MAJOR** | qualcuno lo usava |
| Rinomina di una voce di menu | **MAJOR** | la formazione e le procedure del cliente vanno aggiornate |
| Correzione di un calcolo errato | PATCH (o MAJOR se i dati storici cambiano) | dipende dall'effetto sui dati |
| Miglioramento di prestazioni | PATCH | comportamento invariato |
| Aggiornamento della Foundation, minor | PATCH | invisibile all'utente |
| Aggiornamento della Foundation, major | dipende | PATCH se invisibile, MAJOR se cambia il comportamento |
| Nuovo permesso che restringe un accesso | **MAJOR** | qualcuno perde una possibilità |
| Migrazione dati con fermo del servizio | **MAJOR** | richiede pianificazione con il cliente |

---

## Tag e branch

### Branch

| Branch | Ruolo | Vita |
|---|---|---|
| `main` | stato rilasciabile, sempre verde | permanente |
| `feat/<descrizione>` | una funzionalità | fino al merge |
| `fix/<descrizione>` | una correzione | fino al merge |
| `hotfix/<descrizione>` | correzione urgente in produzione | fino al merge |
| `release/<versione>` | stabilizzazione di una release | fino al tag |

`release/*` si usa solo quando la stabilizzazione richiede più giorni. Per i rilasci ordinari si
tagga direttamente `main`.

### Tag

```bash
git tag -a v2.4.0 -m "2.4.0 — modulo scadenze, report giacenze"
git push origin v2.4.0
```

Il tag è **annotato** (`-a`), mai leggero: porta con sé autore, data e messaggio, e sopravvive
alle riscritture della storia.

---

## Changelog di progetto

Ogni progetto ha il proprio `CHANGELOG.md`, scritto **per il cliente**, non per gli sviluppatori.

```markdown
## 2.4.0 — 2026-09-15

### Novità
- Report delle giacenze per ubicazione, esportabile in Excel.
- Notifica automatica dei lotti in scadenza entro 30 giorni.

### Miglioramenti
- L'elenco dei movimenti si apre in circa un secondo anche con oltre 100.000 righe.

### Correzioni
- La quantità disponibile non teneva conto dei movimenti annullati.

### Note per l'aggiornamento
- Nessuna azione richiesta. L'aggiornamento richiede circa 2 minuti di indisponibilità.
```

Due sezioni che non vanno omesse:

- **Note per l'aggiornamento**: azione richiesta, durata dell'indisponibilità, rischi.
- **Correzioni**: se un calcolo era errato, va detto. Nasconderlo produce clienti che scoprono da
  soli che i dati storici erano sbagliati.

Il changelog tecnico, se serve, è un file separato (`CHANGELOG-DEV.md`).

---

## Allineamento con la Factory

Ogni progetto dichiara nel proprio `CLAUDE.md`:

```markdown
## Allineamento

| | |
|---|---|
| Factory di riferimento | `factory-v2.4.0` |
| Foundation installata | `^2.4` (vedi composer.lock) |
| Ultimo allineamento | 2026-09-15 |
| Deroghe attive | 2 (vedi sezione Deroghe) |
```

La dichiarazione permette a chiunque — persona o agente — di sapere **quali regole erano vigenti**
quando il progetto è stato costruito, e quanto è distante dallo standard corrente.

Quando il divario supera due versioni minor della Factory, si pianifica un allineamento. Un
progetto fermo a una Factory di due anni prima non è «stabile»: è fuori standard, e ogni intervento
costa di più.

---

## Versionamento delle API

Le API hanno un ciclo di vita **proprio**, indipendente dall'applicazione: i loro consumatori non
aggiornano quando aggiorniamo noi.

| Aspetto | Regola |
|---|---|
| Versione nel percorso | `/api/v1/...` |
| Incremento della versione | solo per modifiche incompatibili |
| Aggiunta di un campo in risposta | non incompatibile, nessuna nuova versione |
| Rimozione o rinomina di un campo | incompatibile, nuova versione |
| Cambio di tipo di un campo | incompatibile, nuova versione |
| Nuovo parametro obbligatorio | incompatibile, nuova versione |
| Convivenza tra versioni | almeno 12 mesi |
| Deprecazione | header `Deprecation` e `Sunset`, più comunicazione ai consumatori |

Dettagli: [`rules/rest-api.md`](../../rules/rest-api.md).

---

## Versionamento dello schema

Lo schema del database è versionato dalle migration, con due vincoli specifici della multitenancy:

1. **Ogni migration è reversibile.** Senza `down()` non esiste rollback, e con N tenant un
   rollback manuale non è praticabile.
2. **Le migration tenant sono idempotenti rispetto ai tenant**: eseguirle su un tenant già
   allineato non deve produrre errori, perché il provisioning di un nuovo tenant esegue tutta la
   storia.

Per le modifiche che richiedono la trasformazione di dati esistenti si usa il pattern in tre fasi,
che evita il fermo del servizio:

```
v2.4.0  aggiungi la nuova colonna, scrivi su entrambe (vecchia e nuova)
v2.5.0  migra i dati storici in differita, leggi dalla nuova
v2.6.0  rimuovi la vecchia colonna
```

Tre rilasci invece di uno, ma nessuna finestra di indisponibilità e rollback possibile ad ogni
passo.

---

## Esempi

### Esempio 1 — refactoring imponente, PATCH

Riscrittura completa del livello di persistenza del modulo inventario: 40 file toccati, nuovo
repository, query ottimizzate.

Per l'utente: nulla cambia, tranne che gli elenchi sono più veloci. → `2.4.0` → `2.4.1`.

### Esempio 2 — modifica banale, MAJOR

Il campo «Note» diventa obbligatorio nei movimenti di scarico, su richiesta della qualità.

Tecnicamente: una riga di validazione. Per l'utente: tutti i flussi esistenti si interrompono, il
personale va informato. → `2.4.1` → `3.0.0`, con nota nel changelog e comunicazione al cliente.

### Esempio 3 — correzione che cambia i dati storici

Il calcolo della giacenza non considerava i movimenti annullati: i valori storici erano errati.

La correzione cambia i numeri che il cliente vede. → **MAJOR**, con nota esplicita nel changelog
e comunicazione preventiva: nasconderlo sarebbe scoperto comunque, con conseguenze peggiori.

---

## Best practice

- Versionare pensando all'utente, non alla dimensione del diff.
- Scrivere il changelog **durante** lo sviluppo, non il giorno del rilascio.
- Dichiarare sempre l'indisponibilità prevista nelle note di aggiornamento.
- Taggare solo commit su cui la CI è verde.
- Allinearsi alla Factory almeno ogni due versioni minor.
- Usare il pattern in tre fasi per ogni migrazione di dati non banale.
- Comunicare le correzioni che alterano dati storici, sempre.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Versionare in base alle righe di codice | Le versioni non dicono nulla all'utente | Criterio orientato all'utente |
| Changelog scritto per sviluppatori | Il cliente non lo legge e non si aggiorna | Linguaggio dell'utente, file tecnico separato |
| Tag leggeri | Nessuna traccia di autore e data | `git tag -a` |
| Nessuna dichiarazione di allineamento | Impossibile sapere quali regole valevano | Sezione nel `CLAUDE.md` |
| API versionate insieme all'applicazione | I consumatori si rompono ad ogni rilascio | Ciclo di vita indipendente |
| Migrazione dati in un solo rilascio | Fermo del servizio e rollback impossibile | Pattern in tre fasi |
| Correzione di dati storici non comunicata | Perdita di fiducia | Nota esplicita nel changelog |

---

## Checklist

- [ ] L'incremento riflette l'impatto sull'utente.
- [ ] `CHANGELOG.md` aggiornato, in linguaggio dell'utente.
- [ ] Note per l'aggiornamento con azione richiesta e indisponibilità.
- [ ] Tag annotato su un commit con CI verde.
- [ ] `CLAUDE.md` dichiara Factory e Foundation di riferimento.
- [ ] Le API hanno una versione propria, coerente con le loro modifiche.
- [ ] Le migrazioni di dati usano il pattern in tre fasi.

---

## Riferimenti

- [Versionamento della Factory](../../governance/versioning.md)
- [Gestione dei rilasci](../05-operations/02-release-management.md)
- [Regole Git](../../rules/git.md) · [Commit](../../rules/commit.md) · [REST API](../../rules/rest-api.md)
- [Checklist di rilascio](../../checklists/release-checklist.md)
