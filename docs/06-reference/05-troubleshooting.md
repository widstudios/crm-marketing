# Troubleshooting

> Sintomo, causa probabile, verifica, rimedio. Ordinato per area, pensato per la consultazione
> rapida.

---

## Indice

1. [Descrizione](#descrizione)
2. [Avvio e ambiente](#avvio-e-ambiente)
3. [Multitenancy](#multitenancy)
4. [Database e migration](#database-e-migration)
5. [Autenticazione e autorizzazione](#autenticazione-e-autorizzazione)
6. [Code e job](#code-e-job)
7. [Cache](#cache)
8. [File e storage](#file-e-storage)
9. [Filament](#filament)
10. [Prestazioni](#prestazioni)
11. [Deploy](#deploy)
12. [Esempi](#esempi)
13. [Best practice](#best-practice)
14. [Errori comuni](#errori-comuni)
15. [Checklist](#checklist)
16. [Riferimenti](#riferimenti)

---

## Descrizione

Questo documento raccoglie i problemi che si ripresentano. Una voce entra qui quando lo stesso
problema è stato diagnosticato **due volte**: la terza deve costare cinque minuti, non due ore.

---

## Avvio e ambiente

| Sintomo | Causa probabile | Verifica | Rimedio |
|---|---|---|---|
| `docker compose up` fallisce | porta occupata | `docker compose ps`, `lsof -i :8080` | cambiare porta o fermare il servizio |
| «No application encryption key» | `APP_KEY` mancante | `php artisan about` | `php artisan key:generate` |
| Modifiche al codice non visibili | configurazione in cache | `php artisan about` | `php artisan optimize:clear` |
| Estensione PHP mancante | immagine non allineata | `php -m` nel container | aggiungere all'immagine, non installare a mano |
| `composer install` fallisce | credenziali del registro privato | `composer config -l` | verificare `auth.json` |
| Asset non caricati | Vite non in esecuzione | `npm run dev` | avviare Vite o eseguire la build |

---

## Multitenancy

| Sintomo | Causa probabile | Verifica | Rimedio |
|---|---|---|---|
| «I dati sono spariti» | contesto tenant sbagliato | `tenant()->id` | verificare il dominio usato |
| «Tenant not found» | dominio non associato | `tenant:list` | associare il dominio nel landlord |
| La landing page dà 404 | dominio centrale non configurato | `config('tenancy.central_domains')` | aggiungere il dominio |
| Query vuote in `tinker` | eseguito senza contesto | — | `tenant:artisan "tinker" --tenant=<nome>` |
| Job scrive nel tenant sbagliato | contesto non ripristinato | trait del job | usare il trait della Foundation |
| Un tenant vede i dati di un altro | **incidente critico** | log, chiavi di cache | bloccare l'accesso, aprire P1 |
| Tenant senza tabelle | migration mai eseguite | `tenants:migrate:status` | `tenants:migrate --tenant=<nome>` |

La penultima riga non è un problema di configurazione: è un incidente di sicurezza, e si tratta
secondo [`docs/05-operations/05-incident-management.md`](../05-operations/05-incident-management.md).

---

## Database e migration

| Sintomo | Causa probabile | Verifica | Rimedio |
|---|---|---|---|
| Migration funziona in locale, fallisce in produzione | differenza SQLite/MySQL | eseguire su MySQL | provare sempre su MySQL |
| «Specified key was too long» | indice su colonna troppo lunga con utf8mb4 | definizione dell'indice | limitare la lunghezza o accorciare la colonna |
| Rollback impossibile | `down()` mancante | file di migration | implementare `down()` |
| Migration lenta su molti tenant | esecuzione simultanea | durata per tenant | `--chunk` |
| «Too many connections» | pool insufficiente | `SHOW PROCESSLIST` | aumentare `max_connections` o ridurre il pool |
| Chiave esterna che fallisce | ordine delle migration | timestamp dei file | correggere l'ordine |
| Migration fallita a metà su un tenant | dati non conformi | log della migration | correggere i dati, riallineare |

---

## Autenticazione e autorizzazione

| Sintomo | Causa probabile | Verifica | Rimedio |
|---|---|---|---|
| Login non funziona su un dominio | guardia errata | configurazione del pannello | verificare `authGuard` |
| «This action is unauthorized» inatteso | permesso non seminato | `$user->getAllPermissions()` | eseguire il seeder dei permessi |
| Funzionalità invisibile dopo il deploy | permesso non assegnato al ruolo | ruoli del tenant | aggiornare il seeder |
| Sessione persa continuamente | sessioni su file con più container | `SESSION_DRIVER` | `redis` |
| Token API non valido | scaduto o revocato | tabella dei token | rigenerare |
| Policy non applicata | non registrata | `Gate::policies()` | registrare la Policy |

---

## Code e job

| Sintomo | Causa probabile | Verifica | Rimedio |
|---|---|---|---|
| Job non eseguiti | worker fermo | `horizon:status` | avviare i worker |
| Job eseguono codice vecchio | `queue:restart` dimenticato | data di avvio del worker | `php artisan queue:restart` |
| Job fallisce con «model not found» | eseguito prima del commit | posizione del dispatch | `->afterCommit()` |
| Job ripetuto all'infinito | nessun limite di tentativi | proprietà `$tries` | impostare `tries` e `backoff` |
| Coda in accumulo | worker insufficienti o job lenti | metriche Horizon | aumentare i worker o ottimizzare |
| Job duplicati | evento emesso più volte | log degli eventi | `ShouldBeUnique` |
| Job senza contesto tenant | trait mancante | codice del job | trait della Foundation |

---

## Cache

| Sintomo | Causa probabile | Verifica | Rimedio |
|---|---|---|---|
| Dati obsoleti mostrati | cache non invalidata | chiave e TTL | invalidazione per evento |
| Un tenant vede dati di un altro | chiave senza prefisso | ispezione delle chiavi Redis | `TenantCacheKey::for()` |
| Configurazione modificata senza effetto | `config:cache` attivo | `php artisan about` | `config:clear` |
| Redis pieno | nessuna scadenza sulle chiavi | `INFO memory` | TTL obbligatorio |
| Cache non funziona nei test | store `array` | `phpunit.xml` | comportamento atteso |

---

## File e storage

| Sintomo | Causa probabile | Verifica | Rimedio |
|---|---|---|---|
| File caricato non trovato | disco non riconfigurato | `Storage::disk('tenant')->path('')` | verificare il bootstrapper |
| File accessibile senza autorizzazione | salvato in `public/` | percorso del file | disco privato + controller |
| Upload fallisce su file grandi | limiti di PHP o del web server | `upload_max_filesize`, `client_max_body_size` | allineare i limiti |
| File di un tenant visibile a un altro | percorso condiviso | struttura dello storage | disco per tenant |

---

## Filament

| Sintomo | Causa probabile | Verifica | Rimedio |
|---|---|---|---|
| Resource non compare nel menu | Policy `viewAny` nega | permessi dell'utente | assegnare il permesso |
| Elenco lento | N+1 sulle colonne di relazione | Telescope | `with()` in `getEloquentQuery()` |
| Azione visibile a chi non dovrebbe | `authorize()` mancante | definizione dell'azione | aggiungere `->authorize()` |
| Widget con dati di un altro tenant | chiave di cache senza prefisso | chiave usata | `TenantCacheKey::for()` |
| Modifiche non visibili | cache delle viste | — | `view:clear` |
| Filtro molto lento | colonna non indicizzata | `EXPLAIN` | aggiungere l'indice |

---

## Prestazioni

| Sintomo | Causa probabile | Verifica | Rimedio |
|---|---|---|---|
| Pagina lenta con molti dati | N+1 | conteggio query | eager loading |
| Query lenta | indice mancante | `EXPLAIN` | aggiungere l'indice |
| Memoria esaurita | collezione intera in memoria | dimensione del risultato | paginazione o `chunkById` |
| Dashboard lenta | widget non cacheati | Telescope | cache tenant-scoped |
| Lentezza solo su un tenant | volume di dati maggiore | conteggi per tenant | ottimizzare per quel caso |
| Peggioramento dopo un rilascio | regressione | confronto delle metriche | rollback e diagnosi |

---

## Deploy

| Sintomo | Causa probabile | Verifica | Rimedio |
|---|---|---|---|
| 500 dopo il deploy | cache di configurazione non ricostruita | log | `optimize:clear && optimize` |
| Comportamento vecchio nei job | worker non riavviati | data di avvio | `queue:restart` |
| Asset non trovati | build non eseguita | contenuto di `public/build` | `npm run build` nell'immagine |
| Alcuni tenant funzionano, altri no | migration non allineate | `tenants:migrate:status` | riallineare |
| Rollback non ripristina | migration distruttiva applicata | storia delle migration | ripristino da backup |

---

## Esempi

### Diagnosi condotta bene

> *«Un cliente segnala che la dashboard mostra numeri che non sono i suoi.»*

1. **Riprodurre**: aprire la dashboard di due tenant in sequenza. I numeri del secondo coincidono
   con quelli del primo.
2. **Restringere**: il difetto riguarda solo i widget, non gli elenchi. Gli elenchi leggono dal
   database, i widget dalla cache.
3. **Individuare**: `grep -rn "Cache::" app/Filament/Widgets/` mostra una chiave scritta a mano.
4. **Correggere**: `TenantCacheKey::for(...)`.
5. **Impedire il ritorno**: test di isolamento della cache, più un test di architettura che vieta
   `Cache::` fuori dai `Concerns`.

Il quinto passo è quello che distingue una correzione da una diagnosi finita. Senza, lo stesso
difetto ricompare in un altro widget fra sei mesi.

### Diagnosi condotta male

> *«Ho svuotato la cache e adesso funziona.»*

Il sintomo è sparito e la causa è intatta: tornerà al prossimo popolamento della cache, e nel
frattempo dei dati di un cliente sono stati mostrati a un altro senza che nessuno lo registri.

---

## Best practice

- Verificare **sempre per primo** il contesto tenant: è la causa più frequente di diagnosi errate.
- Aggiungere una voce qui alla seconda occorrenza dello stesso problema.
- Includere la **verifica**, non solo il rimedio: senza, si applica una soluzione a un problema
  diverso.
- Collegare i problemi complessi al runbook corrispondente.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Applicare un rimedio senza verificare la causa | Il problema resta e se ne aggiunge un altro | Eseguire la verifica |
| Non aggiornare questo documento | Ogni diagnosi si ripaga da zero | Aggiungere alla seconda occorrenza |
| Trattare una fuga tra tenant come problema tecnico | Incidente di sicurezza non gestito | P1 immediato |
| Cercare la causa nel codice prima di verificare l'ambiente | Ore perse | Verificare prima ambiente e contesto |

---

## Checklist

- [ ] Ho verificato il contesto tenant.
- [ ] Ho verificato l'ambiente (`php artisan about`).
- [ ] Ho eseguito la verifica indicata prima di applicare il rimedio.
- [ ] Se il problema era nuovo, l'ho aggiunto a questo documento.
- [ ] Se riguardava l'isolamento tra tenant, ho aperto un incidente P1.

---

## Riferimenti

- [Guida al debug](../03-development/07-debugging-guide.md)
- [Riferimento comandi](03-command-reference.md) · [Configurazione](04-configuration-reference.md)
- [Gestione degli incidenti](../05-operations/05-incident-management.md)
- [Runbook operativi](../../deployment/runbooks/README.md)
