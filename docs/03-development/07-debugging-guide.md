# Guida al debug

> Come si diagnostica un problema in locale, in staging e in produzione, con l'attenzione
> specifica che richiede un'applicazione multitenant.

---

## Indice

1. [Descrizione](#descrizione)
2. [Metodo](#metodo)
3. [Strumenti per ambiente](#strumenti-per-ambiente)
4. [Debug in locale](#debug-in-locale)
5. [Debug in staging](#debug-in-staging)
6. [Debug in produzione](#debug-in-produzione)
7. [Problemi tipici della multitenancy](#problemi-tipici-della-multitenancy)
8. [Problemi tipici delle code](#problemi-tipici-delle-code)
9. [Problemi di prestazioni](#problemi-di-prestazioni)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Il debug efficace non è una questione di strumenti ma di **metodo**: la maggior parte del tempo
perso deriva dal cambiare più cose insieme e dal non riuscire a riprodurre il problema.

In un'applicazione multitenant si aggiunge una domanda che altrove non esiste: *in quale tenant
stiamo guardando?* Molte diagnosi sbagliate nascono dall'osservare il tenant sbagliato.

---

## Metodo

1. **Riprodurre.** Un problema non riproducibile non è diagnosticabile: si lavora prima sulla
   riproduzione.
2. **Delimitare.** Quale livello? Ingresso, applicazione, dominio, persistenza, infrastruttura.
3. **Osservare.** Log, query, contesto — senza modificare nulla.
4. **Formulare un'ipotesi** falsificabile: «se è questo, allora anche X dovrebbe accadere».
5. **Verificare**, cambiando **una** cosa per volta.
6. **Correggere** e scrivere il test di regressione **prima** della correzione.
7. **Verificare** che la correzione risolva davvero, non solo che il sintomo sparisca.

Il passo 6 non è formale: il test scritto prima dimostra di aver capito il problema. Se non si
riesce a scriverlo, la diagnosi non è completa.

---

## Strumenti per ambiente

| Strumento | Locale | Staging | Produzione |
|---|---|---|---|
| Telescope | sì | sì | **no** |
| Pulse | sì | sì | sì |
| Horizon | sì | sì | sì |
| Log applicativi | sì | sì | sì |
| `dd()`, `dump()` | sì | no | **mai** |
| Xdebug | sì | no | no |
| Query log | sì | temporaneo | temporaneo, mirato |
| Tinker | sì | sì (lettura) | solo con autorizzazione |

Telescope in produzione registra corpi di richiesta e risultati di query: significa dati personali
in un archivio non previsto dal trattamento, oltre al disco che si riempie.

---

## Debug in locale

### Contare le query

```php
DB::listen(function (QueryExecuted $query): void {
    logger()->debug($query->sql, ['bindings' => $query->bindings, 'time' => $query->time]);
});
```

Oppure, per un blocco specifico:

```php
DB::enableQueryLog();
$suppliers = Supplier::with('movements')->get();
dump(count(DB::getQueryLog()));
```

Se il numero di query cresce con il numero di righe, è un N+1.

### Lavorare nel contesto di un tenant

```bash
php artisan tenant:artisan "tinker" --tenant=acme
```

Tutto ciò che si esegue dentro questo contesto vede il database di `acme`. Aprire `tinker` senza
contesto e interrogare i model produce risultati che sembrano vuoti «senza motivo».

### Isolare il livello

| Sintomo | Isolare con |
|---|---|
| Risposta sbagliata da API | test di feature sull'endpoint |
| Calcolo sbagliato | test unitario sull'Action |
| Dato sbagliato nel database | query diretta nel contesto tenant |
| Interfaccia che non aggiorna | verificare la richiesta Livewire nel browser |
| Job che non gira | `queue:work` in primo piano |

---

## Debug in staging

Staging ha **dati realistici e configurazione di produzione**: è il posto giusto per i problemi
che in locale non si riproducono.

```bash
# Log in tempo reale
docker compose logs -f app

# Contesto tenant, sola lettura
php artisan tenant:artisan "tinker" --tenant=acme

# Stato delle code
php artisan horizon:status
php artisan queue:failed
```

Regole per staging:

- Nessuna modifica manuale ai dati: si perde la capacità di riprodurre.
- Ogni intervento viene annotato.
- I dati sono anonimizzati se provengono dalla produzione.

---

## Debug in produzione

Il principio: **osservare, non toccare**.

| Ammesso | Vietato |
|---|---|
| Leggere i log | `dd()`, `dump()` |
| Consultare Pulse e Horizon | modificare configurazione al volo |
| Query di sola lettura, autorizzate | modificare dati senza procedura |
| Abilitare temporaneamente il log delle query su un tenant | abilitare Telescope |
| Aumentare temporaneamente il livello di log | disabilitare la cache |

Sequenza corretta:

1. Raccogliere le prove: log, metriche, identificatore della richiesta, tenant, momento.
2. Riprodurre in staging con dati equivalenti.
3. Correggere, con test di regressione.
4. Rilasciare secondo la procedura, anche se urgente.

Se il problema è un'interruzione di servizio, si applica prima la mitigazione (rollback,
disabilitazione della funzionalità) e poi la diagnosi. Vedi
[gestione degli incidenti](../05-operations/05-incident-management.md).

### Correlazione delle richieste

Ogni richiesta porta un identificatore che compare in tutti i log correlati:

```php
Log::info('Movement registered', [
    'request_id' => request()->header('X-Request-Id'),
    'tenant' => tenant()->id,
    'movement_id' => $movement->id,
]);
```

Senza `tenant` nel contesto di log, un errore in produzione non è attribuibile a nessuno: è la
prima cosa da verificare quando si imposta un progetto.

---

## Problemi tipici della multitenancy

| Sintomo | Causa probabile | Verifica |
|---|---|---|
| «I dati sono spariti» | si sta guardando il tenant sbagliato | `tenant()->id` nel contesto |
| Job che scrive nel tenant sbagliato | contesto non ripristinato nel job | trait della Foundation nel job |
| Cache che mostra dati di un altro | chiave senza prefisso tenant | ispezionare le chiavi in Redis |
| File caricato non trovato | disco non riconfigurato sul tenant | configurazione dello storage |
| Migration mancante su un tenant | migration fallita e non riallineata | `tenants:migrate:status` |
| Login che non funziona su un dominio | dominio non associato al tenant | tabella dei domini nel landlord |
| Comando che non trova nulla | eseguito senza contesto tenant | `tenant:artisan` |

Il primo sintomo dell'elenco è il più frequente in assoluto, e la verifica è di dieci secondi.

---

## Problemi tipici delle code

| Sintomo | Causa probabile | Verifica |
|---|---|---|
| Job che non parte | worker fermo o coda sbagliata | `horizon:status`, nome della coda |
| Job ripetuto all'infinito | eccezione non gestita, ripetizioni infinite | `tries` e `backoff` |
| Job che fallisce solo in produzione | contesto tenant o configurazione | log del job fallito |
| Coda che si accumula | worker insufficienti o job lenti | metriche Horizon |
| Job duplicati | evento emesso più volte | `ShouldBeUnique` |
| Dati incoerenti dopo un job | job eseguito prima del commit della transazione | `afterCommit()` |

L'ultimo caso è insidioso: il job parte, legge il dato, non lo trova perché la transazione non è
ancora conclusa, e fallisce in modo apparentemente casuale.

---

## Problemi di prestazioni

Ordine di indagine, dal più probabile al meno:

1. **N+1**: query che si moltiplicano per riga. → `with()`.
2. **Indice mancante**: query lenta su tabella grande. → `EXPLAIN`.
3. **Dati non paginati**: collezione intera in memoria. → paginazione.
4. **Widget non cacheati**: aggregati ricalcolati ad ogni caricamento. → cache.
5. **Operazioni sincrone lente**: invio mail, PDF, chiamate esterne. → coda.
6. **Cache non usata o invalidata troppo spesso**. → strategia di invalidazione.

```sql
EXPLAIN SELECT * FROM stock_movements
WHERE batch_id = 42 ORDER BY occurred_at DESC LIMIT 25;
```

`type: ALL` significa scansione completa: manca un indice.

---

## Esempi

### Esempio 1 — «i dati sono spariti»

Segnalazione: dopo l'aggiornamento, l'elenco dei fornitori è vuoto.

Verifica in dieci secondi: `tenant()->id` nel contesto della richiesta. Risultato: il dominio
usato per accedere non è associato al tenant atteso, quindi si sta guardando un tenant nuovo e
vuoto. Nessun dato perso.

Correzione: associazione del dominio nel landlord. Test di regressione: la risoluzione del tenant
per quel dominio.

### Esempio 2 — job che fallisce a intermittenza

`RecalculateStockJob` fallisce circa una volta su venti con «movimento non trovato».

Ipotesi: il job parte prima che la transazione sia conclusa. Verifica: l'evento è emesso dentro
`DB::transaction()`.

Correzione: `->afterCommit()` sul dispatch, oppure emissione dell'evento dopo la transazione.

---

## Best practice

- Riprodurre prima di correggere, sempre.
- Cambiare una cosa per volta.
- Scrivere il test di regressione prima della correzione.
- Includere `tenant` e `request_id` in ogni log applicativo.
- In produzione osservare, non toccare.
- Annotare gli interventi su staging e produzione.
- Al terzo problema simile, correggere la causa comune, non i sintomi.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Correggere senza riprodurre | Si corregge il sintomo, il problema resta | Riproduzione obbligatoria |
| Cambiare più cose insieme | Non si sa cosa ha funzionato | Una variabile per volta |
| `dd()` in staging o produzione | Interruzione del servizio, dati esposti | Solo in locale |
| Telescope in produzione | Dati personali registrati, disco pieno | Disabilitato |
| Diagnosticare nel tenant sbagliato | Conclusioni errate | Verificare il contesto per primo |
| Log senza tenant | Errori non attribuibili | Contesto obbligatorio nei log |
| Modificare dati in produzione senza procedura | Danno non tracciato | Procedura e autorizzazione |
| Nessun test di regressione | Il difetto torna | Test prima della correzione |

---

## Checklist

- [ ] Il problema è riproducibile.
- [ ] Ho verificato in quale tenant sto guardando.
- [ ] Ho isolato il livello coinvolto.
- [ ] Ho formulato un'ipotesi falsificabile.
- [ ] Ho cambiato una cosa per volta.
- [ ] Ho scritto il test di regressione prima della correzione.
- [ ] Non ho lasciato codice di debug.
- [ ] Ho annotato gli interventi su ambienti condivisi.

---

## Riferimenti

- [Monitoraggio e log](../05-operations/03-monitoring-and-logging.md)
- [Gestione degli incidenti](../05-operations/05-incident-management.md)
- [Guida alle prestazioni](../04-quality/04-performance-guide.md)
- [Regole di logging](../../rules/logging.md) · [Queue](../../rules/queue.md)
- [Troubleshooting](../06-reference/05-troubleshooting.md)
