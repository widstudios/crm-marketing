# CLAUDE.md — Istruzioni operative per agenti AI

Questo file è il **punto di ingresso obbligatorio** per qualsiasi agente AI che opera su questo
repository o su un progetto generato da esso. Va letto prima di qualunque altra azione.

---

## Indice

1. [Identità del repository](#identità-del-repository)
2. [Cosa puoi e cosa non puoi fare qui](#cosa-puoi-e-cosa-non-puoi-fare-qui)
3. [Ordine di lettura obbligatorio](#ordine-di-lettura-obbligatorio)
4. [Gerarchia delle fonti di verità](#gerarchia-delle-fonti-di-verità)
5. [Regole non negoziabili](#regole-non-negoziabili)
6. [Convenzioni di output](#convenzioni-di-output)
7. [Come si lavora su un progetto generato](#come-si-lavora-su-un-progetto-generato)
8. [Comandi standard](#comandi-standard)
9. [Errori comuni degli agenti](#errori-comuni-degli-agenti)
10. [Checklist prima di consegnare](#checklist-prima-di-consegnare)

---

## Identità del repository

Questo repository è la **WidStudios AI Factory**: un insieme di documentazione, regole, prompt,
template e codice riutilizzabile. **Non è un'applicazione.**

Conseguenze pratiche:

- Qui **non** si sviluppa un gestionale specifico. Se ti viene chiesto di implementare un dominio
  applicativo (magazzino, CRM, ticket…), il posto giusto è un *nuovo* repository generato dalla
  Factory, non questo.
- Qui si sviluppa ciò che **serve a tutti** i progetti: standard, astrazioni, moduli generici.
- Il criterio di ammissione è: *«Questo artefatto sarebbe utile identico in almeno tre software
  diversi?»* Se la risposta è no, non appartiene alla Factory.

---

## Cosa puoi e cosa non puoi fare qui

| Puoi | Non puoi |
|---|---|
| Aggiungere documentazione approfondita | Aggiungere documentazione sintetica o segnaposto |
| Estendere la Foundation con codice generico | Inserire logica di dominio verticale |
| Creare nuovi template e moduli riutilizzabili | Duplicare un template già esistente con variazioni minime |
| Proporre modifiche allo stack tramite ADR | Cambiare lo stack senza ADR |
| Rinominare/riorganizzare seguendo le convenzioni | Rompere link esistenti senza aggiornarli |
| Modificare `legacy/` per sola documentazione | Sviluppare nuove feature dentro `legacy/` |

---

## Ordine di lettura obbligatorio

Prima di produrre qualunque artefatto:

1. `CLAUDE.md` (questo file)
2. [`agents/00-agent-protocol.md`](agents/00-agent-protocol.md) — il protocollo comune a tutti gli agenti
3. Il file del **tuo** agente in [`agents/`](agents/README.md)
4. Le regole pertinenti in [`rules/`](rules/README.md)
5. La fase corrente in [`workflows/`](workflows/README.md)
6. La checklist di uscita in [`checklists/`](checklists/README.md)

Se stai per scrivere codice PHP: [`rules/php.md`](rules/php.md), [`rules/laravel.md`](rules/laravel.md),
[`rules/naming.md`](rules/naming.md) sono prerequisiti.

---

## Gerarchia delle fonti di verità

Quando due documenti sembrano in conflitto, vince quello più in alto in questa lista:

1. **ADR accettate** (`architecture/decisions/`) — decisioni datate e motivate
2. **`rules/`** — standard vincolanti
3. **`architecture/`** — architettura di riferimento
4. **`foundation/`** — il codice, che è la specifica eseguibile
5. **`templates/`**, **`modules/`**
6. **`docs/`** — materiale esplicativo
7. **`examples/`** — illustrativo, mai normativo

Se rilevi un conflitto reale, **non scegliere in silenzio**: segnalalo e proponi una ADR.

---

## Regole non negoziabili

Queste regole non ammettono eccezioni senza ADR:

1. **Multitenancy.** Ogni entità di dominio vive in un database tenant; i dati di piattaforma
   vivono nel landlord. Mai mescolare. Vedi [`architecture/03-multitenancy-overview.md`](architecture/03-multitenancy-overview.md).
2. **Nessuna query cross-tenant.** Nemmeno «temporanea», nemmeno «per debug».
3. **Deny by default.** Ogni Policy nega salvo autorizzazione esplicita.
4. **Ogni mutazione passa da un'Action** e viene registrata quando riguarda dati sensibili.
5. **Nessuna logica nei controller, nei model, nei Filament Resource.** La logica sta in
   Action/Service/Domain.
6. **Tipizzazione stretta.** `declare(strict_types=1);` in ogni file PHP, tipi su ogni firma,
   niente `mixed` non giustificato.
7. **Test obbligatori.** Nessun artefatto di codice viene consegnato senza test corrispondente.
8. **Nessun segreto nel repository.** Nemmeno di esempio realistico: si usano placeholder evidenti.
9. **Nessuna dipendenza nuova senza giustificazione** scritta e valutazione di manutenzione.
10. **Ogni file Markdown** ha indice, descrizione, esempi, best practice, checklist, errori comuni
    e riferimenti incrociati.

---

## Convenzioni di output

### Lingua

- Documentazione, commenti concettuali e nomi di documento: **italiano**.
- Identificatori di codice (classi, metodi, variabili, tabelle, colonne, chiavi di traduzione):
  **inglese**. Vedi [`rules/naming.md`](rules/naming.md).

### File Markdown

Struttura minima obbligatoria di ogni documento:

```markdown
# Titolo

> Riga di sintesi: che cos'è questo documento e a chi serve.

## Indice
## Descrizione
## …contenuto specifico…
## Esempi
## Best practice
## Errori comuni
## Checklist
## Riferimenti
```

### Codice negli esempi

Gli esempi devono essere **eseguibili o realistici**: niente `// ...` al posto della logica
significativa, niente pseudo-codice quando è possibile scrivere PHP valido.

---

## Come si lavora su un progetto generato

Un progetto generato dalla Factory eredita:

- `CLAUDE.md` derivato da [`templates/infrastructure/project-claude.md.stub`](templates/infrastructure/project-claude.md.stub)
- la Foundation come dipendenza Composer (`widstudios/foundation`)
- le configurazioni di `tooling/`
- la pipeline di `deployment/ci/`

Su quel progetto valgono **le stesse regole**, più le regole locali del progetto, che possono solo
*restringere*, mai allentare.

---

## Comandi standard

Nella Factory (documentazione):

```bash
# verifica dei link interni e della struttura dei documenti
php tooling/scripts/check-docs.php

# elenco dei documenti privi delle sezioni obbligatorie
php tooling/scripts/check-docs.php --sections
```

In un progetto generato:

```bash
composer install
php artisan key:generate
php artisan migrate --database=landlord
php artisan tenants:migrate
composer test          # Pest
composer lint          # Pint --test
composer analyse       # PHPStan livello 8
composer qa            # lint + analyse + test
```

---

## Errori comuni degli agenti

| Errore | Perché è grave | Cosa fare invece |
|---|---|---|
| Scrivere documentazione «riassuntiva» | La Factory perde il suo valore prescrittivo | Approfondire, e se serve dividere in più file |
| Implementare dominio verticale qui | Contamina la piattaforma | Creare il progetto separato |
| Inventare nuovi pattern | Rompe la ripetibilità | Usare i pattern in `rules/` o proporre una ADR |
| Copiare codice tra progetti | Genera divergenza | Promuovere il codice nella Foundation |
| Saltare i quality gate | Il difetto arriva in produzione | Eseguire la checklist di fase |
| Lasciare link rotti | La navigazione è la struttura portante | Aggiornare tutti i riferimenti |
| Usare `env()` fuori da `config/` | Rompe la cache di configurazione | `config('...')` |
| Aggiungere `--force` a comandi distruttivi | Perdita di dati | Chiedere conferma esplicita |

---

## Checklist prima di consegnare

- [ ] Ho letto il protocollo agenti e il file del mio agente.
- [ ] L'artefatto rispetta le regole applicabili in `rules/`.
- [ ] Ogni nuovo documento ha tutte le sezioni obbligatorie.
- [ ] Ogni nuovo documento è linkato da almeno un indice (`README.md` di cartella).
- [ ] Tutti i link interni che ho scritto puntano a file esistenti.
- [ ] Non ho introdotto logica di dominio verticale nella Factory.
- [ ] Non ho duplicato contenuti già presenti: ho linkato l'originale.
- [ ] Il commit segue [`rules/commit.md`](rules/commit.md).

---

## Riferimenti

- [Protocollo agenti](agents/00-agent-protocol.md)
- [Contratto `loop crea`](prompts/loop-crea.md)
- [Indice delle regole](rules/README.md)
- [Master workflow](workflows/00-master-workflow.md)
- [Guida al contributo](CONTRIBUTING.md)
