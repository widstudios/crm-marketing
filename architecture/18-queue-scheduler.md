# Code e scheduler

> Lavoro asincrono e ricorrente in un'applicazione multitenant: cosa va in coda, come si mantiene
> il contesto, come si pianifica su N clienti.

---

## Indice

1. [Descrizione](#descrizione)
2. [Cosa va in coda](#cosa-va-in-coda)
3. [Le code](#le-code)
4. [Contesto tenant nei job](#contesto-tenant-nei-job)
5. [Affidabilità dei job](#affidabilità-dei-job)
6. [Scheduler](#scheduler)
7. [Supervisione](#supervisione)
8. [Dimensionamento](#dimensionamento)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Le code servono a due cose: **non far attendere l'utente** per operazioni lente, e **isolare i
fallimenti** delle dipendenze esterne.

In un contesto multitenant si aggiunge un requisito che altrove non esiste: ogni job deve sapere a
quale cliente appartiene e ripristinarne il contesto prima di eseguire. Un job senza contesto
scrive nel database sbagliato.

---

## Cosa va in coda

| Operazione | In coda | Motivo |
|---|---|---|
| Invio di posta e notifiche | sì | dipendenza esterna, lenta |
| Generazione di documenti | sì | uso intensivo di CPU |
| Chiamate a servizi esterni | sì | latenza e guasti non controllabili |
| Ricalcolo di aggregati | sì | può interessare molte righe |
| Importazioni ed esportazioni | sì | volumi imprevedibili |
| Scrittura dell'audit | dipende | sincrona se il volume è basso |
| Provisioning di un tenant | sì | dura minuti |
| Backup | sì | dura a lungo |
| Validazione di un input | **no** | l'utente attende la risposta |
| Autorizzazione | **no** | deve essere immediata |

Criterio: se l'operazione supera i **200 ms** o dipende da un sistema esterno, va in coda.

---

## Le code

| Coda | Priorità | Contenuto | Worker |
|---|---|---|---|
| `high` | 1 | operazioni che l'utente attende | 4 |
| `default` | 2 | lavoro ordinario | 4 |
| `notifications` | 2 | posta e notifiche | 2 |
| `documents` | 3 | generazione di documenti | 2 |
| `integrations` | 3 | chiamate esterne | 2 |
| `bulk` | 4 | importazioni, esportazioni, ricalcoli massivi | 1 |
| `provisioning` | 1 | creazione di tenant | 1 |

La separazione per priorità evita il problema classico: un'importazione da 100.000 righe che
ritarda la notifica che un utente sta aspettando.

---

## Contesto tenant nei job

```php
final class RecalculateStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TenantAware;   // serializza e ripristina il tenant

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(public int $batchId) {}

    public function handle(): void
    {
        // Il contesto è già ripristinato dal trait.
        Batch::findOrFail($this->batchId)->recalculate();
    }
}
```

Come funziona il trait:

```
dispatch()  ──▶ registra lo slug del tenant corrente nel payload
                            │
                            ▼
worker preleva il job ──▶ risolve il tenant dallo slug
                            │
                            ▼
                    bootstrap dei servizi (database, cache, storage)
                            │
                            ▼
                        handle()
                            │
                            ▼
                    ripristino del contesto precedente
```

| Regola | Motivo |
|---|---|
| Ogni job usa `TenantAware` | senza, gira nel contesto landlord |
| Il job trasporta identificatori, non model | i model serializzati portano dati obsoleti |
| Il contesto si ripristina anche in caso di eccezione | evita di contaminare il job successivo |
| Un job non cambia tenant a metà | come per le richieste HTTP |

---

## Affidabilità dei job

| Proprietà | Valore tipico | Effetto |
|---|---|---|
| `tries` | 3 | tentativi prima del fallimento definitivo |
| `backoff` | `[30, 120, 300]` | attesa crescente tra i tentativi |
| `timeout` | 120 s | oltre, il job viene interrotto |
| `maxExceptions` | 2 | fallimento anticipato su errori ripetuti |
| `ShouldBeUnique` | dove applicabile | evita duplicati |
| `afterCommit` | sempre per i job emessi da un'Action | il job non parte prima del commit |

```php
public function failed(Throwable $exception): void
{
    Log::error('Ricalcolo giacenza fallito', [
        'batch_id' => $this->batchId,
        'tenant' => $this->tenantSlug,
        'exception' => $exception->getMessage(),
    ]);

    // Notifica solo se il fallimento ha impatto operativo.
    Notification::route('mail', config('monitoring.alert_email'))
        ->notify(new JobFailedNotification(static::class, $this->batchId));
}
```

**Idempotenza**: un job può essere eseguito più volte (ritentativi, duplicati). Deve produrre lo
stesso risultato. Un job che incrementa un contatore senza controllo produce dati errati al primo
ritentativo.

---

## Scheduler

```php
// routes/console.php

// ✗ Gira nel contesto landlord: non fa nulla di utile
Schedule::command('stock:recalculate')->daily();

// ✓ Gira su ogni tenant
Schedule::command('tenants:artisan "stock:recalculate"')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('tenants:artisan "batches:check-expiry"')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('tenants:backup')
    ->dailyAt('01:00')
    ->onOneServer();

Schedule::command('tenants:collect-metrics')
    ->hourly()
    ->withoutOverlapping();
```

| Opzione | Quando | Perché |
|---|---|---|
| `withoutOverlapping()` | **sempre** sui comandi multi-tenant | l'esecuzione può superare l'intervallo |
| `onOneServer()` | sempre con più server | evita esecuzioni duplicate |
| `runInBackground()` | comandi lunghi | non blocca gli altri |
| `evenInMaintenanceMode()` | solo per backup e monitoraggio | il resto attende |

Un comando che gira su cinquanta tenant può durare più di un'ora: senza `withoutOverlapping()`,
due esecuzioni sovrapposte producono dati incoerenti.

---

## Supervisione

Horizon, con code separate e metriche per coda.

| Metrica | Soglia di allarme |
|---|---|
| Job in attesa | > 1.000 |
| Tempo di attesa | > 5 minuti |
| Job falliti in un'ora | > 50 |
| Durata media | > 60 s |
| Worker attivi | < atteso |

Il pannello Horizon vive nel Super Admin, con accesso riservato: mostra il contenuto dei payload,
che può contenere identificatori di più tenant.

---

## Dimensionamento

```
worker necessari ≈ (job al minuto × durata media in secondi) / 60
```

| Situazione | Intervento |
|---|---|
| Coda che si accumula stabilmente | aumentare i worker |
| Job lenti | ottimizzare, o spostarli in `bulk` |
| Picchi periodici | scalatura automatica in base alla lunghezza della coda |
| Un tenant che occupa la coda | limitare per tenant, o coda dedicata |

L'ultima riga è specifica del multitenant: un cliente che avvia un'importazione enorme non deve
bloccare il lavoro degli altri.

---

## Esempi

### Esempio 1 — il difetto più comune

```php
// ✗ Il job parte prima che la transazione sia conclusa: talvolta non trova la riga.
DB::transaction(function (): void {
    $movement = StockMovement::create([...]);
    RecalculateStockJob::dispatch($movement->batch_id);
});

// ✓ Il job parte dopo il commit
DB::transaction(function (): void {
    $movement = StockMovement::create([...]);
    RecalculateStockJob::dispatch($movement->batch_id)->afterCommit();
});
```

Il difetto si manifesta in modo intermittente — una volta su venti — ed è per questo difficile da
diagnosticare senza conoscerne la causa.

### Esempio 2 — job idempotente

```php
public function handle(): void
{
    $batch = Batch::findOrFail($this->batchId);

    // Ricalcolo completo dalla sorgente: eseguirlo due volte dà lo stesso risultato.
    $batch->update([
        'quantity' => $batch->movements()->sum(DB::raw('quantity * CASE type
            WHEN "inbound" THEN 1 WHEN "outbound" THEN -1 ELSE 0 END')),
    ]);
}
```

---

## Best practice

- Tutto ciò che supera 200 ms o dipende da un servizio esterno va in coda.
- Ogni job usa `TenantAware`.
- I job trasportano identificatori, non model.
- `afterCommit()` per i job emessi da un'Action.
- Job idempotenti.
- `withoutOverlapping()` su tutti i comandi multi-tenant.
- Code separate per priorità.
- Allarmi su attese e fallimenti.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Job senza `TenantAware` | Esecuzione nel database sbagliato | Trait obbligatorio |
| Job accodato dentro la transazione | Fallimenti intermittenti | `afterCommit()` |
| Job non idempotente | Dati errati al primo ritentativo | Ricalcolo dalla sorgente |
| Model serializzato nel job | Dati obsoleti | Identificatori |
| Nessun `timeout` | Worker bloccato indefinitamente | Timeout esplicito |
| Comando schedulato senza contesto tenant | Non fa nulla di utile | `tenants:artisan` |
| Nessun `withoutOverlapping()` | Esecuzioni sovrapposte, dati incoerenti | Sempre presente |
| Una coda per tutto | Le importazioni bloccano le notifiche | Code separate |

---

## Checklist

- [ ] Ogni job usa `TenantAware`.
- [ ] I job trasportano identificatori, non model.
- [ ] `tries`, `backoff` e `timeout` sono definiti.
- [ ] I job emessi da Action usano `afterCommit()`.
- [ ] I job sono idempotenti.
- [ ] `failed()` registra il contesto, tenant incluso.
- [ ] I comandi schedulati girano nel contesto corretto.
- [ ] `withoutOverlapping()` e `onOneServer()` presenti.
- [ ] Code separate per priorità, con allarmi.

---

## Riferimenti

- [Regole Queue](../rules/queue.md) · [Events](../rules/events.md)
- [Risoluzione del tenant](06-tenant-resolution.md)
- [Osservabilità](25-observability.md)
- [Monitoraggio e log](../docs/05-operations/03-monitoring-and-logging.md)
