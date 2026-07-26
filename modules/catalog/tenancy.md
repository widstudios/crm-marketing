# Modulo — tenancy

> La persistenza dei tenant: chi sono, dove stanno i loro dati, come nascono e come finiscono.

| | |
|---|---|
| **Nome** | `tenancy` |
| **Categoria** | base — sempre attivo, non disattivabile |
| **Dipende da** | — |
| **Contratti implementati** | `TenantRepository` |

---

## Indice

1. [Descrizione](#descrizione) 2. [Che cosa fornisce](#che-cosa-fornisce)
3. [Che cosa non fa](#che-cosa-non-fa) 4. [Il ciclo di vita](#il-ciclo-di-vita)
5. [Configurazione](#configurazione) 6. [Integrazione](#integrazione) 7. [Adozione](#adozione)
8. [Esempi](#esempi) 9. [Best practice](#best-practice) 10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist) 12. [Riferimenti](#riferimenti)

---

## Descrizione

La Foundation sa **come** aprire e chiudere il contesto di un tenant. Non sa **chi siano** i tenant:
per quello serve una tabella, e la Foundation non impone tabelle a nessuno.

Questo modulo è quella tabella, più tutto ciò che le sta intorno: i domini, gli stati, il
provisioning del database, la sospensione, l'archiviazione, il pannello di piattaforma da cui si
governano.

È un modulo di base — nessun software multitenant funziona senza — ma resta un modulo, perché ha
migration proprie e perché un progetto potrebbe volerlo sostituire con una propria implementazione
del contratto `TenantRepository`.

---

## Che cosa fornisce

### Tabelle — database di piattaforma

| Tabella | Contenuto |
|---|---|
| `tenants` | slug, nome, stato, date del ciclo di vita |
| `tenant_domains` | domini da cui il tenant è raggiungibile; uno primario |
| `tenant_settings` | configurazione per tenant, in JSON, senza segreti |
| `tenant_provisioning_jobs` | avanzamento del provisioning, per diagnosi |

Il **nome del database non è una colonna**: si deriva dallo slug. Una colonna può divergere dal
database che esiste davvero, e la divergenza si scopre quando la migration fallisce su un cliente
solo.

### Permessi — piattaforma

| Permesso | Consente |
|---|---|
| `tenant.view` | vedere l'elenco e la scheda dei tenant |
| `tenant.create` | creare un tenant e avviarne il provisioning |
| `tenant.update` | modificare nome, domini, impostazioni |
| `tenant.suspend` | sospendere e riattivare |
| `tenant.archive` | archiviare |
| `tenant.impersonate` | accedere ai dati di un tenant per assistenza |

L'ultimo è il più delicato del sistema: l'accesso del personale ai dati di un cliente è **a
scadenza**, tracciato nell'audit del tenant e **notificato al tenant admin**. Un accesso di
assistenza che il cliente non può vedere non è assistenza.

### Comandi

| Comando | Quando |
|---|---|
| `tenants:list` | primo comando di ogni diagnosi |
| `tenants:provision --slug=` | creazione del database, migration, seeder |
| `tenants:migrate` | migration su tutti i tenant, a lotti, con durata per tenant |
| `tenants:artisan "…"` | qualunque comando nel contesto dei tenant |
| `tenants:suspend --slug=` | sospensione |
| `tenants:archive --slug=` | archiviazione |
| `tenants:backup` | backup dei database dei clienti |

### Eventi

| Evento | Emesso quando |
|---|---|
| `TenantProvisioned` | il database è pronto e migrato |
| `TenantActivated` | il tenant passa a operativo |
| `TenantSuspended` | l'accesso viene sospeso |
| `TenantArchived` | il tenant esce dal servizio |

---

## Che cosa non fa

| Non fa | Dove va cercato |
|---|---|
| Aprire e chiudere il contesto tenant | [Foundation](../../foundation/docs/02-tenancy.md) — `TenantManager` |
| Risolvere il tenant dalla richiesta | Foundation — resolver e middleware |
| Autenticare utenti | [auth](auth.md) |
| Fatturazione, abbonamenti, limiti d'uso | non previsto: è dominio di piattaforma, specifico del prodotto |
| Migrazione di un tenant tra server | procedura di esercizio, non funzionalità |
| Backup verificati e ripristino | fornisce il comando; la **verifica** è [esercizio](../../checklists/operations-checklist.md) |
| Cancellazione definitiva dei dati | deliberatamente assente: vedi sotto |

L'ultima riga è una decisione, non una dimenticanza. L'archiviazione conserva i dati per il periodo
di ritenzione dichiarato; la cancellazione definitiva è un'operazione irreversibile su dati di un
cliente, e richiede una decisione umana, una verifica del periodo di conservazione e una traccia. Un
comando che la esegue con un `--force` è il modo più rapido di perdere i dati di qualcuno.

---

## Il ciclo di vita

```
                    provisioning
                    │        │
      (database ok) │        │ (fallito)
                    ▼        ▼
                  active   archived
                  │    ▲
       sospensione│    │riattivazione
                  ▼    │
                suspended
                  │
                  ▼
               archived      ← stato terminale
```

| Stato | Accesso | Migrazioni e backup |
|---|---|---|
| `provisioning` | no | no |
| `active` | sì | sì |
| `suspended` | no | **sì** |
| `archived` | no | no |

Un tenant sospeso continua a ricevere migration e backup. La sospensione riguarda l'accesso, non i
dati: un tenant sospeso escluso dalle migration non potrebbe più essere riattivato, e la scoperta
avverrebbe nel momento peggiore — quando il cliente ha pagato e vuole rientrare.

---

## Configurazione

Il modulo non introduce configurazione propria: usa `config('foundation.tenancy.*')`. Averne una
seconda significherebbe due posti in cui il prefisso dei database può essere dichiarato, e due
occasioni di divergenza.

---

## Integrazione

```php
// L'unico binding obbligatorio di un progetto Factory
$this->app->singleton(
    WidStudios\Foundation\Contracts\Tenancy\TenantRepository::class,
    App\Modules\Tenancy\Infrastructure\EloquentTenantRepository::class,
);
```

Il codice applicativo non usa quasi mai questo modulo direttamente: lavora già dentro il contesto di
un tenant, aperto dal middleware. Lo usano il pannello di piattaforma, i comandi e le procedure di
esercizio.

---

## Adozione

È attivo dal primo giorno di ogni progetto generato. Su un progetto esistente:

```bash
php artisan migrate --database=landlord
php artisan db:seed --class=System\\PlatformPermissionSeeder
php artisan tenants:provision --slug=acme
php artisan tenants:list
```

---

## Esempi

### Provisioning

```php
final class ProvisionTenantAction extends BaseAction
{
    public function execute(ProvisionTenantData $data): Tenant
    {
        $tenant = $this->transaction(fn (): Tenant => Tenant::query()->create([
            'name' => $data->name,
            'slug' => $data->slug,
            'status' => TenantStatus::Provisioning,
        ]));

        // Fuori dalla transazione: la creazione di un database è DDL, e in
        // MySQL un DDL provoca un commit implicito. Tenerla dentro darebbe
        // l'illusione di una transazione che non esiste.
        $this->databases->create($tenant->getDatabaseName());

        $this->tenancy->run($tenant, function (): void {
            Artisan::call('migrate', ['--database' => config('foundation.tenancy.tenant_connection'), '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'System\\PermissionSeeder']);
        });

        $tenant->update(['status' => TenantStatus::Active]);

        return $tenant;
    }
}
```

### Accesso di assistenza

```php
final class ImpersonateTenantAction extends BaseAction
{
    use RecordsAudit;

    public function execute(ImpersonateTenantData $data): void
    {
        $tenant = $this->tenants->findBySlugOrFail($data->slug);

        // Nell'audit DEL TENANT, non in quello di piattaforma: è il cliente
        // che deve poter vedere chi ha guardato i suoi dati.
        $this->tenancy->run($tenant, function () use ($data): void {
            $this->audit('platform.access_granted', null, [
                'operator_id' => $data->operatorId,
                'expires_at' => $data->expiresAt->format(DATE_ATOM),
                'reason_code' => $data->reasonCode,
            ]);
        });

        $tenant->admins()->each->notify(new PlatformAccessGranted($data->expiresAt));
    }
}
```

---

## Best practice

- Derivare il nome del database dallo slug, mai conservarlo in colonna.
- Provare il provisioning di un tenant **nuovo** in CI: intercetta le migration che dipendono dai
  dati esistenti, difetto che rende impossibile attivare nuovi clienti.
- Verificare che ogni tenant nuovo compaia nel backup della notte successiva.
- Misurare la durata delle migration per tenant prima di ogni rilascio.
- Non riusare mai uno slug: cache, file e job in coda ne conservano traccia.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Nome del database in colonna | Diverge dal database reale su un cliente | Derivarlo dallo slug |
| Slug riusato dopo un'archiviazione | Cache e file del tenant precedente | Slug irripetibili |
| Tenant sospesi esclusi dalle migration | Non riattivabili | `allowsProcessing()` |
| Creazione del database in transazione | Il commit implicito rompe l'atomicità | Fuori dalla transazione |
| Provisioning mai provato in CI | I nuovi clienti non si attivano dopo un rilascio | Prova automatica |
| Accesso di assistenza non notificato | Il cliente non sa chi ha visto i suoi dati | Audit del tenant + notifica |
| Nuovo tenant non verificato nel backup | Il cliente più recente è il meno protetto | Verifica esplicita |

---

## Checklist

- [ ] Il model del tenant implementa il contratto `Tenant`.
- [ ] `TenantRepository` è registrato nel container.
- [ ] Il provisioning di un tenant nuovo è verificato in CI.
- [ ] I tenant sospesi ricevono migration e backup.
- [ ] Gli accessi di assistenza sono a scadenza, tracciati e notificati.
- [ ] Ogni tenant nuovo compare nel backup della notte successiva.
- [ ] Gli slug non vengono mai riusati.

---

## Riferimenti

- [Multitenancy](../../architecture/03-multitenancy-overview.md) · [ADR-0002](../../architecture/decisions/0002-tenant-isolation-strategy.md)
- [Landlord](../../architecture/04-landlord-database.md) · [Database tenant](../../architecture/05-tenant-databases.md)
- [Risoluzione del tenant](../../architecture/06-tenant-resolution.md) · [Ciclo di vita](../../architecture/07-tenant-lifecycle.md)
- [Foundation — Tenancy](../../foundation/docs/02-tenancy.md)
- [Operazioni sui tenant](../../docs/05-operations/06-tenant-operations.md)
