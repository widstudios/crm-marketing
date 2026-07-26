# Deployment — Docker

> L'immagine dell'applicazione, i servizi che la circondano, e le configurazioni che cambiano il
> comportamento in produzione.

---

## Indice

1. [Descrizione](#descrizione) 2. [I file](#i-file) 3. [L'immagine](#limmagine)
4. [I servizi](#i-servizi) 5. [Configurazioni che contano](#configurazioni-che-contano)
6. [Esempi](#esempi) 7. [Best practice](#best-practice) 8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist) 10. [Riferimenti](#riferimenti)

---

## Descrizione

L'immagine di un'applicazione Laravel è un problema quasi risolto: gli esempi disponibili
funzionano. Le differenze che contano sono poche, e nessuna riguarda il funzionamento — riguardano
il comportamento sotto carico, la superficie esposta e la riproducibilità dei difetti.

Le tre decisioni di questa cartella:

1. **una sola immagine** per web e worker;
2. **costruzione a più stadi**, perché gli strumenti di build non entrano in produzione;
3. **OPcache configurato**, perché lasciarlo ai valori predefiniti costa un fattore tre sui tempi
   di risposta.

---

## I file

| File | Contenuto |
|---|---|
| [`php/php.ini`](php/php.ini) | limiti, errori, fuso, sessione |
| [`php/opcache.ini`](php/opcache.ini) | OPcache dimensionato per Laravel con Filament |
| [`nginx/default.conf`](nginx/default.conf) | server web, intestazioni di sicurezza, cache degli asset |

Il `Dockerfile` e il `compose.yaml` del progetto si generano da
[`templates/infrastructure/`](../../templates/infrastructure/README.md).

---

## L'immagine

```
stadio 1  composer:2       dipendenze PHP, senza dev, con autoload ottimizzato
stadio 2  node:22-alpine   asset compilati con Vite
stadio 3  php:8.4-fpm      runtime: solo ciò che serve a eseguire
```

Composer, Node e le dipendenze di sviluppo pesano centinaia di megabyte e sono superficie di
attacco che in produzione non serve a nulla. La costruzione a più stadi li lascia fuori.

**Una sola immagine per web e worker.** Non è un risparmio: due immagini divergono, e la divergenza
produce difetti che si manifestano solo in coda — dove sono più difficili da riprodurre perché non
c'è una richiesta da rieseguire.

```yaml
worker:
    image: magazzino:a1b2c3d          # la stessa
    command: php artisan queue:work --queue=high,default --tries=3 --max-time=3600
```

`--max-time=3600` fa terminare il worker dopo un'ora e lascia che il supervisore lo riavvii: è il
modo più semplice di non accumulare memoria in un processo che vivrebbe per sempre.

**Il processo non gira come root.** Se un'esecuzione arbitraria di codice riesce, la differenza tra
`www-data` e `root` è la differenza tra un incidente e una macchina compromessa.

**Il controllo di salute verifica che l'applicazione risponda**, non che il processo esista: un
PHP-FPM vivo con il database irraggiungibile è «su» per l'orchestratore e inutile per gli utenti.

---

## I servizi

| Servizio | Immagine | Note |
|---|---|---|
| `app` | l'immagine dell'applicazione | PHP-FPM |
| `web` | `nginx:alpine` | serve `public/`, inoltra a `app` |
| `worker` | **la stessa di `app`** | `queue:work` |
| `scheduler` | **la stessa di `app`** | `schedule:work` |
| `database` | `mysql:8.4` | `utf8mb4_unicode_ci` |
| `redis` | `redis:7-alpine` | cache, code, sessioni, lock |
| `mail` | `axllent/mailpit` | **solo** in locale |

Il servizio di posta locale non è una comodità: un ambiente di prova che invia messaggi ai clienti è
un incidente, non un difetto.

---

## Configurazioni che contano

### `opcache.validate_timestamps = 0`

In produzione i file non cambiano senza un container nuovo, quindi controllare le date a ogni
richiesta è lavoro sprecato.

**Conseguenza:** dopo un deploy i worker vanno riavviati con `queue:restart`, perché mantengono in
memoria il codice caricato all'avvio. È la voce che si dimentica più spesso nella sequenza di
rilascio, e il sintomo — codice vecchio che gira solo in coda — non suggerisce la causa.

### Limiti coerenti tra nginx e PHP

```
nginx  client_max_body_size 24M
PHP    post_max_size        24M
PHP    upload_max_filesize  20M
app    documents.max_size_kb 20480
```

L'ordine è deliberato. Se il limite di nginx fosse più basso, l'utente riceverebbe un errore del
server invece del messaggio dell'applicazione; se il limite applicativo fosse più alto di quello di
PHP, il file verrebbe trasferito per intero prima di essere rifiutato.

### `fastcgi_read_timeout > max_execution_time`

60 secondi contro 30. Se fosse il contrario, nginx chiuderebbe la connessione mentre PHP sta ancora
lavorando, e nei log comparirebbe un `502` senza alcuna causa apparente.

### `location /storage/ { deny all; }`

I file dei clienti non si servono dal server web: vivono su un disco privato e passano da un
controller che autorizza, o da un URL firmato a scadenza breve.

---

## Esempi

### Costruzione ed etichettatura

```bash
docker build -t magazzino:$(git rev-parse --short HEAD) .
docker tag magazzino:$(git rev-parse --short HEAD) registry.example.test/magazzino:$(git rev-parse --short HEAD)
docker push registry.example.test/magazzino:$(git rev-parse --short HEAD)
```

Mai `latest`. Durante un incidente la prima domanda è «quale codice sta girando», e `latest` non
risponde.

### Verifica dell'immagine prima di pubblicarla

```bash
docker run --rm magazzino:a1b2c3d php -v
docker run --rm magazzino:a1b2c3d php -m | grep -E 'opcache|redis|pdo_mysql|intl|bcmath'
docker run --rm magazzino:a1b2c3d php -i | grep -E 'opcache.validate_timestamps|memory_limit'
docker run --rm magazzino:a1b2c3d whoami        # deve rispondere www-data
```

Quattro comandi, dieci secondi. Intercettano l'estensione dimenticata, che altrimenti si scopre alla
prima richiesta in produzione.

---

## Best practice

- Una sola immagine per web e worker, sempre.
- Etichettare con il commit, mai con `latest`.
- Verificare estensioni, configurazione e utente prima di pubblicare.
- Tenere i limiti coerenti lungo tutta la catena: nginx, PHP, applicazione.
- Ricordare `queue:restart` nella sequenza di rilascio.
- Non montare il codice come volume in produzione: l'immagine è l'artefatto.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Immagine diversa per i worker | Difetti riproducibili solo in coda | Stessa immagine |
| `latest` come tag | Non si sa quale codice gira | Tag con il commit |
| OPcache ai valori predefiniti | Tempi di risposta tre volte peggiori | `opcache.ini` |
| `queue:restart` dimenticato | I worker eseguono il codice vecchio | Nella sequenza |
| Limiti incoerenti nginx/PHP | Errori del server al posto dei messaggi applicativi | Catena coerente |
| `fastcgi_read_timeout` troppo basso | `502` senza causa apparente | Maggiore di `max_execution_time` |
| Container come root | Un'esecuzione arbitraria diventa compromissione | `USER www-data` |
| `display_errors = On` | Percorsi e configurazione esposti | `Off`, con log |
| Codice montato come volume in produzione | L'immagine non è più l'artefatto | Solo in locale |
| `/storage/` servito da nginx | File dei clienti accessibili | `deny all` |
| Dipendenze di sviluppo nell'immagine | Superficie di attacco inutile | Costruzione a più stadi |

---

## Checklist

- [ ] La costruzione è a più stadi; nessuno strumento di build nell'immagine finale.
- [ ] Web e worker usano la stessa immagine.
- [ ] Il processo gira come `www-data`.
- [ ] OPcache è configurato, con `validate_timestamps = 0`.
- [ ] `queue:restart` è nella sequenza di rilascio.
- [ ] I limiti di dimensione sono coerenti lungo tutta la catena.
- [ ] `fastcgi_read_timeout` è maggiore di `max_execution_time`.
- [ ] Le intestazioni di sicurezza sono presenti.
- [ ] `/storage/` non è servito dal server web.
- [ ] L'immagine è etichettata con il commit e verificata prima della pubblicazione.

---

## Riferimenti

- [Deployment](../README.md) · [Ambienti](../environments/README.md) · [Pipeline](../ci/README.md)
- [Template Dockerfile](../../templates/infrastructure/Dockerfile.stub) · [compose](../../templates/infrastructure/compose.yaml.stub)
- [Regole di deployment](../../rules/deployment.md) · [Performance](../../rules/performance.md)
- [Ambiente locale](../../docs/01-getting-started/02-local-environment.md)
