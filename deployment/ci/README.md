# Deployment — Pipeline

> I gate automatici: che cosa verificano, in che ordine, e perché la pipeline si ferma prima della
> produzione.

---

## Indice

1. [Descrizione](#descrizione) 2. [I gate](#i-gate) 3. [Perché in parallelo](#perché-in-parallelo)
4. [Dove si ferma la pipeline](#dove-si-ferma-la-pipeline) 5. [La pipeline della Factory](#la-pipeline-della-factory)
6. [Esempi](#esempi) 7. [Best practice](#best-practice) 8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist) 10. [Riferimenti](#riferimenti)

---

## Descrizione

Una pipeline non esiste per dire «verde». Esiste per **rifiutare** ciò che non rispetta le regole, e
per farlo in modo che nessuno debba decidere se applicarle.

Il criterio di progetto è uno solo: ogni regola automatizzabile ha una verifica, e ogni verifica
corrisponde a una regola citabile. Un controllo che non si può ricondurre a una regola scritta è un
controllo che qualcuno discuterà, e vincerà.

---

## I gate

| Gate | Verifica | Blocca |
|---|---|---|
| **Stile** | Pint in modalità di verifica | sì |
| **Analisi statica** | PHPStan livello 8, baseline non cresciuta | sì |
| **Test** | suite su SQLite **e** MySQL, in ordine casuale | sì |
| **Copertura** | 80% complessiva, 100% su Action, Policy, VO ed enum | sì |
| **Isolamento tenant** | `tests/Tenant`, più il provisioning di un tenant nuovo | sì |
| **Sicurezza** | `composer audit`, segreti, policy, autorizzazione | sì |
| **Documentazione** | link, sezioni obbligatorie, segnaposto | sì |
| **Architettura** | test di architettura, dipendenze tra moduli | sì |

Tutti bloccano. Un gate che avvisa senza bloccare viene ignorato entro un mese, e da quel momento
produce solo rumore: la sua utilità netta è negativa.

### Perché due basi dati

SQLite è veloce e permissivo; MySQL è ciò che gira in produzione. I difetti su vincoli, tipi,
collation e lunghezze massime esistono **solo** sul secondo, e su SQLite non si manifestano mai —
il che significa che senza il secondo passaggio si scoprono dopo il rilascio.

### Perché l'isolamento in un job separato

È il gate che non si può saltare, e tenerlo separato ha due conseguenze pratiche: gira anche quando
gli altri falliscono, e nel riepilogo si vede immediatamente se il problema riguarda l'isolamento
o qualcos'altro.

Include il **provisioning di un tenant nuovo**, che è la verifica meno ovvia e più preziosa:
intercetta le migration che dipendono dai dati esistenti. Quel difetto non tocca nessun cliente
attuale e rende impossibile attivarne di nuovi — si scopre al primo contratto firmato dopo il
rilascio.

### Perché la storia dei commit nel gate dei segreti

Un segreto rimosso da un file resta nei commit precedenti, e da lì è recuperabile da chiunque abbia
il repository. La rimozione dal working tree non è una correzione: è un occultamento. La correzione
è la **rotazione** della credenziale.

---

## Perché in parallelo

| Struttura | Ritorno | Conseguenza |
|---|---|---|
| Gate in sequenza | 35-40 minuti | il ritorno arriva quando si sta già facendo altro |
| Gate in parallelo | 8-12 minuti | il ritorno arriva mentre il contesto è ancora fresco |

Non è una questione di efficienza: è la differenza tra una pipeline che si legge e una che si
ignora. Sopra i venti minuti, la pipeline smette di essere uno strumento e diventa un ostacolo da
aggirare — e il modo in cui la si aggira è aprire una pull request e passare ad altro.

C'è una seconda ragione, meno evidente: con i gate in sequenza il primo fallimento nasconde gli
altri. Si corregge lo stile, si riesegue, fallisce l'analisi statica; si corregge, si riesegue,
falliscono i test. Tre cicli invece di uno.

---

## Dove si ferma la pipeline

```
gate → costruzione dell'immagine → pubblicazione → ⏹ STOP
                                                    │
                                          decisione umana
                                                    │
                                                 staging
                                                    │
                                          decisione umana
                                                    │
                                              produzione
```

La pipeline arriva alla pubblicazione dell'immagine e si ferma. Non è un'automazione incompleta: è
una decisione, motivata in [`deployment/README.md`](../README.md).

Un rilascio richiede informazioni che la pipeline non ha — se c'è qualcuno che può intervenire, se
un cliente è in un momento critico, se ci sono altri rilasci in corso. Un rilascio completamente
automatico applicherebbe a tutti i clienti, in due minuti, una decisione che nessuno ha preso.

---

## La pipeline della Factory

Questo repository non è un'applicazione, quindi i suoi gate sono diversi da quelli di un progetto
generato. Il file è [`.github/workflows/qa.yml`](../../.github/workflows/qa.yml).

| Gate | Verifica |
|---|---|
| Documentazione | `check-docs.php --all`: link, ancore, sezioni, segnaposto, orfani |
| Contratto degli agenti | `list-agents.php --check` |
| Segreti | `check-secrets.php --history` |
| Foundation | `composer qa` dentro `foundation/` |
| Sintassi | `php -l` su tutti gli script di `tooling/` |

L'ultimo esiste per una ragione specifica: gli script di tooling non sono coperti da nessuna suite,
e un errore di sintassi lì renderebbe **muto** un gate. È il modo peggiore in cui un controllo può
fallire, perché non fallisce affatto: riporta successo.

---

## Esempi

### Un gate che blocca, con il messaggio giusto

```
Sicurezza
---------
  app/Filament/Widgets/StatsWidget.php:34
      Chiave di cache scritta a mano: usare TenantCacheKey::for().

  1 violazioni su 218 elementi verificati.
```

File, riga, regola, correzione. Chi lo riceve sa cosa fare senza aprire la documentazione.

### Un gate che non serve

```
Complessità ciclomatica
-----------------------
  ⚠ 47 metodi superano la soglia consigliata (avviso, non bloccante)
```

Non blocca, quindi nessuno lo corregge; il numero cresce, e dopo tre mesi l'avviso viene letto come
parte del rumore di fondo. Se la soglia conta, il gate blocca; se non conta, il gate si toglie.

---

## Best practice

- Ogni gate blocca, oppure non esiste.
- Gate in parallelo, ritorno sotto i dieci minuti.
- Ogni controllo riconducibile a una regola citabile.
- Mettere in cache le dipendenze, non i risultati dei controlli.
- `fetch-depth: 0` sul gate dei segreti, e solo su quello.
- Rector solo in `--dry-run`: il codice lo cambia una persona, dopo aver guardato.
- Provare ogni gate nuovo su un caso **non conforme** prima di fidarsene.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Gate che avvisa senza bloccare | Ignorato entro un mese, produce rumore | Blocca o si toglie |
| Gate in sequenza | Un fallimento nasconde gli altri, tre cicli invece di uno | In parallelo |
| Test solo su SQLite | Difetti su vincoli e tipi scoperti in produzione | Anche MySQL |
| Provisioning non verificato | I nuovi clienti non si attivano dopo un rilascio | Nel gate di isolamento |
| Segreti cercati solo nel working tree | Restano nella storia, recuperabili | `--history` |
| Segreto rimosso senza rotazione | La credenziale è compromessa e ancora valida | Ruotare sempre |
| Baseline che cresce | Analisi disattivata mantenendone l'aspetto | `check-baseline.php` |
| Pipeline oltre i venti minuti | Aperta la PR, si passa ad altro | Parallelo, cache |
| Deploy automatico in produzione | Una decisione che nessuno ha preso, su tutti i clienti | Passaggio manuale |
| Gate mai provato su codice rotto | Verde perché non trova nulla, non perché non c'è | Prova su un caso non conforme |

---

## Checklist

- [ ] Ogni gate blocca.
- [ ] I gate girano in parallelo, con ritorno sotto i dieci minuti.
- [ ] La suite gira su SQLite e su MySQL, in ordine casuale.
- [ ] La copertura è verificata con la soglia doppia.
- [ ] L'isolamento tenant ha un job dedicato, con il provisioning di un tenant nuovo.
- [ ] I segreti sono cercati anche nella storia dei commit.
- [ ] La pipeline si ferma alla pubblicazione dell'immagine.
- [ ] Ogni gate è stato provato su un caso non conforme.

---

## Riferimenti

- [Deployment](../README.md) · [Docker](../docker/README.md) · [Ambienti](../environments/README.md)
- [Tooling](../../tooling/README.md) · [Analisi statica](../../docs/04-quality/03-static-analysis.md)
- [Template pipeline](../../templates/infrastructure/ci.yaml.stub)
- [Regole di testing](../../rules/testing.md) · [Deployment](../../rules/deployment.md)
- [Metriche di qualità](../../governance/quality-metrics.md)
