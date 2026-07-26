# Deployment

> Come il codice raggiunge le persone: immagini, pipeline, ambienti, procedure di esercizio.

---

## Indice

1. [Descrizione](#descrizione)
2. [Le quattro aree](#le-quattro-aree)
3. [Il percorso di un rilascio](#il-percorso-di-un-rilascio)
4. [Che cosa rende diverso un rilascio multitenant](#che-cosa-rende-diverso-un-rilascio-multitenant)
5. [Che cosa non è automatizzato, e perché](#che-cosa-non-è-automatizzato-e-perché)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Il deployment è il punto in cui tutto il lavoro precedente diventa reale o resta un'ipotesi. È anche
l'unica fase in cui un errore ha conseguenze immediate su persone che non fanno parte del progetto.

Questa cartella contiene ciò che serve a rendere quel passaggio **ripetibile**: le immagini, la
pipeline, la descrizione degli ambienti e le procedure da seguire quando qualcosa va storto.

Non contiene il **momento** in cui rilasciare, né la decisione di rilasciare: quelle restano di una
persona, e il perché è nella sezione [Che cosa non è automatizzato](#che-cosa-non-è-automatizzato-e-perché).

---

## Le quattro aree

| Cartella | Contenuto |
|---|---|
| [`docker/`](docker/README.md) | immagine dell'applicazione, servizi, configurazioni di PHP e del server web |
| [`ci/`](ci/README.md) | pipeline: gate di qualità, costruzione dell'immagine, pubblicazione |
| [`environments/`](environments/README.md) | locale, staging, produzione: differenze e configurazione |
| [`runbooks/`](runbooks/README.md) | procedure operative: incidenti, ripristino, migrazioni, sospensioni |

---

## Il percorso di un rilascio

```
commit su main
      │
      ▼
pipeline ──┬─ stile            Pint
           ├─ analisi statica  PHPStan livello 8
           ├─ test             SQLite e MySQL, ordine casuale
           ├─ isolamento       tests/Tenant + provisioning di un tenant nuovo
           ├─ sicurezza        audit, segreti, policy
           └─ documentazione   link, sezioni, segnaposto
      │
      ▼  (tutti verdi)
costruzione dell'immagine, etichettata con il commit
      │
      ▼
pubblicazione nel registro
      │
      ▼  ← DECISIONE UMANA
staging: rilascio integrale, incluso il piano di ritorno
      │
      ▼  ← DECISIONE UMANA
produzione, secondo la sequenza di deployment/runbooks/
      │
      ▼
verifica successiva al rilascio
```

Le due decisioni umane non sono un residuo di un'automazione incompleta: sono la parte del processo
che richiede informazioni che la pipeline non ha.

---

## Che cosa rende diverso un rilascio multitenant

Con un database per tenant, tre cose cambiano rispetto a un rilascio ordinario.

**Le migration girano N volte.** Una migration che dura tre secondi su un database di prova ne dura
quaranta su quello del cliente più grande, e il totale su trentotto tenant non è la somma che ci si
aspetta. La durata si **misura** su un tenant reale prima del rilascio, non si stima.

**Il provisioning è parte del rilascio.** Uno schema nuovo deve funzionare sia sull'aggiornamento di
un database esistente, sia sulla creazione di uno vuoto. Una migration che dipende dai dati presenti
funziona su tutti i clienti attuali e rende impossibile attivarne di nuovi — difetto che si scopre
al primo contratto firmato dopo il rilascio.

**Un fallimento parziale è lo stato peggiore.** Un rilascio interrotto a metà lascia una parte dei
clienti aggiornata e una parte no, e non è evidente quale. Per questo i comandi su N tenant
raccolgono i fallimenti e proseguono, invece di fermarsi al primo.

---

## Che cosa non è automatizzato, e perché

La pipeline arriva fino alla pubblicazione dell'immagine e si ferma. Il rilascio in produzione è
manuale, ed è una decisione, non un limite tecnico.

| Non automatizzato | Perché |
|---|---|
| Il rilascio in produzione | richiede di sapere se c'è qualcuno che può intervenire, se il cliente è in un momento critico, se ci sono altri rilasci in corso |
| L'esecuzione delle migration lunghe | la finestra dipende dall'attività reale dei clienti, che la pipeline non conosce |
| La decisione di tornare indietro | il criterio è dichiarato prima; la valutazione richiede di guardare cosa sta succedendo |
| L'archiviazione di un tenant | è irreversibile su dati di un cliente |
| La cancellazione definitiva | irreversibile, e non ha un percorso automatico per scelta |

Un rilascio completamente automatico è possibile e sarebbe più comodo. Sarebbe anche il modo di
applicare a trentotto clienti, in due minuti, una decisione che nessuno ha preso.

---

## Esempi

### Verifica preventiva della durata

```bash
# Su staging, con dati simili a produzione
php artisan tenants:migrate --tenant=acme --pretend
php artisan tenants:migrate --tenant=acme --force

# Il comando riporta la durata per tenant e i cinque più lenti:
#   Migrati 38 tenant in 161.40 s (media 4.25 s, massimo 41.20 s).
#   I cinque tenant più lenti:
#     globex                          41.20 s
#     …
```

Il massimo conta più della media: è il tenant che decide la durata della finestra.

### Etichettatura dell'immagine

```bash
# ✗ Durante un incidente non si sa quale codice sta girando
docker build -t magazzino:latest .

# ✓
docker build -t magazzino:$(git rev-parse --short HEAD) .
```

---

## Best practice

- Scrivere il piano di ritorno **prima** del piano di rilascio, e provarlo su staging.
- Misurare la durata delle migration su un tenant reale, mai stimarla.
- Preferire migration di sola espansione: rendono il ritorno una questione di solo codice.
- Etichettare le immagini con il commit, mai con `latest`.
- Rilasciare in una fascia oraria in cui c'è qualcuno che può intervenire.
- Non rilasciare due modifiche rischiose insieme: se qualcosa va storto, non si sa quale.
- Annotare ogni rilascio: la storia dei rilasci è la prima cosa che si consulta durante un incidente.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Nessun piano di ritorno | Si improvvisa durante un disservizio | Scritto e provato prima |
| Durata delle migration stimata | Disservizio molto più lungo del previsto | Misura su volumi reali |
| Migration di contrazione nello stesso rilascio | Il ritorno del codice rompe l'applicazione | Rilascio successivo |
| `queue:restart` dimenticato | I worker eseguono il codice vecchio | Nella sequenza |
| Scheduler attivo durante il rilascio | Comandi su uno schema a metà migrazione | Sospeso e riattivato |
| Immagine diversa per i worker | Difetti non riproducibili | Stessa immagine |
| Tag `latest` | Non si sa quale codice gira | Tag con il commit |
| Provisioning non provato | I nuovi clienti non si attivano più | Prova in pipeline |
| Rilascio automatico fino in produzione | Una decisione che nessuno ha preso, su tutti i clienti | Passaggio manuale |
| Nessuna verifica dopo il rilascio | Il difetto lo segnala il cliente | Verifica strutturata |

---

## Checklist

- [ ] Tutti i gate della pipeline sono verdi sul commit che si rilascia.
- [ ] La durata delle migration è stata misurata su un tenant reale.
- [ ] Il provisioning di un tenant nuovo funziona con questo schema.
- [ ] Backup verificati con una prova di ripristino.
- [ ] Il piano di ritorno è scritto, provato e con criterio di attivazione dichiarato.
- [ ] L'immagine è etichettata con il commit.
- [ ] Il rilascio è stato provato integralmente su staging.
- [ ] La verifica successiva al rilascio è stata eseguita e annotata.

---

## Riferimenti

- [Docker](docker/README.md) · [Pipeline](ci/README.md) · [Ambienti](environments/README.md) · [Runbook](runbooks/README.md)
- [Regole di deployment](../rules/deployment.md) · [Queue](../rules/queue.md)
- [Workflow di rilascio](../workflows/22-release-workflow.md) · [Hotfix](../workflows/21-hotfix-workflow.md)
- [Checklist di rilascio](../checklists/release-checklist.md) · [Checklist di esercizio](../checklists/operations-checklist.md)
- [Gestione dei rilasci](../docs/05-operations/02-release-management.md)
