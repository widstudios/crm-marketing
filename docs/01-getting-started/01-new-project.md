# Avviare un nuovo progetto

> Dal nome del progetto alla prima applicazione funzionante: cosa serve prima, cosa succede
> durante, cosa si ottiene alla fine.

---

## Indice

1. [Descrizione](#descrizione)
2. [Prerequisiti](#prerequisiti)
3. [Passo 1 — Il Project Brief](#passo-1--il-project-brief)
4. [Passo 2 — Creazione del repository](#passo-2--creazione-del-repository)
5. [Passo 3 — Esecuzione di `loop crea`](#passo-3--esecuzione-di-loop-crea)
6. [Passo 4 — Le decisioni che restano umane](#passo-4--le-decisioni-che-restano-umane)
7. [Passo 5 — Verifica del risultato](#passo-5--verifica-del-risultato)
8. [Cosa si ottiene](#cosa-si-ottiene)
9. [Esecuzione manuale, senza agenti](#esecuzione-manuale-senza-agenti)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Avviare un progetto nella Factory significa **istanziare** la piattaforma su un dominio, non
costruire da zero. Il lavoro creativo si concentra sulla descrizione del dominio; tutto il resto
è esecuzione di un processo noto.

Il tempo di riferimento, a regime, è: mezza giornata per il Project Brief, poche ore di
esecuzione, uno o due giorni di affinamento del dominio. Non tre settimane di infrastruttura.

---

## Prerequisiti

Prima di iniziare devono essere veri **tutti** questi punti:

| Prerequisito | Come si verifica |
|---|---|
| Ambiente locale funzionante | [`02-local-environment.md`](02-local-environment.md) eseguito con successo |
| Accesso al registro Composer privato | `composer config repositories` mostra il repository WidStudios |
| Conoscenza dei principi | [`docs/00-introduction/04-principles.md`](../00-introduction/04-principles.md) letto |
| Dominio descrivibile | esiste almeno un interlocutore che sa rispondere sulle regole di business |
| Versione della Factory nota | `git describe --tags` nella Factory |

Se il dominio non è ancora descrivibile — non c'è nessuno che sappia dire cosa fa il software —
**non si avvia il progetto**. Nessuna automazione compensa l'assenza di requisiti.

---

## Passo 1 — Il Project Brief

Il Project Brief è l'unico input sostanziale del processo. La qualità del risultato dipende quasi
interamente dalla sua qualità.

Si compila copiando [`docs/06-reference/01-project-brief-template.md`](../06-reference/01-project-brief-template.md)
nel repository del nuovo progetto, in `docs/project-brief.md`.

Sezioni che non possono restare vuote:

| Sezione | Perché è bloccante |
|---|---|
| Dominio e scopo | Senza, l'Architect non può individuare i bounded context |
| Attori e ruoli | Determinano il modello di autorizzazione |
| Entità principali | Sono l'ossatura dello schema del database |
| Casi d'uso primari | Determinano le Action da generare |
| Vincoli normativi | Determinano audit, conservazione e cifratura |
| Volumi attesi | Determinano indici, cache e strategia delle code |
| Integrazioni esterne | Determinano i contratti di infrastruttura |
| Cosa **non** fa il software | Delimita l'ambito, evita generazione inutile |

L'ultima riga è la più trascurata e la più utile: un ambito senza confini produce un software che
prova a fare tutto male.

---

## Passo 2 — Creazione del repository

```bash
# 1. Repository vuoto del progetto
git init magazzino-sanitario && cd magazzino-sanitario

# 2. Struttura iniziale e brief
mkdir -p docs
cp ../widstudios-ai-factory/docs/06-reference/01-project-brief-template.md docs/project-brief.md

# 3. CLAUDE.md di progetto, derivato dal template
cp ../widstudios-ai-factory/templates/infrastructure/project-claude.md.stub CLAUDE.md
```

Il `CLAUDE.md` del progetto dichiara:

- la versione della Factory di riferimento;
- le eventuali deroghe locali, con motivazione e scadenza;
- le peculiarità del dominio che un agente deve conoscere.

Non ripete le regole della Factory: le eredita.

---

## Passo 3 — Esecuzione di `loop crea`

```
loop crea "Magazzino Sanitario"
```

L'orchestratore legge il Project Brief ed esegue le 14 fasi del
[master workflow](../../workflows/00-master-workflow.md). Ad ogni fase:

1. carica il prompt dell'agente responsabile;
2. gli fornisce gli artefatti prodotti dalle fasi precedenti;
3. riceve gli artefatti dichiarati in output;
4. esegue il quality gate della fase;
5. se il gate fallisce, riporta la fase in *rework* (massimo 3 tentativi, poi si ferma e chiede).

Le fasi e i loro esiti attesi:

| Fase | Agente | Output principale |
|---|---|---|
| 0 | Foundation | scheletro Laravel, Foundation installata, Docker, CI |
| 1 | Business Analyst | requisiti, user story, glossario di dominio |
| 2 | Architect | moduli, bounded context, ADR di progetto |
| 3 | Database | schema landlord e tenant, migration, seeder, factory |
| 4 | Backend | entità, Action, Service, Repository, DTO, eventi |
| 5 | Filament | pannelli Super Admin e Tenant Admin, resource, widget |
| 6 | Frontend | landing page, CMS, componenti Livewire |
| 7 | Security | policy, ruoli, permessi, hardening, test di isolamento |
| 8 | Testing | suite Pest completa, test di architettura |
| 9 | Reviewer + Claude Reviewer | rapporto di revisione, correzioni |
| 10 | Performance | indici, eager loading, cache, code |
| 11 | Refactoring | rimozione duplicazioni, debito tecnico |
| 12 | Documentation | README, ADR, manuale, documentazione API |
| 13 | Deploy | immagine Docker, pipeline, procedure di rilascio |

---

## Passo 4 — Le decisioni che restano umane

L'orchestratore si ferma e chiede quando incontra una di queste situazioni:

| Situazione | Perché non decide da solo |
|---|---|
| Ambiguità di dominio | Solo il committente sa cosa significa davvero un termine |
| Regola di business non specificata | Inventarla produrrebbe un software plausibile ma sbagliato |
| Conflitto tra requisito e regola della Factory | Serve una deroga o una ADR: decisione umana |
| Integrazione esterna non documentata | Servono credenziali e specifiche reali |
| Requisito normativo dubbio | La responsabilità è legale, non tecnica |
| Quality gate fallito tre volte | Il problema è a monte: serve una diagnosi umana |

Ogni interruzione produce una **domanda specifica**, con le opzioni possibili e le conseguenze
di ciascuna. Non «cosa faccio?», ma «la scadenza del lotto si calcola dalla produzione o dalla
consegna? Nel primo caso serve il campo X, nel secondo il campo Y».

---

## Passo 5 — Verifica del risultato

Prima di considerare avviato il progetto:

```bash
composer qa                    # lint + analisi statica + test
php artisan tenants:migrate    # migration su tutti i tenant
php artisan test --coverage    # copertura
docker compose up -d           # ambiente completo
```

Verifiche funzionali minime:

- [ ] Il pannello Super Admin risponde e permette di creare un tenant.
- [ ] Il provisioning di un tenant crea database, migration e utente amministratore.
- [ ] Il pannello Tenant Admin risponde sul dominio del tenant.
- [ ] Un utente del tenant A non vede alcun dato del tenant B (test automatico verde).
- [ ] La landing page pubblica risponde.
- [ ] La pipeline CI è verde sul primo commit.

Se una di queste verifiche fallisce, il progetto **non è avviato**: si torna alla fase competente.

---

## Cosa si ottiene

Al termine, il repository del progetto contiene:

```
progetto/
├── app/
│   ├── Domain/           entità, value object, eventi, contratti
│   ├── Application/      Action, DTO, Query, Service
│   ├── Infrastructure/   Eloquent, provider esterni, filesystem
│   ├── Http/             controller, request, resource, middleware
│   ├── Filament/         pannelli, resource, widget
│   └── Console/          comandi
├── config/               configurazione, inclusa tenancy
├── database/
│   ├── migrations/landlord/
│   ├── migrations/tenant/
│   ├── seeders/
│   └── factories/
├── modules/              moduli di dominio del progetto
├── resources/            viste, componenti Livewire, asset
├── routes/               web, api, tenant, console
├── tests/                Unit, Feature, Architecture
├── docker/               immagini e compose
├── docs/                 brief, ADR di progetto, manuale
├── CLAUDE.md
└── README.md
```

Struttura commentata: [`docs/02-conventions/02-project-layout.md`](../02-conventions/02-project-layout.md).

---

## Esecuzione manuale, senza agenti

Il processo funziona identico eseguito da persone: cambia l'esecutore, non il metodo.

Per ogni fase:

1. Aprire il file dell'agente in [`agents/`](../../agents/README.md) e leggerne responsabilità e
   workflow.
2. Eseguire le attività previste, usando i [template](../../templates/README.md).
3. Verificare il quality gate nella [checklist](../../checklists/README.md) corrispondente.
4. Passare alla fase successiva solo a gate superato.

Il vantaggio è lo stesso: nessuna decisione infrastrutturale da prendere.

---

## Esempi

### Esempio 1 — brief ben fatto (estratto)

> **Entità principali**: Articolo (codice, descrizione, unità di misura, classe di rischio),
> Lotto (articolo, numero, scadenza, quantità), Movimento (lotto, tipo, quantità, causale,
> operatore, momento), Ubicazione (magazzino, corsia, scaffale).
>
> **Vincolo normativo**: la tracciabilità dei dispositivi medici richiede di ricostruire, per
> ogni lotto, tutti i movimenti con operatore e momento, conservati per 10 anni, non modificabili.
>
> **Non fa**: fatturazione, gestione ordini a fornitore, contabilità di magazzino a valore.

Da queste righe discendono direttamente: le tabelle, l'audit log immutabile su `movimenti`, la
politica di conservazione decennale, e l'esclusione di tre moduli che altrimenti sarebbero stati
generati inutilmente.

### Esempio 2 — brief insufficiente

> «Serve un gestionale per il magazzino, come quello che abbiamo adesso ma migliore.»

Manca tutto ciò che serve. Il processo si ferma alla fase 1 con una richiesta di chiarimenti.
Avviare comunque significa produrre un software plausibile e sbagliato.

### Esempio 3 — decisione che resta umana

Fase 3, l'agente Database incontra: «i lotti scaduti si possono ancora movimentare?».
Le due risposte producono schemi e vincoli diversi. L'orchestratore si ferma, espone le opzioni
e le conseguenze, attende la risposta del committente.

---

## Best practice

- Investire sul Project Brief: è l'unico punto in cui un'ora in più cambia davvero il risultato.
- Dichiarare **cosa il software non fa**: delimita l'ambito meglio di qualsiasi elenco di requisiti.
- Eseguire il quality gate ad ogni fase, anche quando sembra superfluo: saltarlo sposta il difetto
  a valle, dove costa di più.
- Rispondere alle domande dell'orchestratore in modo specifico e scritto: la risposta diventa parte
  della documentazione del progetto.
- Tenere il `CLAUDE.md` di progetto aggiornato con le deroghe attive.
- Non iniziare a modificare a mano ciò che una fase successiva rigenererà.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Avviare senza brief completo | Software plausibile ma sbagliato | Completare le sezioni bloccanti |
| Saltare i quality gate per fretta | I difetti emergono in fase 9 o in produzione | Gate obbligatori |
| Rispondere «fai tu» alle domande di dominio | Decisioni di business prese da un agente | Rispondere in modo specifico |
| Copiare la Foundation invece di installarla | Divergenza immediata | Dipendenza Composer |
| Modificare a mano durante l'esecuzione | Conflitti con la fase successiva | Attendere il termine della fase |
| Ignorare i test di isolamento tenant | Rischio di data leak | Verifica obbligatoria al passo 5 |
| Non dichiarare cosa il software non fa | Ambito che si espande senza controllo | Sezione obbligatoria del brief |

---

## Checklist

- [ ] Prerequisiti verificati, ambiente locale funzionante.
- [ ] Project Brief compilato, nessuna sezione bloccante vuota.
- [ ] Repository creato con `CLAUDE.md` di progetto e versione della Factory dichiarata.
- [ ] `loop crea` eseguito, tutte le fasi con quality gate superato.
- [ ] Domande di dominio risposte e registrate nella documentazione del progetto.
- [ ] `composer qa` verde.
- [ ] Test di isolamento tenant verdi.
- [ ] Pannelli Super Admin e Tenant Admin funzionanti.
- [ ] Pipeline CI verde.

---

## Riferimenti

- [Project Brief](../06-reference/01-project-brief-template.md)
- [Master workflow](../../workflows/00-master-workflow.md) · [Contratto `loop crea`](../../prompts/loop-crea.md)
- [Ambiente locale](02-local-environment.md) · [Esecuzione degli agenti](04-running-the-agents.md)
- [Struttura di progetto](../02-conventions/02-project-layout.md)
- [Checklist](../../checklists/README.md)
