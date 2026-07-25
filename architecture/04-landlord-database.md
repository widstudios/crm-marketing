# Database landlord

> Cosa vive nel database di piattaforma, cosa non deve viverci, e come è strutturato.

---

## Indice

1. [Descrizione](#descrizione)
2. [Criterio di appartenenza](#criterio-di-appartenenza)
3. [Schema di riferimento](#schema-di-riferimento)
4. [Tabelle principali](#tabelle-principali)
5. [Utenti di piattaforma](#utenti-di-piattaforma)
6. [Metriche e aggregati](#metriche-e-aggregati)
7. [Accesso dal codice](#accesso-dal-codice)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

Il landlord è il database che sa **quali clienti esistono** e come raggiungerli. Non contiene dati
di dominio: contiene ciò che serve alla piattaforma per funzionare.

È l'unico database che non è mai duplicato, ed è quindi il singolo punto di fallimento più
critico: se il landlord non è disponibile, nessun tenant è raggiungibile, perché non è possibile
risolvere il contesto.

---

## Criterio di appartenenza

Una domanda sola:

> **Il dato descrive *il cliente*, o è *del cliente*?**

- Descrive il cliente (ragione sociale, dominio, piano, stato) → **landlord**.
- È del cliente (i suoi fornitori, i suoi documenti, i suoi utenti operativi) → **tenant**.

| Dato | Dove | Perché |
|---|---|---|
| Anagrafica del tenant | landlord | descrive il cliente |
| Domini associati | landlord | serve prima di risolvere il tenant |
| Piano tariffario | landlord | rapporto commerciale |
| Stato (attivo, sospeso) | landlord | serve prima dell'accesso |
| Utenti Super Admin | landlord | personale WidStudios |
| Utenti del tenant | **tenant** | sono dati del cliente |
| Fornitori, articoli, movimenti | **tenant** | dati di dominio |
| Contenuti della landing page | landlord | pubblici, di piattaforma |
| Metriche aggregate | landlord | solo numeri, per il monitoraggio |
| Log di accesso di piattaforma | landlord | sicurezza della piattaforma |
| Audit log di dominio | **tenant** | riguarda i dati del cliente |

---

## Schema di riferimento

```
┌────────────────────┐         ┌────────────────────┐
│      plans         │         │  platform_users    │
├────────────────────┤         ├────────────────────┤
│ id                 │         │ id                 │
│ name               │         │ name               │
│ max_users          │         │ email              │
│ max_storage_mb     │         │ password           │
│ modules (json)     │         │ two_factor_secret  │
│ price_monthly      │         │ role               │
└─────────┬──────────┘         └────────────────────┘
          │
          │ 1:N
          ▼
┌────────────────────┐         ┌────────────────────┐
│      tenants       │────1:N──▶│  tenant_domains   │
├────────────────────┤         ├────────────────────┤
│ id                 │         │ id                 │
│ slug               │         │ tenant_id          │
│ name               │         │ domain             │
│ plan_id            │         │ is_primary         │
│ database_name      │         │ verified_at        │
│ status             │         └────────────────────┘
│ locale             │
│ timezone           │         ┌────────────────────┐
│ settings (json)    │────1:N──▶│  tenant_metrics   │
│ suspended_at       │         ├────────────────────┤
│ terminating_at     │         │ tenant_id          │
│ created_at         │         │ date               │
└─────────┬──────────┘         │ active_users       │
          │                    │ storage_bytes      │
          │ 1:N                │ operations_count   │
          ▼                    └────────────────────┘
┌────────────────────┐
│  tenant_events     │         ┌────────────────────┐
├────────────────────┤         │   cms_pages        │
│ tenant_id          │         ├────────────────────┤
│ type               │         │ slug               │
│ payload (json)     │         │ locale             │
│ occurred_at        │         │ title, content     │
└────────────────────┘         │ published_at       │
                               └────────────────────┘
```

---

## Tabelle principali

### `tenants`

| Colonna | Tipo | Nota |
|---|---|---|
| `slug` | string, unico | identificatore stabile, mai modificabile |
| `name` | string | ragione sociale |
| `plan_id` | FK | piano corrente |
| `database_name` | string, unico | nome del database dedicato |
| `status` | enum | `provisioning`, `active`, `suspended`, `migrating`, `terminating`, `terminated` |
| `locale`, `timezone` | string | preferenze di visualizzazione |
| `settings` | json | configurazione non strutturata |
| `suspended_at`, `terminating_at` | timestamp | date delle transizioni |

`slug` non è modificabile: compare nel nome del database, nei percorsi di storage e nei prefissi
di cache. Cambiarlo richiederebbe una migrazione dell'infrastruttura.

### `tenant_domains`

Un tenant può avere più domini: quello standard (`acme.gestionale.it`) e uno personalizzato
(`portale.acme.it`).

| Colonna | Nota |
|---|---|
| `domain` | unico su tutta la piattaforma |
| `is_primary` | uno solo per tenant, usato per gli URL generati |
| `verified_at` | per i domini personalizzati, dopo verifica DNS |

L'unicità globale del dominio è ciò che rende la risoluzione deterministica.

### `plans`

Definisce limiti e moduli abilitati. I limiti si verificano **nelle Action**, non solo
nell'interfaccia.

### `tenant_events`

Registro immutabile degli eventi di ciclo di vita: creazione, sospensione, migrazione, dismissione.
Serve a ricostruire la storia di un cliente, anche dopo la cancellazione dei suoi dati.

---

## Utenti di piattaforma

Gli utenti del landlord sono **solo** personale WidStudios: nessun utente di un cliente esiste qui.

| Aspetto | Regola |
|---|---|
| Guardia | `landlord`, separata da `tenant` |
| Secondo fattore | obbligatorio |
| Accesso ai tenant | tramite procedura tracciata, mai diretto |
| Ruoli | `super_admin`, `support`, `readonly` |
| Log | ogni accesso a un tenant è registrato con motivazione |

L'accesso di un operatore di assistenza ai dati di un cliente è un'operazione **eccezionale e
tracciata**: comparirà nell'audit del tenant, ed è giusto che il cliente possa vederlo.

---

## Metriche e aggregati

`tenant_metrics` è l'unico punto in cui informazioni provenienti dai tenant arrivano nel landlord.

**Regola vincolante:** solo valori numerici o categorici aggregati. Mai nomi, indirizzi,
identificativi di entità, contenuti.

| Ammesso | Vietato |
|---|---|
| numero di utenti attivi | elenco degli utenti |
| byte occupati | nomi dei file |
| numero di operazioni | dettaglio delle operazioni |
| numero di lotti in scadenza | quali lotti |
| percentuale di utilizzo del piano | dati che identificano persone o cose |

---

## Accesso dal codice

```php
// Model di piattaforma: connessione esplicita
final class Tenant extends Model
{
    protected $connection = 'landlord';
}

// Dal contesto tenant, accesso puntuale al landlord
TenantMetric::onLandlord()->create([...]);
```

| Regola | Motivo |
|---|---|
| I model di piattaforma dichiarano `$connection = 'landlord'` | esplicito, verificabile |
| I model di dominio non dichiarano connessione | usano quella attiva, cioè il tenant |
| L'accesso al landlord dal contesto tenant è esplicito | rende visibile l'attraversamento |
| Nessun model di dominio eredita da un model di piattaforma | eviterebbe l'ereditarietà della connessione |

```php
arch('i model di dominio non usano la connessione landlord')
    ->expect('App\Domain')
    ->not->toUse('App\Models\Landlord');
```

---

## Esempi

### Esempio 1 — decisione di collocazione

«Dove metto le preferenze di notifica di un utente?»

L'utente è del tenant, quindi le sue preferenze sono dati del cliente → **tenant**.

«Dove metto la configurazione SMTP con cui inviamo le mail?»

È infrastruttura di piattaforma → configurazione applicativa, non database. Se un tenant ha un
proprio SMTP, quella configurazione sta nel **tenant**.

### Esempio 2 — aggregato corretto e scorretto

```php
// ✓ Solo numeri
TenantMetric::create([
    'tenant_id' => $tenant->id,
    'expiring_batches' => 12,
]);

// ✗ Dati del cliente nel landlord
TenantMetric::create([
    'tenant_id' => $tenant->id,
    'expiring_batches' => ['LOT-0042', 'LOT-0043'],   // identificativi di dominio
]);
```

---

## Best practice

- Applicare il criterio «descrive il cliente / è del cliente» ad ogni nuova tabella.
- Dichiarare esplicitamente la connessione nei model di piattaforma.
- Tenere `tenants` minimale: ciò che cresce va nei tenant.
- Registrare ogni evento di ciclo di vita in `tenant_events`.
- Tracciare ogni accesso del personale ai dati di un cliente.
- Trattare il landlord come componente critico: replica, backup frequenti, monitoraggio.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Dati di dominio nel landlord | Isolamento compromesso | Criterio di appartenenza |
| Utenti dei clienti nel landlord | Confusione tra i due livelli di accesso | Utenti nel tenant |
| Aggregati con dati identificativi | Fuga di dati verso la piattaforma | Solo numeri |
| `slug` modificabile | Database, storage e cache non più coerenti | Immutabile |
| Model di dominio senza connessione esplicita che eredita il landlord | Scritture nel database sbagliato | Test di architettura |
| Accesso ai tenant non tracciato | Impossibile rispondere a una contestazione | Procedura tracciata |

---

## Checklist

- [ ] Ogni tabella del landlord supera il criterio «descrive il cliente».
- [ ] Nessun dato di dominio è nel landlord.
- [ ] I model di piattaforma dichiarano `$connection = 'landlord'`.
- [ ] Gli aggregati contengono solo valori numerici.
- [ ] Ogni dominio è unico sull'intera piattaforma.
- [ ] Gli eventi di ciclo di vita sono registrati.
- [ ] Gli accessi del personale ai tenant sono tracciati.
- [ ] Il landlord ha replica e backup frequenti.

---

## Riferimenti

- [Multitenancy](03-multitenancy-overview.md) · [Database tenant](05-tenant-databases.md)
- [Risoluzione del tenant](06-tenant-resolution.md) · [Ciclo di vita](07-tenant-lifecycle.md)
- [Autenticazione](08-authentication.md)
- [Workflow del database](../docs/03-development/03-database-workflow.md)
