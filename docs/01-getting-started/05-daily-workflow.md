# Workflow quotidiano

> La giornata tipo di chi sviluppa su un progetto della Factory: dal ticket al merge, con i
> controlli nei punti giusti.

---

## Indice

1. [Descrizione](#descrizione)
2. [Il ciclo di una modifica](#il-ciclo-di-una-modifica)
3. [Inizio giornata](#inizio-giornata)
4. [Durante lo sviluppo](#durante-lo-sviluppo)
5. [Prima del commit](#prima-del-commit)
6. [Prima della pull request](#prima-della-pull-request)
7. [Durante la revisione](#durante-la-revisione)
8. [Dopo il merge](#dopo-il-merge)
9. [Ritmi consigliati](#ritmi-consigliati)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Il workflow quotidiano è progettato attorno a un'idea: **spostare i controlli il più a sinistra
possibile**. Un difetto trovato mentre si scrive costa minuti; lo stesso difetto trovato in
revisione costa ore; in produzione costa giorni e credibilità.

Ogni controllo descritto qui esiste perché intercetta una classe di difetti nel momento in cui
costa meno correggerla.

---

## Il ciclo di una modifica

```
ticket ──▶ branch ──▶ test rosso ──▶ implementazione ──▶ test verde
                                                            │
                            ┌───────────────────────────────┘
                            ▼
                     composer qa ──▶ commit ──▶ push ──▶ PR
                            │                            │
                       (se rosso)                        ▼
                            └──── correggi          revisione
                                                         │
                                              ┌──────────┴──────────┐
                                              ▼                     ▼
                                        richieste             approvazione
                                              │                     │
                                              └──▶ correggi         ▼
                                                                 merge
                                                                    │
                                                                    ▼
                                                              CI ──▶ staging
```

---

## Inizio giornata

```bash
git checkout main
git pull --rebase origin main
composer install          # se composer.lock è cambiato
npm ci                    # se package-lock.json è cambiato
php artisan tenants:migrate   # se ci sono nuove migration
```

Poi:

1. **Leggere il ticket per intero**, comprese le discussioni.
2. **Verificare che sia ben posto**: cosa deve essere vero perché sia considerato fatto? Se non è
   chiaro, chiedere adesso costa cinque minuti; chiederlo dopo l'implementazione costa la
   reimplementazione.
3. **Creare il branch** secondo [`rules/git.md`](../../rules/git.md):
   ```bash
   git checkout -b feat/suppliers-archiviazione
   ```

---

## Durante lo sviluppo

### L'ordine che conviene

1. **Test rosso.** Scrivere il test che descrive il comportamento atteso, verificarlo fallire.
2. **Implementazione minima.** Far passare il test senza aggiungere ciò che non è richiesto.
3. **Riordino.** Migliorare la struttura con i test verdi a fare da rete.
4. **Test dei casi limite.** Errori, autorizzazione negata, stato non ammesso, isolamento tenant.

Il test scritto per primo non è un rito: è il modo più economico per accorgersi che il requisito
non è chiaro. Se non si riesce a scrivere il test, non si è capito cosa va fatto.

### Dove va cosa

| Sto scrivendo… | Va in… | Regola |
|---|---|---|
| una regola di business | `Domain/` (entità, enum, value object) | [principio 5](../00-introduction/04-principles.md) |
| una mutazione di stato | `Application/Actions/` | [`rules/action-pattern.md`](../../rules/action-pattern.md) |
| una lettura complessa | `Application/Queries/` | [`rules/repository-pattern.md`](../../rules/repository-pattern.md) |
| un accesso a dati | `Infrastructure/Repositories/` | [`rules/repository-pattern.md`](../../rules/repository-pattern.md) |
| una traduzione HTTP | `Http/Controllers/` | [`rules/laravel.md`](../../rules/laravel.md) |
| una schermata amministrativa | `Filament/Resources/` | [`rules/filament.md`](../../rules/filament.md) |
| una regola di accesso | `Policies/` | [`rules/policies.md`](../../rules/policies.md) |
| un'operazione lenta | `Jobs/` | [`rules/queue.md`](../../rules/queue.md) |

### Controlli continui

Tenere aperto in un terminale:

```bash
php artisan test --filter=Supplier --watch
```

E, quando si tocca il frontend:

```bash
npm run dev
```

---

## Prima del commit

**Sempre, senza eccezioni:**

```bash
composer qa
```

Che esegue, in ordine:

| Passo | Verifica | Se fallisce |
|---|---|---|
| `lint` | formattazione PSR-12 e preset | `composer lint:fix` |
| `analyse` | PHPStan livello 8 | correggere, **non** aggiungere alla baseline |
| `test` | suite completa | correggere prima di committare |

Poi il commit secondo [`rules/commit.md`](../../rules/commit.md):

```bash
git add -p                    # revisione delle proprie modifiche, riga per riga
git commit -m "feat(suppliers): archiviazione fornitore con vincolo di stato"
```

`git add -p` non è pignoleria: è la prima revisione del codice, fatta da chi lo ha scritto,
quando correggere costa ancora zero. Intercetta `dd()` dimenticati, commenti di debug, file
temporanei.

---

## Prima della pull request

Verifiche aggiuntive rispetto al commit:

```bash
# Suite anche su MySQL, non solo SQLite
docker compose exec app php artisan test --env=testing-mysql

# Migration reversibili
php artisan tenants:migrate:rollback --step=1 && php artisan tenants:migrate

# Isolamento tenant
php artisan test --filter=Tenant
```

Poi si compila la descrizione della PR:

| Sezione | Contenuto |
|---|---|
| Cosa cambia | in una frase, dal punto di vista dell'utente |
| Perché | il ticket e il problema risolto |
| Come verificarlo | passi concreti per provare la modifica |
| Rischi | cosa potrebbe rompersi, cosa tenere d'occhio |
| Deviazioni | regole derogate, con motivazione |

Una PR senza «come verificarlo» costa al revisore il tempo di ricostruirlo. Moltiplicato per il
numero di revisori, è il modo più efficace di rallentare il team.

---

## Durante la revisione

**Chi riceve la revisione:**

- Risponde a **ogni** commento, anche solo con «fatto».
- Se non è d'accordo, argomenta citando la regola o il principio: la discussione diventa oggettiva.
- Non si difende dal commento: il codice è il prodotto, non l'autore.

**Chi revisiona:**

- Segue [`checklists/code-review-checklist.md`](../../checklists/code-review-checklist.md).
- Distingue **bloccante** (violazione di regola, difetto, rischio) da **suggerimento** (preferenza).
- Motiva ogni richiesta bloccante con la regola violata.
- Approva quando il codice è **corretto**, non quando è come lo avrebbe scritto lui.

Tempo di risposta atteso: entro mezza giornata lavorativa. Una PR ferma è lavoro finito che non
produce valore, e diventa più difficile da integrare ogni ora che passa.

---

## Dopo il merge

1. **Verificare la CI** sul branch principale: se è rossa, la priorità è quella, prima di
   qualsiasi altra attività.
2. **Verificare in staging** che la modifica si comporti come atteso.
3. **Aggiornare il ticket** con l'esito.
4. **Cancellare il branch**:
   ```bash
   git checkout main && git pull --rebase && git branch -d feat/suppliers-archiviazione
   ```
5. **Se durante il lavoro è emerso qualcosa di generale** — un pattern ripetuto, una regola poco
   chiara, un difetto della Foundation — riportarlo alla Factory. È la parte che si dimentica
   sempre e senza cui la piattaforma smette di migliorare.

---

## Ritmi consigliati

| Attività | Frequenza | Perché |
|---|---|---|
| `composer qa` | prima di ogni commit | intercetta il difetto quando costa meno |
| Push del branch | almeno una volta al giorno | il lavoro non vive solo sulla propria macchina |
| Rebase su `main` | quotidiano | conflitti piccoli invece che grandi |
| PR | ogni 1-2 giorni di lavoro | PR piccole si revisionano davvero |
| Aggiornamento dipendenze | mensile | aggiornamenti piccoli invece che migrazioni |
| Revisione del debito tecnico | mensile | non si accumula fino a bloccare |

Sulla dimensione delle PR: sotto le 400 righe la revisione è efficace; sopra le 1000 il revisore
approva senza leggere davvero, e la revisione diventa un rito.

---

## Esempi

### Esempio 1 — giornata tipica

```
09:00  pull, lettura del ticket, domanda di chiarimento sul comportamento atteso
09:20  branch, test rosso su ArchiveSupplierAction
09:40  implementazione, test verde
10:10  casi limite: fornitore già archiviato, permesso mancante, isolamento tenant
10:40  composer qa, git add -p, commit
10:50  Filament: azione di archiviazione che delega all'Action
11:20  composer qa, commit
11:30  push, PR con descrizione e passi di verifica
14:00  risposta ai commenti di revisione, due correzioni
15:00  approvazione, merge, verifica CI e staging
15:20  nota alla Factory: la transizione di stato si ripete in tre moduli, valutare
       una concern condivisa
```

### Esempio 2 — cosa non fare

```
09:00  inizio a sviluppare senza leggere il ticket per intero
11:00  scopro che il requisito era diverso, rifaccio
14:00  commit unico da 1.400 righe, senza eseguire i test
14:05  CI rossa
14:30  correzioni «al volo» direttamente sul branch condiviso
16:00  PR gigante che nessuno revisiona davvero
17:00  approvazione di cortesia, merge
```

Ogni riga di questo elenco corrisponde a un controllo saltato, e ognuno di quei controlli esisteva
per intercettare esattamente il problema che si è verificato.

---

## Best practice

- Chiedere chiarimenti **prima** di implementare: cinque minuti contro mezza giornata.
- Commit piccoli e coerenti: un commit, un cambiamento concettuale.
- `git add -p` sempre: è la prima revisione, ed è gratis.
- PR piccole: sotto le 400 righe di diff.
- Rispondere alle revisioni entro mezza giornata.
- Riportare alla Factory ciò che si è imparato, nello stesso giorno in cui lo si è imparato.
- Non lasciare `main` rosso: è la priorità assoluta di chi lo ha rotto.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Committare senza `composer qa` | CI rossa, tempo del team sprecato | Alias o hook locale |
| PR da migliaia di righe | Revisione superficiale, difetti che passano | Dividere in PR tematiche |
| Sviluppare senza test | Regressioni scoperte in produzione | Test rosso per primo |
| Aggiungere alla baseline PHPStan per fare prima | Debito nascosto che cresce | Correggere il codice |
| Ignorare i commenti di revisione | Sfiducia, revisioni sempre più superficiali | Rispondere a tutto |
| Non fare rebase per giorni | Conflitti grandi e rischiosi | Rebase quotidiano |
| Non riportare gli apprendimenti | La Factory smette di migliorare | Nota nello stesso giorno |
| Lasciare `main` rosso | Blocca tutto il team | Priorità assoluta |

---

## Checklist

**Prima del commit**
- [ ] `composer qa` verde.
- [ ] `git add -p` eseguito, niente `dd()` o codice di debug.
- [ ] Messaggio di commit conforme.

**Prima della PR**
- [ ] Test anche su MySQL.
- [ ] Migration verificate in rollback.
- [ ] Test di isolamento tenant verdi.
- [ ] Descrizione con «cosa», «perché», «come verificarlo», «rischi».
- [ ] Diff sotto le 400 righe, o divisione motivata.

**Dopo il merge**
- [ ] CI verde su `main`.
- [ ] Verifica in staging.
- [ ] Ticket aggiornato, branch cancellato.
- [ ] Apprendimenti riportati alla Factory.

---

## Riferimenti

- [Ambiente locale](02-local-environment.md) · [Il primo modulo](03-first-module.md)
- [Ciclo di vita dello sviluppo](../03-development/01-development-lifecycle.md)
- [Guida al code review](../04-quality/02-code-review-guide.md)
- [Regole Git](../../rules/git.md) · [Regole Commit](../../rules/commit.md)
- [Checklist di code review](../../checklists/code-review-checklist.md)
