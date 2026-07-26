# Definition of Done

> Quando una modifica è **finita**. Non «funziona sulla mia macchina», non «manca solo la
> documentazione».

| | |
|---|---|
| **Quando** | prima di considerare conclusa qualunque modifica |
| **Chi la applica** | chiunque consegni un artefatto: persona o agente |
| **Natura** | condizione di consegna, non negoziabile |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

«Fatto» è una parola che, senza una definizione condivisa, significa cose diverse per ognuno: per
chi scrive il codice significa che compila; per chi lo rivede che è leggibile; per chi lo mette in
produzione che non si rompe; per chi lo usa che risolve il problema.

Questo documento è la definizione unica. Vale per una funzionalità nuova, per la correzione di un
difetto, per un refactoring e per una modifica alla sola documentazione — con le esclusioni
dichiarate nella sezione [Ambito ridotto](#ambito-ridotto).

Una modifica che non soddisfa tutte le voci applicabili **non è finita**. Non è «finita all'80%»:
non è finita.

---

## Verifiche

### Funzionalità

- [ ] Il comportamento richiesto è implementato, per intero.
- [ ] Ogni regola di business dichiarata è implementata dove dichiarato.
- [ ] I casi limite del dominio sono gestiti: valori nulli, quantità zero, insiemi vuoti,
      concorrenza sulle risorse contese.
- [ ] Nessuna funzionalità aggiunta oltre a quella richiesta.
- [ ] Le assunzioni fatte sono **dichiarate** nel report o nel corpo della PR.
- [ ] Le domande rimaste aperte sono elencate, non decise in silenzio.

### Codice

- [ ] `declare(strict_types=1);` in ogni file PHP nuovo.
- [ ] Tipi dichiarati su ogni parametro, ritorno e proprietà.
- [ ] Nessuna logica nei controller, nei model, nei Filament Resource, nei Livewire component.
- [ ] Ogni mutazione passa da un'Action, che riceve un DTO.
- [ ] I nomi rispettano [naming.md](../rules/naming.md) e il glossario del dominio.
- [ ] Nessun helper di debug residuo: `dd`, `dump`, `ray`, `var_dump`.
- [ ] Nessun codice commentato, nessun `TODO` senza riferimento tracciato.
- [ ] Nessuna dipendenza nuova senza giustificazione scritta.
- [ ] Nessuna duplicazione di codice già presente nella Foundation.

### Sicurezza

- [ ] Ogni punto di ingresso introdotto autorizza esplicitamente.
- [ ] Ogni model nuovo ha una Policy registrata, che nega in assenza di permesso.
- [ ] I permessi nuovi sono creati dal seeder e assegnati al ruolo amministratore.
- [ ] Nessuna query cross-tenant.
- [ ] Ogni chiave di cache introdotta è tenant-scoped.
- [ ] Ogni job introdotto usa `TenantAware`, è idempotente e dichiara `tries`, `backoff`, `timeout`.
- [ ] Nessun dato sensibile nei log introdotti.
- [ ] Nessun segreto nel diff.

### Database

- [ ] Le migration stanno nella cartella corretta e implementano `down()`, provato.
- [ ] Nessuna trasformazione di dati dentro una migration di schema.
- [ ] Ogni chiave esterna ha vincolo e indice.
- [ ] Ogni colonna usata nei filtri o negli ordinamenti dichiarati è indicizzata.
- [ ] Ogni model nuovo ha una factory che produce entità valide secondo il dominio.
- [ ] La migration è stata verificata su MySQL, non solo su SQLite.

### Test

- [ ] Ogni operazione ha i **tre** test: percorso corretto, violazione di dominio, autorizzazione
      negata.
- [ ] Ogni entità nuova ha il test di isolamento tra tenant.
- [ ] Se la modifica corregge un difetto, esiste il test che **falliva prima**.
- [ ] Copertura 100% su Action, Policy, value object ed enum di dominio introdotti.
- [ ] Nessun test è stato modificato per farlo passare invece di correggere il codice.
- [ ] La suite è verde su SQLite e su MySQL.

### Prestazioni

- [ ] Nessuna query dentro un ciclo introdotta.
- [ ] Ogni relazione usata in un elenco è caricata in anticipo.
- [ ] Nessun caricamento in memoria di insiemi non limitati.
- [ ] Se la modifica tocca un percorso principale, il numero di query è stato misurato.

### Documentazione

- [ ] La documentazione toccata dalla modifica è aggiornata **nello stesso commit**.
- [ ] Ogni decisione strutturale ha una ADR.
- [ ] Il `CHANGELOG.md` riporta la modifica, se rivolta all'utente.
- [ ] Ogni nuovo documento ha le sezioni obbligatorie ed è collegato da un indice.
- [ ] Nessun link rotto introdotto.

### Consegna

- [ ] `composer qa` verde in locale, sul commit che si consegna.
- [ ] La pipeline è verde.
- [ ] I commit seguono [commit.md](../rules/commit.md).
- [ ] Il ramo è aggiornato rispetto al ramo di destinazione.
- [ ] Il diff contiene **solo** la modifica dichiarata: nessuna riformattazione incidentale,
      nessun file di lavoro.
- [ ] Il corpo della PR spiega **perché**, non solo cosa.

---

## Ambito ridotto

Non tutte le voci si applicano a tutte le modifiche. Le esclusioni ammesse sono queste, e solo
queste:

| Tipo di modifica | Sezioni non applicabili |
|---|---|
| Sola documentazione | Database, Prestazioni, e i test funzionali |
| Sola configurazione di tooling | Database, Test funzionali, Prestazioni |
| Refactoring senza cambio di comportamento | nessuna: i test esistenti devono restare verdi **senza modifiche** |
| Correzione di difetto | nessuna: in più, il test che falliva prima è obbligatorio |

Un refactoring che richiede di modificare i test non è un refactoring: è un cambio di comportamento,
e vale la definizione completa.

Le voci non applicabili si **dichiarano**, con la motivazione. Una voce non dichiarata è una voce
non verificata.

---

## Comandi di verifica

```bash
composer qa                       # lint + analisi statica + test
composer test:coverage
composer test:arch
php artisan test --env=testing-mysql
git diff --stat main...HEAD       # il diff contiene solo la modifica dichiarata
php tooling/scripts/check-docs.php
```

---

## Esempi

### Dichiarazione conforme

```markdown
### Definition of Done
Tutte le voci applicabili soddisfatte.

Non applicabili:
- Database: la modifica non tocca lo schema.
- Prestazioni (misura del numero di query): la modifica non tocca percorsi di elenco.

`composer qa` verde. Suite verde su SQLite e MySQL. Copertura Action: 100%.
Documentazione aggiornata in `docs/domain/batches.md`, stesso commit.
```

### Dichiarazione non conforme

```markdown
### Definition of Done
Fatto. Manca solo la documentazione, la aggiungo dopo.
```

Non è una consegna. La documentazione aggiornata «dopo» non viene aggiornata: la modifica resta
nella storia e il documento resta falso.

---

## Best practice

- Verificare durante il lavoro, non alla fine: le correzioni costano meno finché il contesto è
  fresco.
- Dichiarare esplicitamente le voci non applicabili: distingue «non serve» da «non verificato».
- Aggiornare la documentazione nello stesso commit della modifica.
- Trattare «manca solo X» come «non finito»: è sempre X a produrre il difetto successivo.
- Se una voce risulta sistematicamente inapplicabile, proporne la modifica invece di ignorarla.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| «Manca solo la documentazione» | La documentazione non arriva mai | Stesso commit |
| Test modificati per farli passare | La rete di protezione si buca | Correggere il codice |
| Voci non applicabili omesse | Non si distingue da «non verificato» | Dichiararle |
| `composer qa` eseguito su un commit precedente | Verde su codice diverso da quello consegnato | Sul commit finale |
| Riformattazioni nel diff funzionale | La revisione non vede la modifica reale | Commit separato |
| Difetto corretto senza test rosso | Il difetto ritorna | Test che falliva prima |
| Assunzioni non dichiarate | Software plausibile e sbagliato | Dichiararle sempre |
| Refactoring con test modificati | Cambio di comportamento mascherato | Definizione completa |

---

## Checklist

- [ ] Ho verificato ogni voce applicabile.
- [ ] Ho dichiarato le voci non applicabili con la motivazione.
- [ ] Ho eseguito `composer qa` sul commit che consegno.
- [ ] Ho aggiornato la documentazione nello stesso commit.
- [ ] Ho dichiarato assunzioni e domande aperte.
- [ ] Il diff contiene solo la modifica dichiarata.

---

## Riferimenti

- [Indice delle checklist](README.md) · [Revisione del codice](code-review-checklist.md)
- [Protocollo agenti](../agents/00-agent-protocol.md)
- [Regole di commit](../rules/commit.md) · [Git](../rules/git.md) · [Testing](../rules/testing.md)
- [Ciclo di sviluppo](../docs/03-development/01-development-lifecycle.md)
