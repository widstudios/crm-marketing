# Regole — Code e job

> Contesto tenant obbligatorio, idempotenza, limiti espliciti, code separate per priorità.

---

## Indice

1. [Descrizione](#descrizione)
2. [Cosa va in coda](#cosa-va-in-coda)
3. [Regole dei job](#regole-dei-job)
4. [Regole delle code](#regole-delle-code)
5. [Regole dello scheduler](#regole-dello-scheduler)
6. [Fallimenti](#fallimenti)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Le code servono a non far attendere l'utente e a isolare i fallimenti delle dipendenze esterne. In
un contesto multitenant aggiungono un requisito che altrove non esiste: ogni job deve sapere a
quale cliente appartiene.

Un job senza contesto tenant scrive nel database sbagliato, o fallisce in modo apparentemente
casuale.

---

## Cosa va in coda

**R1.** Va in coda ogni operazione che supera i **200 ms** o dipende da un sistema esterno.

| In coda | Non in coda |
|---|---|
| invio di posta e notifiche | validazione dell'input |
| generazione di documenti | autorizzazione |
| chiamate a servizi esterni | letture per la risposta corrente |
| ricalcolo di aggregati | operazioni che l'utente attende come esito |
| importazioni ed esportazioni | |
| provisioning di tenant | |
| backup | |

*Verifica:* revisione, metriche sui tempi di risposta.

---

## Regole dei job

**R2.** Ogni job usa il trait `TenantAware`, che serializza e ripristina il contesto.
*Motivo:* senza, il job gira nel contesto landlord. *Verifica:* test di architettura.
*Livello: assoluto.*

**R3.** Il costruttore riceve **identificatori e scalari**, mai model.
*Motivo:* `SerializesModels` rilegge il model, ma il payload resta grande e la relazione può essere
sparita. *Verifica:* revisione.

**R4.** Ogni job dichiara `$tries`, `$backoff` e `$timeout`.

```php
public int $tries = 3;
public array $backoff = [30, 120, 300];
public int $timeout = 120;
```

*Motivo:* un job senza limiti che fallisce può ripartire indefinitamente e saturare i worker.
*Verifica:* test di architettura. *Livello: vincolante.*

**R5.** Ogni job è **idempotente**: eseguirlo più volte produce lo stesso risultato.

```php
// ✗ Non idempotente: il ritentativo raddoppia
$batch->increment('quantity', $this->quantity);

// ✓ Idempotente: ricalcolo dalla sorgente
$batch->update(['quantity' => $batch->movements()->sum('signed_quantity')]);
```

*Verifica:* test che esegue il job due volte. *Livello: vincolante.*

**R6.** I job emessi da un'Action usano `->afterCommit()`.
*Motivo:* altrimenti possono partire prima del commit e non trovare i dati.
*Verifica:* revisione, test.

**R7.** Il job implementa `failed()` e registra il contesto, tenant incluso.
*Verifica:* revisione.

**R8.** I job che non devono duplicarsi implementano `ShouldBeUnique` con una chiave esplicita.
*Verifica:* revisione.

**R9.** Nessuna logica di business nel job: delega a un'Action o a un Service.
*Motivo:* la logica nel job non è invocabile in modo sincrono né testabile in isolamento.
*Verifica:* revisione.

**R10.** I job che elaborano molti record procedono a blocchi (`chunkById`) o si suddividono in job
figli.
*Motivo:* memoria costante, ripresa possibile. *Verifica:* revisione.

---

## Regole delle code

**R11.** Le code sono separate per priorità e tipo di lavoro.

| Coda | Priorità | Contenuto |
|---|---|---|
| `high` | 1 | operazioni che l'utente attende |
| `provisioning` | 1 | creazione di tenant |
| `default` | 2 | lavoro ordinario |
| `notifications` | 2 | posta e notifiche |
| `documents` | 3 | generazione di documenti |
| `integrations` | 3 | chiamate esterne |
| `bulk` | 4 | importazioni, esportazioni, ricalcoli massivi |

*Motivo:* un'importazione da 100.000 righe non deve ritardare una notifica attesa.
*Verifica:* revisione della configurazione.

**R12.** Ogni job dichiara la propria coda, esplicitamente o tramite `onQueue()`.
*Verifica:* revisione.

**R13.** Il driver è Redis in tutti gli ambienti tranne i test (`sync`).
*Verifica:* configurazione.

**R14.** I worker usano la **stessa immagine** dell'applicazione web.
*Motivo:* una divergenza produce difetti non riproducibili. *Verifica:* configurazione Docker.

**R15.** `queue:restart` è obbligatorio nella sequenza di deploy.
*Motivo:* i worker mantengono in memoria il codice caricato all'avvio.
*Verifica:* script di deploy. *Livello: vincolante.*

---

## Regole dello scheduler

**R16.** I comandi che operano sui tenant girano tramite `tenants:artisan`, non direttamente.
*Motivo:* un comando schedulato senza contesto gira sul landlord e non fa nulla di utile.
*Verifica:* revisione di `routes/console.php`. *Livello: vincolante.*

**R17.** Ogni comando schedulato multi-tenant usa `withoutOverlapping()`.
*Motivo:* l'esecuzione su N tenant può superare l'intervallo di pianificazione.
*Verifica:* revisione.

**R18.** Con più server, `onOneServer()` è obbligatorio.
*Verifica:* revisione.

**R19.** I comandi lunghi usano `runInBackground()`.
*Verifica:* revisione.

**R20.** Solo backup e monitoraggio usano `evenInMaintenanceMode()`.
*Verifica:* revisione.

---

## Fallimenti

```php
public function failed(Throwable $exception): void
{
    Log::error('Ricalcolo giacenza fallito', [
        'batch_id' => $this->batchId,
        'tenant' => $this->tenantSlug,
        'exception' => $exception->getMessage(),
    ]);
}
```

| Aspetto | Regola |
|---|---|
| Coda dei falliti | attiva, con conservazione di 7 giorni |
| Allarme | oltre 50 job falliti in un'ora |
| Riprova manuale | possibile dal pannello Super Admin |
| Notifica | solo se il fallimento ha impatto operativo |
| Registro | sempre, con tenant e contesto |

---

## Esempi

### Esempio 1 — job conforme

```php
final class RecalculateStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TenantAware;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $timeout = 120;

    public function __construct(public int $batchId) {}

    public function handle(RecalculateStockAction $action): void
    {
        $batch = Batch::find($this->batchId);

        if ($batch === null) {
            return;
        }

        $action->execute($batch);   // R9: logica nell'Action
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Ricalcolo fallito', [
            'batch_id' => $this->batchId,
            'tenant' => $this->tenantSlug,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

### Esempio 2 — scheduler conforme

```php
Schedule::command('tenants:artisan "stock:recalculate"')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('tenants:backup')
    ->dailyAt('01:00')
    ->onOneServer()
    ->evenInMaintenanceMode();
```

### Esempio 3 — violazioni

```php
class SyncJob implements ShouldQueue        // ✗ R2: nessun TenantAware
{
    public function __construct(public Batch $batch) {}   // ✗ R3: model nel costruttore

    public function handle(): void
    {
        // ✗ R9: logica nel job, ✗ R5: non idempotente
        $this->batch->increment('quantity', 10);
        Http::post('https://erp.example.com/sync', $this->batch->toArray());
    }
}
// ✗ R4: nessun tries, backoff, timeout · ✗ R7: nessun failed()
```

---

## Best practice

- Trattare l'idempotenza come requisito, non come accorgimento.
- Ricalcolare dalla sorgente invece di incrementare.
- Suddividere i job massivi in job figli, per avere avanzamento e ripresa.
- Verificare in locale con `queue:work` in primo piano.
- Monitorare attesa, durata e fallimenti per coda.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Job senza `TenantAware` | Scrittura nel database sbagliato | Trait obbligatorio |
| Job accodato in transazione | Fallimenti intermittenti | `afterCommit()` |
| Job non idempotente | Dati errati sui ritentativi | Ricalcolo dalla sorgente |
| Nessun `timeout` | Worker bloccato indefinitamente | Limiti espliciti |
| Model nel costruttore | Payload grande, relazioni sparite | Identificatori |
| Logica nel job | Non testabile né invocabile sincrona | Delegare all'Action |
| Una coda per tutto | Le importazioni bloccano le notifiche | Code separate |
| `queue:restart` dimenticato | I worker eseguono il codice vecchio | Nella sequenza di deploy |
| Comando schedulato senza contesto tenant | Non fa nulla | `tenants:artisan` |
| Nessun `withoutOverlapping()` | Esecuzioni sovrapposte, dati incoerenti | Sempre presente |

---

## Checklist

- [ ] Ogni job usa `TenantAware`.
- [ ] Il costruttore riceve identificatori, non model.
- [ ] `$tries`, `$backoff`, `$timeout` dichiarati.
- [ ] Il job è idempotente, verificato da un test che lo esegue due volte.
- [ ] I job emessi da Action usano `afterCommit()`.
- [ ] `failed()` registra il contesto con il tenant.
- [ ] Nessuna logica di business nel job.
- [ ] Coda dichiarata, separata per priorità.
- [ ] Worker sulla stessa immagine dell'applicazione.
- [ ] `queue:restart` nella sequenza di deploy.
- [ ] Comandi schedulati con contesto tenant e `withoutOverlapping()`.

---

## Riferimenti

- [Code e scheduler](../architecture/18-queue-scheduler.md)
- [Events](events.md) · [Performance](performance.md) · [Logging](logging.md)
- [Template Job](../templates/infrastructure/README.md)
