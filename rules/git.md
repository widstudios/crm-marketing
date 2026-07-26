# Regole — Git

> Branch, merge, storia. Una storia leggibile è la prima forma di documentazione di un progetto.

---

## Indice

1. [Descrizione](#descrizione)
2. [Branch](#branch)
3. [Storia](#storia)
4. [Merge](#merge)
5. [Tag](#tag)
6. [Cosa non entra nel repository](#cosa-non-entra-nel-repository)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

La storia di Git è l'unico documento che registra **perché** il codice è arrivato in quello stato.
Una storia curata permette di capire una modifica di tre anni prima; una storia di commit «wip» e
«fix» non serve a nulla.

---

## Branch

**R1.** `main` è sempre in stato rilasciabile e sempre verde.
*Verifica:* protezione del branch, pipeline. *Livello: vincolante.*

**R2.** Nessun commit diretto su `main`: si passa da una pull request.
*Verifica:* protezione del branch.

**R3.** Il nome del branch segue lo schema `<tipo>/<descrizione-in-kebab-case>`.

| Tipo | Uso |
|---|---|
| `feat/` | nuova funzionalità |
| `fix/` | correzione |
| `hotfix/` | correzione urgente in produzione |
| `chore/` | manutenzione, dipendenze, configurazione |
| `docs/` | sola documentazione |
| `refactor/` | ristrutturazione senza cambio di comportamento |
| `test/` | soli test |
| `release/` | stabilizzazione di una release |

*Verifica:* protezione del branch con schema di nome.

**R4.** Un branch, una modifica concettuale.
*Verifica:* revisione.

**R5.** I branch vivono al massimo **5 giorni** lavorativi.
*Motivo:* oltre, i conflitti diventano ingestibili. *Verifica:* metriche.

**R6.** Il branch si cancella dopo il merge.
*Verifica:* configurazione del repository.

---

## Storia

**R7.** Rebase su `main` **quotidiano** durante lo sviluppo.
*Motivo:* conflitti piccoli invece di conflitti grandi. *Verifica:* prassi.

**R8.** Nessun rebase o riscrittura di storia già condivisa su branch usati da altri.
*Verifica:* prassi.

**R9.** Nessun merge commit di `main` dentro il branch: si usa il rebase.
*Motivo:* mantiene la storia lineare e leggibile. *Verifica:* revisione.

**R10.** I commit sono coerenti: un commit, un cambiamento concettuale.
*Verifica:* revisione.

**R11.** `git add -p` prima di ogni commit.
*Motivo:* è la prima revisione del codice, fatta da chi lo ha scritto, quando correggere costa
zero. Intercetta `dd()` dimenticati, commenti di debug, file temporanei.
*Verifica:* prassi, revisione.

**R12.** Nessun commit contiene codice commentato, `dd()`, `dump()` o file temporanei.
*Verifica:* test di architettura, revisione.

---

## Merge

**R13.** Il merge avviene con **squash** quando il branch contiene commit di lavoro non
significativi, con **merge commit** quando la sequenza dei commit è informativa.
*Verifica:* revisione.

**R14.** Il merge richiede: pipeline verde, almeno una revisione approvata, conversazioni risolte.
*Verifica:* protezione del branch.

**R15.** Chi apre la pull request esegue il merge, dopo l'approvazione.
*Motivo:* chi ha scritto la modifica è chi sa quando è il momento giusto.
*Verifica:* prassi.

**R16.** Se la pipeline su `main` diventa rossa, la correzione ha **priorità assoluta** su qualsiasi
altra attività.
*Verifica:* prassi. *Livello: vincolante.*

---

## Tag

**R17.** Ogni rilascio ha un tag **annotato**.

```bash
git tag -a v2.4.0 -m "2.4.0 — modulo scadenze, report giacenze"
git push origin v2.4.0
```

*Motivo:* il tag leggero non porta autore, data e messaggio.
*Verifica:* revisione dei tag.

**R18.** Si tagga solo un commit su cui la pipeline è verde.
*Verifica:* processo di rilascio.

**R19.** I tag non si spostano né si cancellano dopo la pubblicazione.
*Verifica:* protezione dei tag.

---

## Cosa non entra nel repository

| Elemento | Perché |
|---|---|
| `.env` | contiene segreti |
| `vendor/`, `node_modules/` | ricostruibili |
| File di build (`public/build/`) | generati in CI |
| Dump di database | volume, dati personali |
| File di IDE personali | preferenze individuali |
| Segreti, chiavi, certificati | esposizione |
| File temporanei, log | rumore |

**R20.** `composer.lock` e `package-lock.json` **sono** versionati.
*Motivo:* build riproducibili. *Verifica:* presenza nel repository.

**R21.** Nessun segreto nel repository, nemmeno nella storia.
*Motivo:* la storia è pubblica quanto lo stato corrente: un segreto committato e poi rimosso resta
recuperabile. *Verifica:* scansione dei segreti in CI. *Livello: assoluto.*

---

## Esempi

### Esempio 1 — ciclo conforme

```bash
git checkout main && git pull --rebase origin main
git checkout -b feat/archiviazione-fornitori

# … sviluppo …
composer qa
git add -p
git commit -m "feat(suppliers): archiviazione con vincolo di stato"

git fetch origin && git rebase origin/main
git push -u origin feat/archiviazione-fornitori
# pull request, revisione, merge, cancellazione del branch
```

### Esempio 2 — storia da evitare

```
* 4f2a1b9 fix
* 8c3d2e1 wip
* 1a9f4c7 altre modifiche
* 5e8b3a2 rimetto come prima
* 9d1c6f4 prova
```

Nessuno di questi messaggi permette di capire cosa è cambiato o perché. La stessa sequenza, con
squash e un messaggio conforme, sarebbe leggibile a distanza di anni.

---

## Best practice

- Rebase quotidiano: i conflitti piccoli si risolvono in minuti.
- `git add -p` sempre: costa trenta secondi e intercetta gli errori più imbarazzanti.
- Branch corti: sotto i cinque giorni, sotto le 400 righe di diff.
- Verificare la pipeline su `main` dopo ogni merge.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Commit diretti su `main` | Nessuna revisione, pipeline rossa | Protezione del branch |
| Branch che vive settimane | Conflitti ingestibili | Massimo 5 giorni |
| Nessun rebase per giorni | Conflitti grandi e rischiosi | Rebase quotidiano |
| `git add .` senza revisione | `dd()` e file temporanei committati | `git add -p` |
| Segreto committato | Recuperabile dalla storia per sempre | Scansione, rotazione della credenziale |
| Tag leggeri | Nessuna traccia di autore e data | `git tag -a` |
| `main` rosso lasciato tale | Blocca tutto il team | Priorità assoluta |
| `composer.lock` non versionato | Build non riproducibili | Versionarlo |

---

## Checklist

- [ ] `main` protetto, verde, senza commit diretti.
- [ ] Nome del branch conforme allo schema.
- [ ] Un branch, una modifica concettuale, sotto i 5 giorni.
- [ ] Rebase quotidiano su `main`.
- [ ] `git add -p` eseguito; nessun codice di debug.
- [ ] Pipeline verde e revisione approvata prima del merge.
- [ ] Branch cancellato dopo il merge.
- [ ] Tag annotati su commit verdi.
- [ ] `composer.lock` versionato; nessun segreto nel repository.

---

## Riferimenti

- [Commit](commit.md) · [Code review](code-review.md) · [Deployment](deployment.md)
- [Workflow quotidiano](../docs/01-getting-started/05-daily-workflow.md)
- [Versionamento dei progetti](../docs/02-conventions/04-project-versioning.md)
