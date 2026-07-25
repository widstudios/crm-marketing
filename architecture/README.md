# Architettura di riferimento

> L'architettura che **ogni** software WidStudios deve avere. Non una proposta: il punto di
> partenza obbligatorio, modificabile solo tramite ADR.

---

## Indice

1. [Descrizione](#descrizione)
2. [Come si legge questa sezione](#come-si-legge-questa-sezione)
3. [Indice dei documenti](#indice-dei-documenti)
4. [Vista d'insieme](#vista-dinsieme)
5. [Le decisioni portanti](#le-decisioni-portanti)
6. [Cosa è vincolante e cosa no](#cosa-è-vincolante-e-cosa-no)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

L'architettura di riferimento risponde a una domanda sola: *come è fatto un software WidStudios?*

La risposta è identica per un magazzino sanitario, un CRM e un help desk, perché tutti condividono
la stessa struttura portante: multitenancy con database separati, separazione in livelli, sistema
modulare, autorizzazione granulare, tracciabilità.

Ciò che cambia tra un progetto e l'altro è il **dominio**, che vive nei moduli. La struttura non
cambia.

---

## Come si legge questa sezione

I documenti sono numerati per **dipendenza concettuale**: ogni documento presuppone i precedenti.
Una lettura completa richiede circa sei ore; i primi dieci documenti sono il nucleo indispensabile.

| Blocco | Documenti | Argomento |
|---|---|---|
| Fondamenta | 01-02 | visione d'insieme e livelli |
| Multitenancy | 03-07 | la decisione più impattante dell'intera architettura |
| Accesso | 08-09 | autenticazione e autorizzazione |
| Modularità | 10-11 | come si compone un'applicazione |
| Livelli | 12-15 | responsabilità e dipendenze di ciascuno |
| Interfacce | 16-17 | API e presentazione pubblica |
| Infrastruttura | 18-25 | code, storage, audit, eventi, cache, ricerca, notifiche, osservabilità |
| Decisioni | `decisions/` | le ADR, fonte di verità più alta |

---

## Indice dei documenti

### Fondamenta

| # | Documento | Contenuto |
|---|---|---|
| 01 | [Panoramica](01-overview.md) | la struttura completa in una vista |
| 02 | [Livelli](02-layers.md) | dominio, applicazione, infrastruttura, presentazione |

### Multitenancy

| # | Documento | Contenuto |
|---|---|---|
| 03 | [Multitenancy: panoramica](03-multitenancy-overview.md) | il modello, i suoi costi e i suoi benefici |
| 04 | [Database landlord](04-landlord-database.md) | cosa vive nel database di piattaforma |
| 05 | [Database tenant](05-tenant-databases.md) | cosa vive nei database dei clienti |
| 06 | [Risoluzione del tenant](06-tenant-resolution.md) | come si determina il contesto |
| 07 | [Ciclo di vita del tenant](07-tenant-lifecycle.md) | provisioning, sospensione, migrazione, dismissione |

### Accesso

| # | Documento | Contenuto |
|---|---|---|
| 08 | [Autenticazione](08-authentication.md) | guardie, sessioni, token, secondo fattore |
| 09 | [Autorizzazione, ruoli e permessi](09-authorization-roles-permissions.md) | il modello di accesso |

### Modularità

| # | Documento | Contenuto |
|---|---|---|
| 10 | [Sistema modulare](10-modular-system.md) | come si compone un'applicazione |
| 11 | [Contratto di modulo](11-module-contract.md) | cosa un modulo deve dichiarare e garantire |

### Livelli

| # | Documento | Contenuto |
|---|---|---|
| 12 | [Livello di dominio](12-domain-layer.md) | entità, value object, eventi, contratti |
| 13 | [Livello applicativo](13-application-layer.md) | Action, Query, DTO, Service |
| 14 | [Livello di infrastruttura](14-infrastructure-layer.md) | repository, integrazioni, filesystem |
| 15 | [Livello di presentazione](15-presentation-layer.md) | HTTP, Filament, console |

### Interfacce

| # | Documento | Contenuto |
|---|---|---|
| 16 | [Architettura delle API](16-api-architecture.md) | struttura, versioni, contratti |
| 17 | [CMS e landing page](17-cms-landing.md) | presenza pubblica e contenuti |

### Infrastruttura

| # | Documento | Contenuto |
|---|---|---|
| 18 | [Code e scheduler](18-queue-scheduler.md) | lavoro asincrono e ricorrente |
| 19 | [Storage e media](19-storage-media.md) | file, dischi per tenant, documenti |
| 20 | [Audit e activity log](20-audit-activity-log.md) | tracciabilità |
| 21 | [Eventi e messaggistica](21-events-and-messaging.md) | disaccoppiamento tra moduli |
| 22 | [Strategia di caching](22-caching-strategy.md) | chiavi, invalidazione, isolamento |
| 23 | [Ricerca](23-search.md) | ricerca testuale e filtri |
| 24 | [Notifiche](24-notifications.md) | canali, preferenze, recapito |
| 25 | [Osservabilità](25-observability.md) | metriche, log, tracce |

### Decisioni

| Documento | Contenuto |
|---|---|
| [ADR](decisions/README.md) | indice delle decisioni architetturali |

---

## Vista d'insieme

```
                        ┌────────────────────────────────────┐
                        │            INTERNET                │
                        └──────────────┬─────────────────────┘
                                       │
                        ┌──────────────▼─────────────────────┐
                        │        Nginx + PHP-FPM             │
                        └──────────────┬─────────────────────┘
                                       │
                        ┌──────────────▼─────────────────────┐
                        │      Risoluzione del tenant        │
                        │  dominio · sottodominio · token    │
                        └──────┬──────────────────┬──────────┘
                               │                  │
              contesto LANDLORD│                  │contesto TENANT
                               │                  │
        ┌──────────────────────▼───┐   ┌──────────▼─────────────────────┐
        │  Landing page pubblica   │   │  Tenant Admin  ·  Portale app  │
        │  CMS                     │   │  API tenant                    │
        │  Super Admin             │   │                                │
        │  API di piattaforma      │   │                                │
        └──────────────┬───────────┘   └──────────┬─────────────────────┘
                       │                          │
                       │      ┌───────────────────▼──────────────────┐
                       │      │  Presentazione → Applicazione →      │
                       │      │  Dominio ← Infrastruttura            │
                       │      └───────────────────┬──────────────────┘
                       │                          │
        ┌──────────────▼───────────┐   ┌──────────▼─────────────────────┐
        │   DATABASE LANDLORD      │   │   DATABASE TENANT (uno per     │
        │   tenant, domini, piani, │   │   cliente): tutte le entità    │
        │   utenti di piattaforma  │   │   di dominio                   │
        └──────────────────────────┘   └────────────────────────────────┘

        ┌──────────────────────────────────────────────────────────────┐
        │  Redis (cache · code · sessioni · lock)  —  chiavi per tenant│
        │  Storage (dischi separati per tenant)                        │
        │  Worker delle code  ·  Scheduler                             │
        └──────────────────────────────────────────────────────────────┘
```

---

## Le decisioni portanti

Cinque decisioni determinano tutto il resto. Sono registrate come ADR e non si ridiscutono per
progetto.

| # | Decisione | ADR | Conseguenza principale |
|---|---|---|---|
| 1 | Multitenancy con **database separati** | [0002](decisions/0002-tenant-isolation-strategy.md) | isolamento per costruzione; N migration; backup per cliente |
| 2 | Separazione in **livelli** con dominio indipendente | [0003](decisions/0003-layered-architecture.md) | logica testabile senza framework; più classi |
| 3 | **Sistema modulare** con moduli disinstallabili | [0004](decisions/0004-modular-system.md) | crescita per aggiunta; comunicazione a eventi |
| 4 | **Action** per ogni mutazione di stato | [0005](decisions/0005-action-pattern.md) | inventario esplicito delle operazioni |
| 5 | **Permessi granulari** con deny by default | [0006](decisions/0006-permission-model.md) | sicurezza per costruzione; configurazione iniziale più laboriosa |

---

## Cosa è vincolante e cosa no

| Vincolante | A discrezione del progetto |
|---|---|
| Separazione landlord/tenant | numero e nome dei bounded context |
| Database separati per tenant | grado di purezza del dominio (puro o pragmatico) |
| Direzione delle dipendenze tra livelli | organizzazione interna dei moduli di dominio |
| Action per ogni mutazione | uso di Service di coordinamento |
| Policy deny-by-default | granularità dei permessi oltre il minimo |
| Audit sulle entità sensibili | quali entità considerare sensibili (dal brief) |
| Chiavi di cache tenant-scoped | strategia di invalidazione |
| Comunicazione tra moduli a eventi | quali eventi esporre |
| Versionamento delle API | forma delle risorse |

Le deviazioni dalle colonne di sinistra richiedono una ADR di progetto, con motivazione e scadenza.

---

## Esempi

### Esempio 1 — la stessa architettura, due domini

Magazzino Sanitario e Help Desk condividono: tenancy, autenticazione, autorizzazione, audit,
code, storage, struttura a livelli, pannelli Filament, API versionate.

Differiscono in: entità di dominio, Action, regole di business, moduli specifici, report.

Circa il 70% della struttura è identico. È la ragione per cui esiste la Factory.

### Esempio 2 — una deviazione legittima

Il progetto Tracciabilità RFID riceve 5.000 letture al secondo. Le letture non stanno in MySQL:
serve un archivio time-series.

Deviazione ammissibile: si aggiunge un archivio dedicato **per quel dominio**, dietro un contratto
della Foundation, lasciando invariato tutto il resto. Serve una ADR di progetto che documenti
l'eccezione e i suoi confini.

---

## Best practice

- Leggere i primi dieci documenti prima di progettare qualsiasi cosa.
- Consultare le ADR prima di proporre una modifica: la domanda potrebbe avere già una risposta.
- Deviare solo con ADR, mai in silenzio.
- Verificare i vincoli architetturali con i test, non con la disciplina.
- Aggiornare l'architettura quando un progetto dimostra che una scelta non regge.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Progettare senza leggere le ADR | Si ridiscutono decisioni già prese | Leggere prima |
| Deviare senza ADR | Divergenza silenziosa tra progetti | ADR obbligatoria |
| Applicare l'architettura al CRUD banale | Cerimonia inutile | Grado di purezza per contesto |
| Comunicazione diretta tra moduli | Moduli non più disinstallabili | Eventi e contratti |
| Vincoli non verificati | Erosione strutturale | Test di architettura |

---

## Checklist

- [ ] Conosco le cinque decisioni portanti e le loro ADR.
- [ ] So distinguere ciò che è vincolante da ciò che è a discrezione del progetto.
- [ ] Le deviazioni del mio progetto hanno una ADR.
- [ ] I vincoli architetturali sono verificati da test.
- [ ] La struttura del progetto corrisponde al layout standard.

---

## Riferimenti

- [Panoramica](01-overview.md) · [Livelli](02-layers.md) · [Multitenancy](03-multitenancy-overview.md)
- [ADR](decisions/README.md)
- [Principi](../docs/00-introduction/04-principles.md)
- [Struttura di progetto](../docs/02-conventions/02-project-layout.md)
- [Indice delle regole](../rules/README.md)
