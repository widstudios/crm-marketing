# Standard aziendali

> Le regole vincolanti della Factory. Ciò che sta scritto qui non è un consiglio: è il criterio con
> cui il codice viene accettato o respinto.

---

## Indice

1. [Descrizione](#descrizione)
2. [Come si legge una regola](#come-si-legge-una-regola)
3. [Indice delle regole](#indice-delle-regole)
4. [Livelli di vincolo](#livelli-di-vincolo)
5. [Deroghe](#deroghe)
6. [Come si aggiunge una regola](#come-si-aggiunge-una-regola)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Le regole esistono per un motivo preciso: rendere **non necessaria** la discussione su ciò che è
già stato deciso. Una revisione che discute se le Action debbano essere `final` è una revisione che
spreca il tempo di due persone su una domanda già risposta.

Ogni regola di questa cartella soddisfa tre requisiti:

1. **È prescrittiva.** Usa «deve» o «può», mai «dovrebbe».
2. **È verificabile.** Dichiara come si controlla che sia rispettata.
3. **È motivata.** Dichiara il problema che previene.

Una regola che non soddisfa i tre requisiti non appartiene a questa cartella: appartiene a `docs/`.

---

## Come si legge una regola

Ogni documento ha questa struttura:

| Sezione | Contenuto |
|---|---|
| Descrizione | il problema che le regole prevengono |
| Regole numerate | prescrizioni, ciascuna con motivo e verifica |
| Esempi | codice corretto e codice errato |
| Best practice | indicazioni non vincolanti |
| Errori comuni | errore → conseguenza → rimedio |
| Checklist | verifica operativa |

Le regole sono **numerate** (`R1`, `R2`, …) per poterle citare in revisione: «viola R4 di
`action-pattern.md`» è più utile di «non mi piace».

---

## Indice delle regole

### Linguaggio e framework

| Regola | Contenuto |
|---|---|
| [php.md](php.md) | tipizzazione, immutabilità, enum, eccezioni, stile |
| [laravel.md](laravel.md) | uso del framework, cosa è ammesso e cosa vietato |
| [filament.md](filament.md) | pannelli, resource, azioni, widget |
| [livewire.md](livewire.md) | componenti, proprietà, autorizzazione |

### Frontend e interfaccia

| Regola | Contenuto |
|---|---|
| [frontend.md](frontend.md) | struttura, asset, componenti |
| [tailwind.md](tailwind.md) | utility, riuso, tema |
| [alpine.md](alpine.md) | interattività lato client |
| [ui.md](ui.md) | componenti, stati, coerenza visiva |
| [ux.md](ux.md) | flussi, messaggi, prevenzione degli errori |
| [accessibility.md](accessibility.md) | WCAG 2.1 AA |
| [i18n.md](i18n.md) | traduzioni, formati, lingue |

### Dati

| Regola | Contenuto |
|---|---|
| [sql.md](sql.md) | schema, indici, tipi, query |
| [database.md](database.md) | migration, seeder, factory, transazioni |
| [cache.md](cache.md) | chiavi, TTL, invalidazione |

### Pattern applicativi

| Regola | Contenuto |
|---|---|
| [action-pattern.md](action-pattern.md) | mutazioni di stato |
| [repository-pattern.md](repository-pattern.md) | persistenza e letture |
| [service-layer.md](service-layer.md) | coordinamento |
| [dto.md](dto.md) | trasporto di dati |
| [events.md](events.md) | eventi e listener |
| [queue.md](queue.md) | job e code |
| [dependency-injection.md](dependency-injection.md) | contratti e binding |
| [error-handling.md](error-handling.md) | eccezioni e loro traduzione |

### Interfacce programmatiche

| Regola | Contenuto |
|---|---|
| [rest-api.md](rest-api.md) | risorse, versioni, risposte |
| [validation.md](validation.md) | input e regole di dominio |
| [middleware.md](middleware.md) | catena di elaborazione |

### Sicurezza e qualità

| Regola | Contenuto |
|---|---|
| [security.md](security.md) | superficie d'attacco e contromisure |
| [policies.md](policies.md) | autorizzazione |
| [testing.md](testing.md) | categorie, soglie, obblighi |
| [performance.md](performance.md) | query, indici, cache, code |
| [logging.md](logging.md) | livelli, contesto, dati vietati |

### Processo

| Regola | Contenuto |
|---|---|
| [naming.md](naming.md) | nomi di classi, tabelle, permessi, rotte |
| [git.md](git.md) | branch, merge, storia |
| [commit.md](commit.md) | messaggi di commit |
| [code-review.md](code-review.md) | criteri di revisione |
| [deployment.md](deployment.md) | rilascio e rollback |
| [configuration.md](configuration.md) | configurazione e ambiente |
| [documentation.md](documentation.md) | documenti obbligatori |

---

## Livelli di vincolo

| Livello | Significato | Deroga |
|---|---|---|
| **Assoluto** | non ammette eccezioni | nessuna |
| **Vincolante** | obbligatorio | deroga con ADR di progetto e scadenza |
| **Consigliato** | prassi migliore | giudizio, da motivare in revisione |

Regole di livello **assoluto** (elenco completo):

1. Nessuna query cross-tenant.
2. Nessuna colonna `tenant_id` nelle tabelle tenant.
3. Ogni chiave di cache è tenant-scoped.
4. Ogni job ripristina il contesto tenant.
5. Ogni Policy nega in assenza di permesso esplicito.
6. Nessun segreto nel repository.
7. `declare(strict_types=1);` in ogni file PHP.
8. L'audit log è immutabile.
9. Nessun dato sensibile nei log.
10. Nessun file di cliente su disco pubblico.

Queste dieci non si derogano: sono le condizioni sotto le quali il resto dell'architettura ha senso.

---

## Deroghe

Una deroga a una regola **vincolante** richiede:

```markdown
## Deroghe attive

### D-01 — Repository non usato nel modulo `geo`

**Regola derogata:** repository-pattern.md R2
**Motivo:** il modulo espone solo letture su una tabella di riferimento in sola lettura
            (comuni italiani); il repository aggiungerebbe indirezione senza beneficio.
**Approvata da:** Architecture Owner, 2026-08-10
**Scadenza:** 2027-02-10 (da rivalutare con la crescita del modulo)
```

Le deroghe vivono nel `CLAUDE.md` del progetto. Requisiti:

- regola citata con numero;
- motivo tecnico, non di comodità;
- approvazione di un Area Owner;
- **scadenza** obbligatoria.

Una deroga senza scadenza diventa lo standard di fatto e svuota la regola. Alla scadenza: rientro,
oppure nuova valutazione con motivazione aggiornata.

Se una regola viene derogata da **più progetti**, il problema è la regola: si corregge, non si
impone con più forza.

---

## Come si aggiunge una regola

1. Verificare che il contenuto non appartenga a `docs/` (è prescrittivo? verificabile? motivato?).
2. Identificare il **principio** a cui risponde
   ([dodici principi](../docs/00-introduction/04-principles.md)).
3. Definire il **criterio di verifica**: se non esiste, la regola non è ammissibile.
4. Se la regola è vincolante: scrivere una ADR.
5. Numerare la regola dentro il documento.
6. Aggiungere gli esempi corretto/errato.
7. Aggiornare questo indice.
8. Se possibile, automatizzare la verifica (test di architettura, script, PHPStan).

Obiettivo dichiarato: **≥ 50% delle regole verificate automaticamente**.

---

## Esempi

### Esempio 1 — regola ammissibile

> **R4.** Ogni classe in `Actions/` deve essere `final`.
>
> **Motivo:** l'ereditarietà tra Action produce gerarchie in cui il comportamento effettivo dipende
> dalla classe concreta e non è più deducibile dal nome.
>
> **Verifica:** test di architettura `arch('le action sono final')`.

Prescrittiva, motivata, verificabile automaticamente.

### Esempio 2 — regola non ammissibile

> Il codice deve essere pulito e ben organizzato.

Non prescrittiva (cosa significa «pulito»?), non verificabile, non motivata. Appartiene a `docs/`,
riformulata come guida.

---

## Best practice

- Citare la regola con il numero durante le revisioni.
- Verificare l'esistenza di una regola prima di discutere una scelta.
- Proporre la correzione di una regola invece di aggirarla.
- Automatizzare la verifica appena possibile.
- Tenere le regole corte: un documento di trenta regole non viene letto.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Regola senza criterio di verifica | Ignorata nel tempo | Aggiungere la verifica o spostare in `docs/` |
| Regola senza motivazione | Aggirata appena scomoda | Dichiarare il problema che previene |
| Deroga senza scadenza | Diventa lo standard di fatto | Scadenza obbligatoria |
| Discutere in revisione una regola esistente | Tempo sprecato | Citare la regola |
| Regola derogata da tutti i progetti | La regola è sbagliata | Correggerla |
| Guida scritta in `rules/` | Confusione tra obbligo e consiglio | Le guide stanno in `docs/` |

---

## Checklist

- [ ] Conosco le dieci regole di livello assoluto.
- [ ] Le deroghe del mio progetto sono scritte, approvate e con scadenza.
- [ ] Quando respingo del codice, cito la regola con il numero.
- [ ] Le regole che ho introdotto hanno motivo e criterio di verifica.
- [ ] Le verifiche automatizzabili sono in pipeline.

---

## Riferimenti

- [I dodici principi](../docs/00-introduction/04-principles.md)
- [CLAUDE.md](../CLAUDE.md) · [Guida al contributo](../CONTRIBUTING.md)
- [ADR](../architecture/decisions/README.md)
- [Checklist](../checklists/README.md)
