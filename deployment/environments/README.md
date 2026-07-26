# Deployment — Ambienti

> Locale, staging e produzione: che cosa cambia, che cosa non deve cambiare, e come si verifica.

---

## Indice

1. [Descrizione](#descrizione) 2. [I tre ambienti](#i-tre-ambienti)
3. [Che cosa non cambia mai](#che-cosa-non-cambia-mai) 4. [Configurazione per ambiente](#configurazione-per-ambiente)
5. [I segreti](#i-segreti) 6. [Verificare un ambiente](#verificare-un-ambiente) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Le differenze tra un ambiente e l'altro sono il posto in cui si nascondono i difetti che «in locale
funzionava». Ogni differenza è una possibilità in più che qualcosa si comporti diversamente proprio
dove non si può provare.

Il criterio guida: **le differenze sono poche, dichiarate e motivate**. Una differenza che nessuno
ricorda di aver introdotto produce, prima o poi, un incidente che nessuno riesce a spiegare.

---

## I tre ambienti

| | Locale | Staging | Produzione |
|---|---|---|---|
| `APP_ENV` | `local` | `staging` | `production` |
| `APP_DEBUG` | `true` | **`false`** | **`false`** |
| Database | MySQL in container | MySQL, dati simili a produzione | MySQL |
| Suite di test | SQLite in memoria | — | — |
| Redis | container | dedicato | dedicato |
| Code | worker in container | worker reale | worker reale, per coda |
| Posta | catturatore locale | catturatore, **mai** indirizzi reali | servizio reale |
| Storage | disco locale | disco separato | disco separato, con backup |
| HTTPS | no | sì | sì, con HSTS |
| Telescope | attivabile | **no** | **no** |
| Risoluzione per header | **sì** | no | no |
| Backup | no | settimanale | quotidiano, verificato |
| Dati | inventati | **anonimizzati** | reali |

### `APP_DEBUG=false` anche in staging

È la voce che si è più tentati di derogare, perché con `true` la diagnosi è più rapida. È anche la
voce che espone configurazione, percorsi e talvolta credenziali a chiunque provochi un errore, e
staging è raggiungibile.

Se serve più informazione in staging si alza il livello di log, non si abbassa la protezione.

### Dati anonimizzati in staging

Copiare il database di produzione in staging è comodo e risolve il problema dei volumi realistici.
Significa anche avere una seconda copia dei dati dei clienti, in un ambiente con controlli più
deboli, accessibile a più persone e con backup che nessuno ricorda di avere.

I dati si copiano **anonimizzati**: nomi, indirizzi, contatti, codici fiscali e dati sanitari
sostituiti. I volumi restano quelli veri, che è ciò che serviva davvero.

---

## Che cosa non cambia mai

Questo elenco è più importante del precedente: sono le cose che, se differiscono, rendono le prove
inutili.

| Non cambia | Perché |
|---|---|
| La versione di PHP | un comportamento diverso del linguaggio invalida ogni prova |
| Le versioni delle dipendenze | `composer.lock` è lo stesso ovunque |
| Il tipo di base dati | MySQL in staging e produzione, sempre |
| L'immagine dell'applicazione | quella provata in staging è quella che va in produzione |
| L'immagine dei worker | identica a quella del web |
| La struttura delle code | stesse code, stesse priorità |
| I bootstrapper della tenancy | l'isolamento non si prova su una configurazione diversa |
| Lo schema del database | staging ha lo schema di produzione, non uno simile |

L'immagine merita una precisazione: **si costruisce una volta** e si promuove. Ricostruirla per la
produzione significa che quella provata non è quella rilasciata, e la differenza può essere una
dipendenza transitiva aggiornata nel frattempo.

---

## Configurazione per ambiente

### Locale

```env
APP_ENV=local
APP_DEBUG=true
LOG_LEVEL=debug
MAIL_MAILER=smtp          # verso il catturatore locale
FOUNDATION_CENTRAL_DOMAIN=admin.localhost
```

I domini dei tenant richiedono voci in `/etc/hosts`, oppure si usa l'header `X-Tenant`, che la
Foundation accetta **solo** in `local` e `testing`.

### Staging

```env
APP_ENV=staging
APP_DEBUG=false
LOG_LEVEL=info
TELESCOPE_ENABLED=false
SESSION_SECURE_COOKIE=true
```

Staging serve a una cosa che nessun altro ambiente può fare: **provare il rilascio per intero**,
incluso il piano di ritorno. Un piano di ritorno mai eseguito non è un piano.

### Produzione

```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
TELESCOPE_ENABLED=false
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

Più `config:cache`, `route:cache`, `view:cache`, `event:cache` nella sequenza di rilascio — e
`queue:restart` **dopo** il deploy del codice.

---

## I segreti

I segreti non stanno in un file `.env` copiato a mano. Stanno nel gestore di segreti dell'ambiente,
e `.env.example` dichiara **quali** variabili servono, non quanto valgono.

| | Locale | Staging | Produzione |
|---|---|---|---|
| Origine | `.env` locale, non versionato | gestore di segreti | gestore di segreti |
| Rotazione | non necessaria | trimestrale | trimestrale |
| Accesso | lo sviluppatore | chi rilascia | chi rilascia, tracciato |

L'utenza del database che serve le richieste **non** ha bisogno di `CREATE DATABASE`. Il
provisioning di un tenant usa un'utenza dedicata, con quel privilegio e nient'altro: un'iniezione
SQL riuscita sull'utenza applicativa non deve poter creare o cancellare database.

---

## Verificare un ambiente

```bash
php artisan about                       # ambiente, debug, driver, versioni
php artisan config:show foundation      # configurazione della tenancy
php artisan tenants:list                # tenant e stato
php artisan queue:monitor high,default  # code e accumuli
php artisan health:check                # controllo di salute
```

Verifica minima dopo ogni modifica alla configurazione di un ambiente:

1. `APP_DEBUG` è `false` fuori da locale;
2. Telescope è disabilitato;
3. il disco dei tenant è privato e scrivibile;
4. Redis è raggiungibile e i driver di cache, coda e sessione lo usano;
5. i worker girano sulla stessa immagine del web;
6. le variabili nuove sono impostate — non solo documentate.

L'ultima è quella che manda in errore i rilasci: la variabile è nel `.env.example`, la
documentazione la cita, e sull'ambiente di destinazione non è stata impostata.

---

## Esempi

### Una differenza che si paga

```
Locale     SQLite in memoria
Staging    SQLite su file
Produzione MySQL 8.4
```

Un vincolo di unicità su una colonna `varchar(191)` con collation `utf8mb4_unicode_ci` considera
uguali `ACME` e `acme`. SQLite no. Il difetto — un duplicato rifiutato dove non dovrebbe esserlo —
compare solo in produzione, e sembra un errore applicativo.

Regola: **staging e produzione usano lo stesso tipo di base dati**, sempre.

### Anonimizzazione, non esclusione

```php
// ✗ Staging senza dati: i problemi di volume non si vedono
// ✓ Staging con i volumi di produzione e i dati sostituiti
$user->update([
    'name' => fake()->name(),
    'email' => "utente{$user->id}@esempio-non-valido.test",
    'fiscal_code' => null,
]);
```

Il volume è ciò che serviva; l'identità no.

---

## Best practice

- Poche differenze, tutte dichiarate e motivate.
- Costruire l'immagine una volta e promuoverla: quella provata è quella rilasciata.
- Stesso tipo di base dati in staging e produzione.
- Dati anonimizzati in staging, con i volumi di produzione.
- Provare il rilascio **e il ritorno** su staging, anche senza un rilascio in corso.
- Verificare che le variabili nuove siano impostate, non solo documentate.
- Utenza di provisioning separata da quella applicativa.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `APP_DEBUG=true` in staging | Configurazione e percorsi esposti | `false`, con log più verboso |
| Basi dati diverse tra staging e produzione | Difetti su vincoli e collation invisibili | Stesso tipo |
| Dati di produzione copiati in chiaro | Seconda copia dei dati dei clienti, meno protetta | Anonimizzazione |
| Immagine ricostruita per la produzione | Quella provata non è quella rilasciata | Costruire una volta, promuovere |
| Worker su immagine diversa | Difetti riproducibili solo in coda | Stessa immagine |
| Variabile nuova documentata e non impostata | Errore al primo utilizzo in produzione | Verifica esplicita |
| Utenza applicativa con `CREATE DATABASE` | Un'iniezione riuscita può cancellare database | Utenza dedicata |
| Segreti in un `.env` copiato a mano | Divergono, e nessuno sa quale sia quello buono | Gestore di segreti |
| Risoluzione per header attiva fuori da locale | Chiunque sceglie il cliente da leggere | Verifica nel codice |
| Staging senza backup | Un errore di prova costa il lavoro di prova | Backup settimanale |

---

## Checklist

- [ ] `APP_DEBUG=false` in staging e produzione.
- [ ] Telescope disabilitato fuori da locale.
- [ ] Staging e produzione usano lo stesso tipo di base dati.
- [ ] I dati di staging sono anonimizzati, con volumi realistici.
- [ ] L'immagine è costruita una volta e promossa.
- [ ] I worker girano sulla stessa immagine del web.
- [ ] Le variabili nuove sono impostate sull'ambiente di destinazione.
- [ ] I segreti vengono dal gestore di segreti, non da file copiati.
- [ ] L'utenza di provisioning è separata da quella applicativa.
- [ ] Il piano di ritorno è stato provato su staging.

---

## Riferimenti

- [Deployment](../README.md) · [Docker](../docker/README.md) · [Pipeline](../ci/README.md) · [Runbook](../runbooks/README.md)
- [Ambienti](../../docs/05-operations/01-environments.md) · [Ambiente locale](../../docs/01-getting-started/02-local-environment.md)
- [Configurazione](../../rules/configuration.md) · [Sicurezza](../../rules/security.md)
- [Riferimento di configurazione](../../docs/06-reference/04-configuration-reference.md)
