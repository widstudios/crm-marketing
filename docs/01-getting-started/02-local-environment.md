# Ambiente locale

> Come si prepara la macchina di sviluppo, come si avvia un progetto della Factory, e come si
> lavora ogni giorno con un'applicazione multitenant.

---

## Indice

1. [Descrizione](#descrizione)
2. [Requisiti della macchina](#requisiti-della-macchina)
3. [Servizi dell'ambiente](#servizi-dellambiente)
4. [Primo avvio](#primo-avvio)
5. [Tenant di sviluppo](#tenant-di-sviluppo)
6. [Domini locali](#domini-locali)
7. [Comandi quotidiani](#comandi-quotidiani)
8. [Sviluppo con SQLite](#sviluppo-con-sqlite)
9. [Strumenti di diagnosi](#strumenti-di-diagnosi)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

L'ambiente locale deve avere **la stessa composizione di servizi** della produzione: stesso
database, stessa cache, stesso broker di code. Le differenze ammesse riguardano la scala e la
configurazione di debug, mai la natura dei servizi.

Il motivo è concreto: i difetti che emergono solo in produzione sono quasi sempre difetti di
ambiente, e costano dieci volte tanto.

---

## Requisiti della macchina

| Requisito | Versione minima | Verifica |
|---|---|---|
| Docker Engine | 24 | `docker --version` |
| Docker Compose | v2 | `docker compose version` |
| PHP (per gli strumenti locali) | 8.4 | `php -v` |
| Composer | 2.7 | `composer --version` |
| Node.js | 22 LTS | `node -v` |
| Git | 2.40 | `git --version` |
| Memoria disponibile | 8 GB | — |
| Spazio su disco | 20 GB | — |

PHP e Node locali servono solo per gli strumenti di sviluppo (IDE, Pint, Vite in watch).
L'applicazione gira nei container.

---

## Servizi dell'ambiente

Definiti in `docker/compose.yaml` di ogni progetto:

| Servizio | Immagine | Porta locale | Ruolo |
|---|---|---|---|
| `app` | PHP-FPM 8.4 (immagine WidStudios) | — | esecuzione applicativa |
| `web` | Nginx | 8080 | web server |
| `db` | MySQL 8 | 3306 | database landlord e tenant |
| `redis` | Redis 7 | 6379 | cache, code, sessioni, lock |
| `queue` | stessa immagine di `app` | — | worker delle code |
| `scheduler` | stessa immagine di `app` | — | scheduler |
| `mailpit` | Mailpit | 8025 | cattura della posta in uscita |
| `minio` | MinIO | 9000/9001 | storage S3-compatibile |

I servizi `queue` e `scheduler` usano **la stessa immagine** dell'applicazione: una divergenza tra
l'ambiente web e quello dei worker è una fonte classica di difetti difficili da riprodurre.

---

## Primo avvio

```bash
# 1. Clonare il progetto
git clone <repository-progetto> && cd <progetto>

# 2. Configurazione
cp .env.example .env

# 3. Avvio dei servizi
docker compose up -d

# 4. Dipendenze
docker compose exec app composer install
docker compose exec app npm ci

# 5. Chiave applicativa
docker compose exec app php artisan key:generate

# 6. Database landlord
docker compose exec app php artisan migrate --database=landlord --seed

# 7. Primo tenant di sviluppo
docker compose exec app php artisan tenant:create demo --domain=demo.localhost

# 8. Asset
docker compose exec app npm run dev
```

Al termine sono raggiungibili:

| Indirizzo | Contenuto |
|---|---|
| `http://localhost:8080` | landing page pubblica |
| `http://localhost:8080/super-admin` | pannello Super Admin (landlord) |
| `http://demo.localhost:8080/admin` | pannello Tenant Admin del tenant `demo` |
| `http://localhost:8025` | Mailpit, posta catturata |
| `http://localhost:9001` | console MinIO |

---

## Tenant di sviluppo

Lavorare con un solo tenant è l'errore più comune: nasconde esattamente i difetti che la
multitenancy dovrebbe prevenire.

**Regola: in locale esistono sempre almeno due tenant.**

```bash
php artisan tenant:create acme   --domain=acme.localhost
php artisan tenant:create globex --domain=globex.localhost
php artisan tenant:seed --tenant=acme   --class=DemoDataSeeder
php artisan tenant:seed --tenant=globex --class=DemoDataSeeder
```

Con due tenant popolati, una query senza scope si manifesta subito, invece che in produzione
davanti al cliente.

Comandi utili sui tenant:

```bash
php artisan tenant:list                       # elenco e stato
php artisan tenant:migrate --tenant=acme      # migration su un tenant
php artisan tenants:migrate                   # migration su tutti
php artisan tenant:rollback --tenant=acme     # rollback su un tenant
php artisan tenant:artisan "cache:clear" --tenant=acme   # comando nel contesto tenant
php artisan tenant:delete acme --force        # cancellazione (solo in locale)
```

---

## Domini locali

La risoluzione del tenant avviene per dominio. In locale servono nomi risolvibili.

`*.localhost` è risolto automaticamente su `127.0.0.1` dalla maggior parte dei sistemi. Se il
proprio non lo fa, aggiungere le voci al file hosts:

```
127.0.0.1   demo.localhost
127.0.0.1   acme.localhost
127.0.0.1   globex.localhost
```

Su Windows il file è `C:\Windows\System32\drivers\etc\hosts`; su Linux e macOS `/etc/hosts`.

Alternativa senza modifiche al sistema: risoluzione tramite header, ammessa **solo** in ambiente
locale e di test.

```bash
curl -H "X-Tenant: acme" http://localhost:8080/api/v1/articles
```

La risoluzione per header è disabilitata per configurazione in staging e produzione: sarebbe una
via per accedere a un tenant arbitrario.

---

## Comandi quotidiani

Ogni progetto espone gli stessi script Composer. Impararli una volta vale per tutti i progetti.

| Comando | Cosa fa |
|---|---|
| `composer qa` | lint + analisi statica + test: da eseguire prima di ogni commit |
| `composer test` | suite Pest completa |
| `composer test:unit` | solo test unitari (veloci) |
| `composer test:arch` | solo test di architettura |
| `composer lint` | Pint in modalità verifica |
| `composer lint:fix` | Pint applica le correzioni |
| `composer analyse` | PHPStan livello 8 |
| `composer refactor` | Rector in modalità anteprima |

Comandi Artisan ricorrenti:

```bash
php artisan tenant:create <nome> --domain=<dominio>
php artisan tenants:migrate --fresh --seed     # ricostruisce tutti i tenant (solo locale)
php artisan queue:work --queue=high,default    # worker in primo piano per il debug
php artisan schedule:work                      # scheduler in primo piano
php artisan telescope:clear                    # svuota Telescope
php artisan optimize:clear                     # svuota tutte le cache di framework
```

---

## Sviluppo con SQLite

SQLite è ammesso per la suite di test e per lo sviluppo rapido, con due avvertenze.

**Configurazione per i test** (`phpunit.xml`):

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="CACHE_STORE" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
```

**Le due avvertenze:**

1. Prima di aprire una PR, eseguire la suite **anche su MySQL**:
   ```bash
   docker compose exec app php artisan test --env=testing-mysql
   ```
2. Ogni migration va verificata su MySQL: `ALTER TABLE` si comporta in modo diverso e alcune
   modifiche che SQLite accetta falliscono in produzione.

La pipeline esegue entrambe le varianti, ma scoprirlo in locale costa meno.

---

## Strumenti di diagnosi

| Strumento | Attivo in | Uso |
|---|---|---|
| Laravel Telescope | locale, staging | richieste, query, job, eccezioni, mail |
| Laravel Pulse | tutti | metriche applicative aggregate |
| Mailpit | locale | posta in uscita catturata |
| Horizon | tutti | supervisione delle code Redis |
| `php artisan tenant:artisan` | tutti | eseguire un comando nel contesto di un tenant |
| Query log | locale | `DB::listen()` per diagnosticare N+1 |

Telescope **non** va abilitato in produzione: registra dati sensibili e cresce senza controllo.

---

## Esempi

### Esempio 1 — riprodurre un problema segnalato su un tenant

```bash
# 1. Contesto del tenant
php artisan tenant:artisan "tinker" --tenant=acme

# 2. Nel contesto, il modello vede solo i dati di quel tenant
>>> App\Domain\Inventory\Models\Batch::expiringWithin(30)->count()
```

### Esempio 2 — verificare l'isolamento a mano

```bash
php artisan tenant:artisan "tinker" --tenant=acme
>>> App\Models\Article::create(['code' => 'A-001', 'name' => 'Garza']);

php artisan tenant:artisan "tinker" --tenant=globex
>>> App\Models\Article::where('code', 'A-001')->exists();   // deve essere false
```

Se ritorna `true`, l'isolamento è compromesso: si ferma tutto e si diagnostica.

### Esempio 3 — ricostruire l'ambiente da zero

```bash
docker compose down -v          # elimina anche i volumi
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan migrate --database=landlord --seed
docker compose exec app php artisan tenant:create acme --domain=acme.localhost
docker compose exec app php artisan tenant:create globex --domain=globex.localhost
```

---

## Best practice

- Tenere sempre **due tenant** popolati in locale.
- Eseguire `composer qa` prima di ogni commit, non prima della PR.
- Usare Mailpit e non indirizzi reali: una mail di prova a un cliente è un incidente.
- Ricostruire l'ambiente da zero ogni tanto: verifica che la procedura documentata funzioni ancora.
- Mantenere `.env.example` allineato ad ogni nuova variabile introdotta.
- Non installare estensioni PHP «solo in locale»: se servono, vanno nell'immagine.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Sviluppare con un solo tenant | Query senza scope non rilevate | Due tenant sempre attivi |
| Testare solo su SQLite | Migration che falliscono in produzione | Suite anche su MySQL prima della PR |
| Immagini diverse per web e worker | Difetti non riproducibili | Stessa immagine per tutti i servizi PHP |
| `.env` committato | Segreti esposti | `.gitignore`, solo `.env.example` |
| Telescope attivo in produzione | Dati sensibili registrati, disco pieno | Abilitato solo in locale e staging |
| Modificare il database a mano | Divergenza dallo schema versionato | Sempre tramite migration |
| Header `X-Tenant` abilitato oltre il locale | Accesso a tenant arbitrari | Disabilitato per configurazione |

---

## Checklist

- [ ] Requisiti della macchina soddisfatti.
- [ ] `docker compose up -d` avvia tutti i servizi senza errori.
- [ ] Landlord migrato e popolato.
- [ ] Almeno due tenant creati e popolati.
- [ ] Domini locali risolvibili.
- [ ] `composer qa` verde.
- [ ] Mailpit riceve la posta di prova.
- [ ] Telescope raggiungibile in locale.

---

## Riferimenti

- [Avviare un nuovo progetto](01-new-project.md) · [Workflow quotidiano](05-daily-workflow.md)
- [Docker](../../deployment/docker/README.md) · [Ambienti](../05-operations/01-environments.md)
- [Operazioni sui tenant](../05-operations/06-tenant-operations.md)
- [Riferimento comandi](../06-reference/03-command-reference.md)
- [Troubleshooting](../06-reference/05-troubleshooting.md)
