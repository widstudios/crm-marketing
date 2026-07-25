# Riferimento dei comandi

> Tutti i comandi standard di un progetto della Factory, raggruppati per attività.

---

## Indice

1. [Descrizione](#descrizione)
2. [Composer](#composer)
3. [Ambiente Docker](#ambiente-docker)
4. [Database](#database)
5. [Tenant](#tenant)
6. [Code e scheduler](#code-e-scheduler)
7. [Cache e ottimizzazione](#cache-e-ottimizzazione)
8. [Generazione di artefatti](#generazione-di-artefatti)
9. [Diagnosi](#diagnosi)
10. [Comandi della Factory](#comandi-della-factory)
11. [Esempi](#esempi)
12. [Best practice](#best-practice)
13. [Errori comuni](#errori-comuni)
14. [Checklist](#checklist)
15. [Riferimenti](#riferimenti)

---

## Descrizione

Ogni progetto generato espone **gli stessi comandi**, con la stessa sintassi. Impararli una volta
vale per tutti i progetti: è uno dei benefici più immediati della standardizzazione.

I comandi contrassegnati con ⚠️ sono distruttivi e non vanno eseguiti in produzione senza
procedura.

---

## Composer

| Comando | Cosa fa |
|---|---|
| `composer qa` | lint + analisi statica + test (da eseguire prima di ogni commit) |
| `composer test` | suite Pest completa |
| `composer test:unit` | solo test unitari |
| `composer test:feature` | solo test di feature |
| `composer test:arch` | solo test di architettura |
| `composer test:tenant` | solo test di isolamento |
| `composer test:coverage` | test con rapporto di copertura |
| `composer lint` | Pint in verifica |
| `composer lint:fix` | Pint applica le correzioni |
| `composer analyse` | PHPStan livello 8 |
| `composer refactor` | Rector in anteprima |
| `composer refactor:apply` | Rector applica |

---

## Ambiente Docker

| Comando | Cosa fa |
|---|---|
| `docker compose up -d` | avvia tutti i servizi |
| `docker compose down` | ferma i servizi |
| `docker compose down -v` | ⚠️ ferma ed elimina i volumi (dati locali persi) |
| `docker compose ps` | stato dei servizi |
| `docker compose logs -f app` | log applicativi in tempo reale |
| `docker compose exec app bash` | shell nel container applicativo |
| `docker compose build --no-cache app` | ricostruzione dell'immagine |
| `docker compose restart queue` | riavvio dei worker |

---

## Database

| Comando | Cosa fa |
|---|---|
| `php artisan migrate --database=landlord` | migration sul landlord |
| `php artisan migrate:rollback --database=landlord` | rollback landlord |
| `php artisan migrate:status --database=landlord` | stato delle migration landlord |
| `php artisan migrate:fresh --database=landlord --seed` | ⚠️ ricostruzione completa del landlord |
| `php artisan db:seed --class=System\\PermissionSeeder` | esecuzione di un seeder |

---

## Tenant

| Comando | Cosa fa |
|---|---|
| `php artisan tenant:list` | elenco dei tenant con stato e versione di schema |
| `php artisan tenant:create <nome> --domain=<dominio>` | provisioning di un tenant |
| `php artisan tenant:suspend <nome> --reason="<motivo>"` | sospensione |
| `php artisan tenant:resume <nome>` | riattivazione |
| `php artisan tenant:terminate <nome> --confirm=<nome>` | ⚠️ avvio della dismissione |
| `php artisan tenants:migrate` | migration su tutti i tenant |
| `php artisan tenants:migrate --tenant=<nome>` | migration su un tenant |
| `php artisan tenants:migrate:status` | allineamento dello schema per tenant |
| `php artisan tenants:migrate:rollback --step=1` | ⚠️ rollback su tutti i tenant |
| `php artisan tenants:migrate --fresh --seed` | ⚠️ ricostruzione di tutti i tenant |
| `php artisan tenant:artisan "<comando>" --tenant=<nome>` | esegue un comando nel contesto del tenant |
| `php artisan tenants:backup` | backup di tutti i tenant |
| `php artisan tenant:restore <nome> --backup=<data>` | ⚠️ ripristino di un tenant |
| `php artisan tenant:export <nome> --format=sql` | esportazione dei dati |
| `php artisan tenants:verify` | verifica di integrità |

---

## Code e scheduler

| Comando | Cosa fa |
|---|---|
| `php artisan queue:work --queue=high,default` | worker in primo piano |
| `php artisan queue:restart` | segnala ai worker di riavviarsi (obbligatorio dopo il deploy) |
| `php artisan queue:failed` | elenco dei job falliti |
| `php artisan queue:retry all` | riprova tutti i job falliti |
| `php artisan queue:flush` | ⚠️ elimina i job falliti |
| `php artisan horizon` | avvia Horizon |
| `php artisan horizon:status` | stato di Horizon |
| `php artisan horizon:terminate` | arresto controllato |
| `php artisan schedule:list` | attività pianificate |
| `php artisan schedule:work` | scheduler in primo piano |

---

## Cache e ottimizzazione

| Comando | Cosa fa |
|---|---|
| `php artisan optimize` | cache di configurazione, rotte, viste, eventi |
| `php artisan optimize:clear` | svuota tutte le cache di framework |
| `php artisan config:cache` | cache della configurazione (produzione) |
| `php artisan route:cache` | cache delle rotte |
| `php artisan view:cache` | precompilazione delle viste |
| `php artisan cache:clear` | svuota la cache applicativa |
| `php artisan tenant:artisan "cache:clear" --tenant=<nome>` | svuota la cache di un tenant |

`php artisan config:cache` in produzione rende `env()` inefficace fuori da `config/`: è il motivo
della regola corrispondente.

---

## Generazione di artefatti

| Comando | Cosa genera |
|---|---|
| `php artisan make:action <Nome>` | Action dal template della Factory |
| `php artisan make:data <Nome>` | DTO |
| `php artisan make:query <Nome>` | Query object |
| `php artisan make:repository <Nome>` | Repository + contratto |
| `php artisan make:domain-event <Nome>` | evento di dominio |
| `php artisan make:module <nome>` | struttura completa di un modulo |
| `php artisan make:tenant-migration <nome>` | migration nella cartella tenant |
| `php artisan make:filament-resource <Nome>` | Filament Resource |

I comandi `make:*` della Foundation usano i template di [`templates/`](../../templates/README.md):
generare a mano significa dimenticare qualcosa.

---

## Diagnosi

| Comando | Cosa fa |
|---|---|
| `php artisan about` | riepilogo di configurazione e ambiente |
| `php artisan route:list --path=api` | rotte registrate |
| `php artisan tinker` | shell interattiva (contesto landlord) |
| `php artisan tenant:artisan "tinker" --tenant=<nome>` | shell nel contesto di un tenant |
| `php artisan telescope:clear` | svuota Telescope |
| `php artisan pulse:check` | stato delle metriche |
| `php artisan health:check` | verifica delle dipendenze |

---

## Comandi della Factory

Eseguiti nel repository della Factory, non nei progetti:

| Comando | Cosa fa |
|---|---|
| `php tooling/scripts/check-docs.php` | verifica link interni e struttura dei documenti |
| `php tooling/scripts/check-docs.php --sections` | verifica le sezioni obbligatorie |
| `php tooling/scripts/check-links.php` | verifica solo i link |
| `php tooling/scripts/list-agents.php` | elenco degli agenti con versione dei prompt |

---

## Esempi

### Esempio 1 — sequenza di primo avvio

```bash
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --database=landlord --seed
docker compose exec app php artisan tenant:create acme --domain=acme.localhost
docker compose exec app php artisan tenant:create globex --domain=globex.localhost
docker compose exec app npm ci && npm run dev
```

### Esempio 2 — sequenza di deploy

```bash
php artisan down --render="errors::503"      # solo se indispensabile
php artisan migrate --database=landlord --force
php artisan tenants:migrate --force --chunk=10
php artisan optimize
php artisan queue:restart
php artisan up
```

`--chunk=10` esegue le migration a lotti di dieci tenant: evita di saturare il database.

---

## Best practice

- Eseguire `composer qa` prima di ogni commit.
- Usare i comandi `make:*` della Foundation, non quelli generici di Laravel.
- `queue:restart` sempre dopo un deploy.
- I comandi sui tenant si eseguono a lotti in produzione.
- I comandi ⚠️ non si eseguono in produzione senza procedura e backup.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Dimenticare `queue:restart` dopo il deploy | I worker eseguono il codice vecchio | Inserirlo nella sequenza |
| `migrate:fresh` in produzione | ⚠️ Perdita totale dei dati | Mai; usare il ripristino |
| Comando eseguito senza contesto tenant | Nessun risultato o dati sbagliati | `tenant:artisan` |
| `optimize` in sviluppo | Modifiche non visibili | `optimize:clear` |
| `make:*` di Laravel invece di quelli della Foundation | Template non conformi | Comandi della Foundation |
| Migration su tutti i tenant senza lotti | Saturazione del database | `--chunk` |

---

## Checklist

- [ ] Conosco i comandi contrassegnati come distruttivi.
- [ ] Uso `composer qa` prima di ogni commit.
- [ ] Uso i comandi `make:*` della Foundation.
- [ ] Nella sequenza di deploy è incluso `queue:restart`.
- [ ] I comandi massivi sui tenant usano `--chunk`.

---

## Riferimenti

- [Ambiente locale](../01-getting-started/02-local-environment.md)
- [Operazioni sui tenant](../05-operations/06-tenant-operations.md)
- [Gestione dei rilasci](../05-operations/02-release-management.md)
- [Troubleshooting](05-troubleshooting.md)
