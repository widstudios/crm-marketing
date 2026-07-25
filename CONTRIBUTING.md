# Guida al contributo

> Come si modifica la AI Factory senza degradarla. Vale per persone e per agenti AI.

---

## Indice

1. [Descrizione](#descrizione)
2. [Principi di contribuzione](#principi-di-contribuzione)
3. [Tipi di contributo](#tipi-di-contributo)
4. [Flusso di lavoro](#flusso-di-lavoro)
5. [Standard dei documenti](#standard-dei-documenti)
6. [Standard del codice](#standard-del-codice)
7. [Quando serve una ADR](#quando-serve-una-adr)
8. [Revisione](#revisione)
9. [Esempi](#esempi)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

La Factory è un asset di lungo periodo: verrà letta e applicata per anni, da persone che non
parteciperanno a questa conversazione. Ogni contributo va valutato su tre assi:

1. **Generalità** — serve a più progetti, o solo a quello che stai facendo adesso?
2. **Verificabilità** — la regola che introduci può essere controllata da qualcuno o da una pipeline?
3. **Coerenza** — è allineata a ciò che esiste già, o crea un secondo modo di fare la stessa cosa?

Un contributo che fallisce anche uno solo di questi assi non entra.

---

## Principi di contribuzione

- **Una fonte di verità.** Se un concetto è già documentato, si linka, non si riscrive.
- **Documento piccolo, argomento intero.** Un documento tratta un argomento completo; se supera
  ~500 righe si divide in più file collegati, mai si accorcia il contenuto.
- **Prescrittivo, non descrittivo.** «Si usa X» batte «si potrebbe usare X».
- **Esempi reali.** Ogni regola ha almeno un esempio corretto e uno scorretto.
- **Nessuna rottura silenziosa.** Se sposti o rinomini un file, aggiorni tutti i riferimenti.

---

## Tipi di contributo

| Tipo | Dove | Serve ADR? | Revisione minima |
|---|---|---|---|
| Correzione redazionale | ovunque | no | 1 revisore |
| Nuovo documento | `docs/`, `rules/` | no | 1 revisore |
| Nuova regola vincolante | `rules/` | **sì** | 2 revisori |
| Nuovo agente / modifica prompt | `agents/`, `prompts/` | sì se cambia il workflow | 2 revisori |
| Nuovo template | `templates/` | no | 1 revisore + test d'uso |
| Modifica alla Foundation | `foundation/` | sì se cambia un contratto pubblico | 2 revisori + test |
| Nuovo modulo di catalogo | `modules/` | sì | 2 revisori |
| Cambio di stack o versione | ovunque | **sì** | 2 revisori + piano di migrazione |
| Modifica alla pipeline | `deployment/`, `.github/` | no | 1 revisore |

---

## Flusso di lavoro

1. **Verifica l'esistente.** Cerca nel repository prima di creare: quasi tutto ha già un posto.
2. **Apri un branch** secondo [`rules/git.md`](rules/git.md):
   `factory/<area>/<descrizione-breve>` (es. `factory/rules/aggiunta-regole-cache`).
3. **Scrivi il contributo** rispettando gli standard sotto.
4. **Aggiorna gli indici**: il `README.md` della cartella e, se rilevante, `docs/README.md`.
5. **Esegui i controlli**:
   ```bash
   php tooling/scripts/check-docs.php
   ```
6. **Aggiorna il CHANGELOG** nella sezione `Unreleased`.
7. **Commit** secondo [`rules/commit.md`](rules/commit.md).
8. **Apri la pull request** usando il template del repository.

---

## Standard dei documenti

Ogni file `.md` deve avere, nell'ordine:

| Sezione | Obbligatoria | Nota |
|---|---|---|
| Titolo `# ` | sì | uno solo per file |
| Riga di sintesi `> ` | sì | una frase, dice a chi serve il documento |
| `## Indice` | sì | link agli heading di secondo livello |
| `## Descrizione` | sì | il contesto e il problema che risolve |
| Corpo | sì | sezioni specifiche dell'argomento |
| `## Esempi` | sì | almeno un esempio concreto |
| `## Best practice` | sì | elenco prescrittivo |
| `## Errori comuni` | sì | tabella errore → conseguenza → rimedio |
| `## Checklist` | sì | caselle `- [ ]` verificabili |
| `## Riferimenti` | sì | link ad altri documenti della Factory |

Regole di forma:

- Righe di testo ≤ 110 caratteri quando possibile.
- Tabelle per confronti, elenchi per sequenze, blocchi di codice per il codice.
- Link **relativi** e verificati, mai URL assoluti verso il repository.
- Nomi file in `kebab-case`, prefisso numerico dove l'ordine conta (`03-multitenancy-overview.md`).

---

## Standard del codice

Vale tutto ciò che è scritto in [`rules/php.md`](rules/php.md) e [`rules/laravel.md`](rules/laravel.md).
In sintesi, per il codice che vive in `foundation/` e `templates/`:

- `declare(strict_types=1);` sempre.
- PSR-12 applicato da Pint, PHPStan livello 8 senza baseline nuove.
- Nessuna dipendenza da codice applicativo: la Foundation non conosce i progetti.
- Ogni classe pubblica ha un test in `foundation/tests/`.
- I template (`.stub`) usano segnaposto `{{ Namespace }}`, `{{ Class }}`, `{{ variable }}` e sono
  documentati nel README della loro cartella.

---

## Quando serve una ADR

Serve una Architecture Decision Record quando la modifica:

- cambia una regola vincolante o ne introduce una;
- cambia un contratto pubblico della Foundation;
- introduce o rimuove una dipendenza di piattaforma;
- cambia la struttura del repository;
- cambia il workflow degli agenti;
- ha alternative ragionevoli che qualcuno, tra due anni, potrebbe voler riconsiderare.

Formato e processo: [`architecture/decisions/README.md`](architecture/decisions/README.md).

---

## Revisione

Chi revisiona verifica, in quest'ordine:

1. **Ammissibilità** — il contributo appartiene alla Factory?
2. **Conflitti** — contraddice regole o ADR esistenti?
3. **Completezza** — sezioni obbligatorie, esempi, checklist.
4. **Navigabilità** — è raggiungibile dagli indici? I link funzionano?
5. **Qualità linguistica** — prescrittivo, non ambiguo, senza gergo inutile.

La checklist completa è in [`checklists/documentation-checklist.md`](checklists/documentation-checklist.md).

---

## Esempi

### Contributo ammissibile

> «Aggiungo `rules/cache.md` con la strategia di invalidazione per chiavi tenant-scoped, perché oggi
> ogni progetto la reinventa.»

Generale, verificabile (si può controllare il prefisso delle chiavi), coerente con la multitenancy.

### Contributo non ammissibile

> «Aggiungo `modules/catalog/lotti-scadenza.md` con la logica dei lotti sanitari.»

È dominio verticale: appartiene al progetto Magazzino Sanitario, non alla Factory. Se emerge che
la gestione di lotti serve a tre software, allora si valuta un modulo generico `batches`.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Creare un documento senza linkarlo | Diventa invisibile e muore | Aggiornare il `README.md` della cartella |
| Riscrivere un concetto già documentato | Due verità divergenti nel tempo | Linkare e, se serve, estendere l'originale |
| Regola senza criterio di verifica | Non applicabile, ignorata | Aggiungere il *come si controlla* |
| Esempio con pseudo-codice | Non copiabile, non testabile | Scrivere PHP valido |
| Modifica di stack senza ADR | Divergenza tra progetti | Aprire la ADR prima del codice |
| Commit «wip» o «fix» | Storia illeggibile | Conventional commit descrittivo |

---

## Checklist

- [ ] Il contributo è generale, verificabile e coerente.
- [ ] Ho cercato duplicati prima di creare nuovi file.
- [ ] Il documento ha tutte le sezioni obbligatorie.
- [ ] Ho aggiornato gli indici di cartella e, se serve, `docs/README.md`.
- [ ] Tutti i link relativi sono validi.
- [ ] Se serviva, ho scritto la ADR e l'ho collegata.
- [ ] Ho aggiornato `CHANGELOG.md`.
- [ ] Il branch e il commit rispettano le convenzioni.

---

## Riferimenti

- [CLAUDE.md](CLAUDE.md)
- [Regole Git](rules/git.md) · [Regole Commit](rules/commit.md)
- [Documentazione: standard](rules/documentation.md)
- [ADR](architecture/decisions/README.md)
- [Governance e versionamento](governance/versioning.md)
