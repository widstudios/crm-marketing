# Struttura di un progetto generato

> Il layout standard di ogni applicazione prodotta dalla Factory: cosa contiene ogni cartella e
> quale regola determina la collocazione di una classe.

---

## Indice

1. [Descrizione](#descrizione)
2. [Albero completo](#albero-completo)
3. [Il livello applicativo](#il-livello-applicativo)
4. [Moduli](#moduli)
5. [Database](#database)
6. [Rotte](#rotte)
7. [Configurazione](#configurazione)
8. [Test](#test)
9. [Dove va una classe](#dove-va-una-classe)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

La struttura è **identica in tutti i progetti**. Questa uniformità è ciò che permette a una
persona di orientarsi in mezz'ora su un progetto che non ha mai visto, e a un agente di sapere
dove cercare senza esplorare.

Il layout si discosta da quello predefinito di Laravel in un punto sostanziale: `app/` è
organizzata per **livello architetturale**, non per tipo tecnico. Il motivo è che la
collocazione per tipo (`Models/`, `Services/`, `Helpers/`) non dice nulla sulle dipendenze
ammesse, mentre la collocazione per livello le rende evidenti.

---

## Albero completo

```
progetto/
├── app/
│   ├── Domain/                 logica di business, senza framework
│   ├── Application/            orchestrazione: Action, DTO, Query, Service
│   ├── Infrastructure/         implementazioni tecniche
│   ├── Http/                   punto di ingresso HTTP
│   ├── Filament/               pannelli amministrativi
│   ├── Console/                comandi Artisan
│   ├── Jobs/                   job in coda
│   ├── Listeners/              reazioni agli eventi
│   ├── Policies/               autorizzazione
│   ├── Providers/              service provider
│   └── Models/                 model Eloquent (dettaglio di persistenza)
├── bootstrap/
├── config/
│   ├── tenancy.php             configurazione multitenancy
│   ├── modules.php             moduli attivi
│   └── …                       configurazione standard Laravel
├── database/
│   ├── migrations/
│   │   ├── landlord/           schema di piattaforma
│   │   └── tenant/             schema replicato su ogni tenant
│   ├── factories/
│   └── seeders/
│       ├── System/             dati obbligatori (ruoli, permessi, stati)
│       └── Demo/               dati di prova, mai in produzione
├── modules/                    moduli di dominio del progetto
├── public/
├── resources/
│   ├── views/
│   ├── js/
│   ├── css/
│   └── lang/{it,en}/
├── routes/
│   ├── web.php                 rotte pubbliche (landlord)
│   ├── tenant.php              rotte del contesto tenant
│   ├── api.php                 API pubbliche
│   ├── api-tenant.php          API nel contesto tenant
│   └── console.php
├── storage/
├── tests/
│   ├── Unit/
│   ├── Feature/
│   ├── Architecture/
│   └── Tenant/                 test di isolamento
├── docker/
├── docs/
│   ├── project-brief.md
│   ├── decisions/              ADR di progetto
│   └── manual/                 manuale utente
├── CLAUDE.md
├── README.md
├── composer.json
└── phpunit.xml
```

---

## Il livello applicativo

### `app/Domain/`

La logica di business, **senza alcuna dipendenza dal framework**.

```
Domain/
└── <BoundedContext>/
    ├── Models/                 entità (possono estendere Model, vedi nota)
    ├── Enums/                  stati e classificazioni con le loro transizioni
    ├── ValueObjects/           oggetti auto-validanti
    ├── Events/                 fatti accaduti, al passato
    ├── Exceptions/             eccezioni di dominio
    └── Contracts/              interfacce implementate dall'infrastruttura
```

**Nota pragmatica.** La Factory ammette due gradi di purezza:

| Grado | Quando | Entità |
|---|---|---|
| **Puro** | dominio con logica ricca, invarianti, macchine a stati | classi PHP pure, mappate da un repository |
| **Pragmatico** | CRUD con poche regole | model Eloquent con la logica di dominio nei metodi |

In entrambi i casi restano vincolanti: enum per gli stati, value object per i dati validati,
contratti nel dominio. Il grado si sceglie per bounded context, non per classe, e si dichiara
nella documentazione del modulo.

### `app/Application/`

Orchestra il dominio. Conosce il dominio, non conosce HTTP.

```
Application/
└── <BoundedContext>/
    ├── Actions/                una mutazione di stato ciascuna
    ├── Queries/                letture complesse, ritornano proiezioni
    ├── Data/                   DTO immutabili
    └── Services/               coordinamento di più Action
```

### `app/Infrastructure/`

Le implementazioni tecniche dei contratti del dominio.

```
Infrastructure/
├── Repositories/               implementazioni Eloquent
├── External/                   client di servizi esterni
├── Storage/                    filesystem, generazione documenti
└── Notifications/              canali di notifica
```

### `app/Http/`, `app/Filament/`, `app/Console/`

I punti di ingresso. Traducono un protocollo (HTTP, interfaccia, riga di comando) in
un'invocazione applicativa. **Nessuna logica di business.**

### Direzione delle dipendenze

```
Http ─┐
Filament ─┼──▶ Application ──▶ Domain ◀── Infrastructure
Console ─┘                        ▲              │
                                  └──────────────┘
                              (implementa i contratti)
```

Regola verificabile: `Domain/` non importa da `Application/`, `Infrastructure/`, `Http/`,
`Filament/` né da `Illuminate/`. È il test di architettura più importante del progetto.

---

## Moduli

I moduli di **dominio** del progetto vivono in `modules/`; i moduli di **catalogo** arrivano come
dipendenze Composer.

```
modules/<nome>/
├── module.json
├── README.md
├── database/{migrations,factories,seeders}/
├── src/{Domain,Application,Infrastructure,Http,Filament,Policies,Providers}/
├── resources/{views,lang}/
├── routes/
├── tests/
└── docs/
```

Un modulo replica al proprio interno la stessa stratificazione dell'applicazione: chi sa muoversi
in `app/` sa muoversi in un modulo.

---

## Database

La separazione tra migration landlord e tenant è **strutturale**, non una convenzione di nome.

| Cartella | Contenuto | Comando |
|---|---|---|
| `migrations/landlord/` | tenant, domini, piani, utenti di piattaforma, metriche | `php artisan migrate --database=landlord` |
| `migrations/tenant/` | tutte le entità di dominio | `php artisan tenants:migrate` |

I seeder si dividono per natura:

| Cartella | Contenuto | In produzione |
|---|---|---|
| `seeders/System/` | ruoli, permessi, stati, tipi: dati senza cui l'applicazione non funziona | **sì** |
| `seeders/Demo/` | dati di esempio | **mai** |

Confondere le due categorie è l'errore che produce dati di prova in produzione, oppure
un'applicazione appena installata che non funziona perché mancano i ruoli.

---

## Rotte

| File | Contesto | Middleware caratteristico |
|---|---|---|
| `web.php` | landlord | `web` |
| `tenant.php` | tenant | `web`, `tenant` |
| `api.php` | landlord | `api`, `auth:sanctum` |
| `api-tenant.php` | tenant | `api`, `auth:sanctum`, `tenant` |

Il middleware `tenant` risolve il contesto e riconfigura database, cache e storage. Una rotta che
tocca dati di dominio **senza** questo middleware è un difetto di sicurezza, non una svista.

---

## Configurazione

| File | Contenuto |
|---|---|
| `config/tenancy.php` | connessioni, risoluzione, bootstrapper, provisioning |
| `config/modules.php` | moduli attivi e loro ordine di caricamento |
| `config/permission.php` | ruoli e permessi |
| `config/audit.php` | entità sottoposte ad audit, conservazione |

Regola assoluta: `env()` si usa **solo** dentro `config/`. Altrove ritorna `null` quando la
configurazione è in cache, cioè in produzione.

---

## Test

| Cartella | Natura | Database |
|---|---|---|
| `tests/Unit/` | classe in isolamento | nessuno |
| `tests/Feature/` | comportamento end-to-end | SQLite in memoria |
| `tests/Architecture/` | vincoli strutturali | nessuno |
| `tests/Tenant/` | isolamento tra tenant | almeno due tenant |

`tests/Tenant/` è una cartella a sé perché quei test hanno un requisito diverso: devono creare
più tenant reali, e non possono usare le scorciatoie degli altri.

---

## Dove va una classe

| La classe… | Va in | Verifica |
|---|---|---|
| rappresenta un concetto di business con identità | `Domain/<Context>/Models/` | ha senso senza database? |
| è un insieme chiuso di valori con regole | `Domain/<Context>/Enums/` | le transizioni sono nell'enum? |
| è un dato validato senza identità | `Domain/<Context>/ValueObjects/` | è immutabile? |
| descrive un fatto accaduto | `Domain/<Context>/Events/` | il nome è al passato? |
| modifica lo stato | `Application/<Context>/Actions/` | è `final` con un solo metodo pubblico? |
| esegue una lettura complessa | `Application/<Context>/Queries/` | ritorna una proiezione? |
| trasporta dati tra livelli | `Application/<Context>/Data/` | è `readonly`? |
| coordina più Action | `Application/<Context>/Services/` | non contiene logica di dominio? |
| accede alla persistenza | `Infrastructure/Repositories/` | implementa un contratto del dominio? |
| parla con un servizio esterno | `Infrastructure/External/` | è dietro un'interfaccia? |
| traduce HTTP | `Http/Controllers/` | il corpo è sotto le 10 righe? |
| decide un permesso | `Policies/` | nega per default? |
| viene eseguita in differita | `Jobs/` | ripristina il contesto tenant? |

---

## Esempi

### Esempio 1 — una feature completa e la sua collocazione

Requisito: «archiviare un fornitore».

| Artefatto | Percorso |
|---|---|
| Transizione di stato ammessa | `app/Domain/Suppliers/Enums/SupplierStatus.php` |
| Evento | `app/Domain/Suppliers/Events/SupplierArchived.php` |
| Operazione | `app/Application/Suppliers/Actions/ArchiveSupplierAction.php` |
| Permesso e stato | `app/Policies/SupplierPolicy.php` |
| Ingresso HTTP | `app/Http/Controllers/Api/SupplierController.php` |
| Ingresso amministrativo | `app/Filament/Resources/SupplierResource.php` |
| Test | `tests/Feature/Suppliers/ArchiveSupplierTest.php` |

### Esempio 2 — collocazione sbagliata e sua correzione

Una classe `app/Services/SupplierService.php` con dodici metodi pubblici: creazione,
aggiornamento, archiviazione, esportazione, calcoli.

Correzione: quattro Action distinte in `Application/Suppliers/Actions/`, una Query per
l'esportazione, e i calcoli nel dominio. Il «Service» tuttofare sparisce.

---

## Best practice

- Creare la struttura completa all'inizio, anche se alcune cartelle restano vuote: la forma
  suggerisce la collocazione corretta.
- Nominare i bounded context con il linguaggio del dominio, non con termini tecnici.
- Tenere `app/Models/` per i soli model Eloquent: sono persistenza, non dominio.
- Verificare la direzione delle dipendenze con i test di architettura, non con la disciplina.
- Replicare la stessa stratificazione dentro i moduli.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Cartella `Helpers/` o `Utils/` | Diventa la discarica del progetto | Namespace che dichiara la responsabilità |
| Logica di dominio nei model Eloquent (in contesto puro) | Impossibile testare senza database | Entità di dominio + repository |
| Migration tenant in `migrations/` | Eseguita sul landlord, schema errato | Sottocartella corretta |
| Dati di prova in `seeders/System/` | Dati finti in produzione | Separazione System/Demo |
| Rotta di dominio senza middleware `tenant` | Accesso fuori contesto, rischio di leak | Middleware obbligatorio |
| `env()` fuori da `config/` | `null` in produzione con config in cache | Solo `config()` |
| Bounded context inventati per comodità tecnica | Confini che non corrispondono al dominio | Nomi dal linguaggio del dominio |

---

## Checklist

- [ ] `app/` è organizzata per livello, non per tipo tecnico.
- [ ] `Domain/` non dipende da `Illuminate/` (test di architettura verde).
- [ ] Migration divise tra `landlord/` e `tenant/`.
- [ ] Seeder divisi tra `System/` e `Demo/`.
- [ ] Rotte tenant con middleware `tenant`.
- [ ] `env()` usato solo in `config/`.
- [ ] `tests/Tenant/` contiene i test di isolamento.
- [ ] I moduli replicano la stessa stratificazione.
- [ ] Nessuna cartella `Helpers/`, `Utils/`, `Common/`.

---

## Riferimenti

- [Architettura: livelli](../../architecture/02-layers.md)
- [Sistema modulare](../../architecture/10-modular-system.md)
- [Regole Laravel](../../rules/laravel.md) · [Naming](../../rules/naming.md)
- [Il primo modulo](../01-getting-started/03-first-module.md)
- [Blueprint di modulo](../../modules/_blueprint/README.md)
