# Panoramica dell'architettura

> La struttura completa di un'applicazione WidStudios, dal punto di ingresso HTTP alla
> persistenza, con il flusso di una richiesta.

---

## Indice

1. [Descrizione](#descrizione)
2. [I due contesti](#i-due-contesti)
3. [Il flusso di una richiesta](#il-flusso-di-una-richiesta)
4. [I componenti](#i-componenti)
5. [Il flusso di un'operazione asincrona](#il-flusso-di-unoperazione-asincrona)
6. [Confini architetturali](#confini-architetturali)
7. [Qualità architetturali attese](#qualità-architetturali-attese)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

Un'applicazione WidStudios è un monolite modulare multitenant: un solo deploy, un solo codice,
molti clienti, moduli indipendenti.

La scelta del monolite non è conservatorismo: con i volumi e i team di cui disponiamo, i costi
operativi dei microservizi (rete, coerenza distribuita, osservabilità, deploy multipli) superano
i benefici. La modularità che serve si ottiene **dentro** il monolite, con confini applicati da
test invece che dalla rete.

---

## I due contesti

Ogni richiesta appartiene a uno dei due contesti, e la distinzione governa tutto il resto.

| | Landlord | Tenant |
|---|---|---|
| Chi accede | personale WidStudios, visitatori | utenti del cliente |
| Domini | `gestionale.it`, `www.gestionale.it` | `acme.gestionale.it`, `portale.acme.it` |
| Database | uno, condiviso | uno per cliente |
| Guardia | `landlord` | `tenant` |
| Contenuto | tenant, piani, licenze, contenuti pubblici | dati di dominio |
| Interfacce | landing, CMS, Super Admin | Tenant Admin, portale, API |

Un'operazione non attraversa mai i due contesti nella stessa richiesta. Ciò che deve passare da
uno all'altro (metriche, stato di attivazione) viaggia in modo asincrono, tramite job o aggregati.

---

## Il flusso di una richiesta

```
1. HTTP ──▶ Nginx ──▶ PHP-FPM
                        │
2.                      ▼
              ┌──────────────────────┐
              │  Middleware globali  │  sicurezza, sessione, CSRF
              └──────────┬───────────┘
                         ▼
              ┌──────────────────────┐
3.            │  Risoluzione tenant  │  dominio → tenant, oppure contesto landlord
              └──────────┬───────────┘
                         ▼
              ┌──────────────────────┐
4.            │  Bootstrap servizi   │  database, cache, storage, code
              └──────────┬───────────┘
                         ▼
              ┌──────────────────────┐
5.            │  Autenticazione      │  guardia del contesto
              └──────────┬───────────┘
                         ▼
              ┌──────────────────────┐
6.            │  Rotta → Controller  │  o Filament / Livewire
              └──────────┬───────────┘
                         ▼
              ┌──────────────────────┐
7.            │  Form Request        │  validazione della forma
              └──────────┬───────────┘
                         ▼
              ┌──────────────────────┐
8.            │  Policy              │  autorizzazione, deny by default
              └──────────┬───────────┘
                         ▼
              ┌──────────────────────┐
9.            │  Action              │  regole di dominio, transazione
              └──────────┬───────────┘
                         ▼
              ┌──────────────────────┐
10.           │  Repository → DB     │  persistenza sul database del tenant
              └──────────┬───────────┘
                         ▼
              ┌──────────────────────┐
11.           │  Evento di dominio   │  dopo il commit
              └──────────┬───────────┘
                         ▼
              ┌──────────────────────┐
12.           │  Resource → risposta │  serializzazione
              └──────────────────────┘
                         │
                         ▼
              ┌──────────────────────┐
13.           │  Listener in coda    │  audit, notifiche, aggregati
              └──────────────────────┘
```

I passi 3 e 4 sono ciò che distingue questa architettura da un'applicazione Laravel ordinaria:
tutto ciò che avviene dopo lavora **dentro** il contesto del tenant, senza saperlo.

---

## I componenti

| Componente | Ruolo | Note operative |
|---|---|---|
| Nginx | terminazione TLS, file statici, instradamento | header di sicurezza |
| PHP-FPM | esecuzione applicativa | OPcache e JIT attivi in produzione |
| MySQL landlord | dati di piattaforma | replica di lettura in produzione |
| MySQL tenant (N) | dati dei clienti | un database per tenant |
| Redis | cache, code, sessioni, lock | database logici separati |
| Worker | esecuzione dei job | stessa immagine dell'applicazione |
| Scheduler | attività ricorrenti | un solo processo, con lock |
| Storage | file | disco per tenant |
| Horizon | supervisione delle code | pannello nel Super Admin |
| Pulse | metriche applicative | per tenant e aggregate |

---

## Il flusso di un'operazione asincrona

```
Action ──▶ evento (dopo il commit)
              │
              ▼
        Listener che implementa ShouldQueue
              │
              ▼
        Job accodato ── porta con sé il TENANT
              │
              ▼
        Worker preleva il job
              │
              ▼
        Ripristino del contesto tenant  ← passo critico
              │
              ▼
        Esecuzione nel database corretto
              │
              ├── successo ──▶ fine
              └── errore ──▶ ritenta (backoff) ──▶ coda dei falliti
```

Il ripristino del contesto è il punto in cui si concentrano i difetti più gravi: un job che gira
senza contesto scrive nel database sbagliato o fallisce in modo apparentemente casuale. La
Foundation lo garantisce tramite un trait obbligatorio per tutti i job.

---

## Confini architetturali

Quattro confini, ciascuno con la propria verifica automatica.

| Confine | Regola | Verifica |
|---|---|---|
| **Contesto** | landlord e tenant non si mescolano in una richiesta | test di isolamento |
| **Livello** | le dipendenze vanno verso il dominio, mai al contrario | test di architettura |
| **Modulo** | i moduli comunicano per eventi e contratti | test di architettura |
| **Pubblico/privato** | ciò che è `@internal` non è usato fuori | analisi statica |

Un confine senza verifica automatica è un confine che si erode in pochi mesi: la disciplina umana
non regge alla pressione delle scadenze.

---

## Qualità architetturali attese

| Qualità | Come si ottiene | Come si verifica |
|---|---|---|
| Isolamento dei dati | database separati | test di isolamento per entità |
| Testabilità | dominio senza framework | test unitari senza database |
| Sostituibilità | contratti nel dominio | test di architettura sulle dipendenze |
| Estendibilità | moduli indipendenti | disinstallazione di un modulo |
| Tracciabilità | audit e activity log | test di scrittura dell'audit |
| Ripristinabilità | backup per tenant | prova di ripristino settimanale |
| Osservabilità | metriche e log con contesto | presenza di `tenant` nei log |
| Prestazioni | cache, indici, code | test sul numero di query |

---

## Esempi

### Esempio 1 — richiesta completa

Un operatore di `acme` registra uno scarico dal portale.

```
POST https://acme.gestionale.it/api/v1/batches/42/movements
  → risoluzione: dominio `acme.gestionale.it` → tenant `acme`
  → bootstrap: connessione `tenant_acme`, cache con prefisso `acme:`, disco `acme/`
  → guardia `tenant`, utente autenticato
  → StoreMovementRequest: forma valida
  → MovementPolicy::register: permesso presente, lotto non scaduto
  → RegisterMovementAction: disponibilità sufficiente, transazione, riga inserita
  → MovementRegistered emesso dopo il commit
  → MovementResource: 201 Created
  → in coda: ricalcolo della giacenza, scrittura dell'audit
```

### Esempio 2 — attraversamento di contesto fatto bene

Il Super Admin deve vedere il numero di movimenti per tenant.

Soluzione **non** ammissibile: iterare sui database dei tenant dal pannello di piattaforma.

Soluzione corretta: un job schedulato gira **nel contesto di ciascun tenant**, calcola l'aggregato
e scrive una riga in `tenant_metrics` nel landlord. Il pannello legge solo il landlord.

---

## Best practice

- Tenere separati i due contesti in modo rigoroso, anche quando unirli sembra più semplice.
- Far viaggiare in modo asincrono ciò che deve attraversare i contesti.
- Verificare ogni confine con un test.
- Usare sempre il trait della Foundation per i job.
- Emettere gli eventi dopo il commit, non dentro la transazione.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Query cross-tenant dal pannello di piattaforma | Violazione dell'isolamento | Aggregati asincroni nel landlord |
| Job senza contesto tenant | Scritture nel database sbagliato | Trait della Foundation |
| Evento emesso dentro la transazione | Listener che non trova i dati | Emissione dopo il commit |
| Moduli che si conoscono direttamente | Impossibile disinstallarli | Eventi e contratti |
| Confini non verificati | Erosione strutturale | Test di architettura |

---

## Checklist

- [ ] So distinguere il contesto landlord da quello tenant.
- [ ] Nessuna operazione attraversa i contesti in modo sincrono.
- [ ] Tutti i job usano il trait di contesto della Foundation.
- [ ] Gli eventi sono emessi dopo il commit.
- [ ] I quattro confini architetturali hanno una verifica automatica.

---

## Riferimenti

- [Livelli](02-layers.md) · [Multitenancy](03-multitenancy-overview.md)
- [Risoluzione del tenant](06-tenant-resolution.md) · [Code e scheduler](18-queue-scheduler.md)
- [Eventi](21-events-and-messaging.md)
- [Struttura di progetto](../docs/02-conventions/02-project-layout.md)
