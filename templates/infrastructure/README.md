# Templates — Infrastruttura

> Gli stub di ciò che sta intorno al codice: job, listener, notifiche, comandi, middleware,
> provider, immagine, ambiente, pipeline, `CLAUDE.md` di progetto.

---

## Indice

1. [Descrizione](#descrizione) 2. [Gli stub](#gli-stub) 3. [Dove vanno i file](#dove-vanno-i-file)
4. [Nota sui segnaposto nei file YAML](#nota-sui-segnaposto-nei-file-yaml)
5. [Esempi](#esempi) 6. [Best practice](#best-practice) 7. [Errori comuni](#errori-comuni)
8. [Checklist](#checklist) 9. [Riferimenti](#riferimenti)

---

## Descrizione

È la cartella più eterogenea, e ha comunque un filo conduttore: raccoglie tutto ciò che viene
eseguito **fuori dalla richiesta dell'utente** — in coda, da uno scheduler, da una pipeline, da un
container.

È anche il contesto in cui i difetti sono più difficili da vedere. Un job che gira nel tenant
sbagliato non produce un errore: produce dati corretti nel posto sbagliato. Un worker su
un'immagine diversa da quella del web funziona finché una dipendenza non diverge. Per questo gli
stub di questa cartella hanno il rapporto più alto tra commenti e codice.

---

## Gli stub

| Stub | Quando | Vincolo che conta |
|---|---|---|
| [Job.php.stub](Job.php.stub) | lavoro asincrono | `TenantAware`, limiti espliciti, idempotenza |
| [Listener.php.stub](Listener.php.stub) | reazione a un evento | una cosa sola, nessun evento a catena |
| [Notification.php.stub](Notification.php.stub) | comunicazione a una persona | il destinatario deve poter agire |
| [Mail.php.stub](Mail.php.stub) | messaggio che deve essere posta | sempre in coda |
| [Command.php.stub](Command.php.stub) | comando Artisan | rifiuta di girare senza contesto tenant |
| [Middleware.php.stub](Middleware.php.stub) | verifica trasversale | una responsabilità sola |
| [ServiceProvider.php.stub](ServiceProvider.php.stub) | registrazione nel container | `register()` dichiara, `boot()` usa |
| [Dockerfile.stub](Dockerfile.stub) | immagine dell'applicazione | multi-stadio, non-root, stessa immagine per i worker |
| [compose.yaml.stub](compose.yaml.stub) | ambiente locale | MySQL e Redis veri, worker vero |
| [ci.yaml.stub](ci.yaml.stub) | pipeline | gate in parallelo, due basi dati, isolamento in job dedicato |
| [env.example.stub](env.example.stub) | variabili d'ambiente | segnaposto evidenti, nessun segreto |
| [project-claude.md.stub](project-claude.md.stub) | `CLAUDE.md` del progetto | glossario del dominio obbligatorio |

---

## Dove vanno i file

```
app/
├── Jobs/{{ Class }}Job.php                     Job.php.stub
├── Listeners/{{ Class }}Listener.php           Listener.php.stub
├── Notifications/{{ Class }}.php               Notification.php.stub
├── Mail/{{ Class }}.php                        Mail.php.stub
├── Console/Commands/{{ Class }}Command.php     Command.php.stub
├── Http/Middleware/{{ Class }}.php             Middleware.php.stub
└── Providers/{{ Class }}ServiceProvider.php    ServiceProvider.php.stub

Dockerfile                                      Dockerfile.stub
compose.yaml                                    compose.yaml.stub
.github/workflows/qa.yml                        ci.yaml.stub
.env.example                                    env.example.stub
CLAUDE.md                                       project-claude.md.stub
```

---

## Nota sui segnaposto nei file YAML

`ci.yaml.stub` contiene sia i segnaposto della Factory sia le espressioni di GitHub Actions, che
usano una sintassi simile. Si distinguono dal prefisso:

| Forma | Che cos'è | Va sostituita? |
|---|---|---|
| `{{ Project }}` | segnaposto della Factory | **sì** |
| `${{ github.workflow }}` | espressione di GitHub Actions | **no**, resta com'è |

Il `$` iniziale è l'unica differenza, ed è sufficiente. Sostituire un'espressione di GitHub Actions
produce una pipeline che non parte, con un messaggio d'errore che non indica la causa.

---

## Esempi

### Il job come dovrebbe essere

```php
final class RecalculateStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TenantAware;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $timeout = 120;
    public string $queue = 'default';

    public function __construct(public int $batchId) {}

    public function handle(RecalculateStockAction $action): void
    {
        $batch = Batch::find($this->batchId);

        if ($batch === null) {
            return;                       // sparito nel frattempo: non è un errore
        }

        $action->execute($batch->getKey());
    }
}
```

### Idempotenza, in due righe

```php
// ✗ Il ritentativo raddoppia la quantità
$batch->increment('quantity', $this->quantity);

// ✓ Ricalcolo dalla sorgente: eseguirlo dieci volte dà lo stesso risultato
$batch->update(['quantity' => $batch->movements()->sum('signed_quantity')]);
```

Il test dell'idempotenza esegue il job **due volte** e verifica lo stesso esito. Senza quel test,
l'idempotenza è dichiarata, non verificata.

---

## Best practice

- Ogni job: `TenantAware`, `tries`, `backoff`, `timeout`, coda dichiarata, `failed()` con il tenant.
- Ricalcolare dalla sorgente invece di incrementare.
- Suddividere i job massivi in job figli: si ottengono avanzamento e ripresa.
- Stessa immagine per web e worker, sempre.
- Etichettare le immagini con il commit, mai con `latest`.
- Far girare in parallelo i gate della pipeline: un ritorno lento non viene letto.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Job senza `TenantAware` | Scrittura nel database sbagliato | Trait obbligatorio |
| `middleware()` sovrascritto | Il ripristino del contesto sparisce | `jobMiddleware()` |
| Job non idempotente | Dati errati sui ritentativi | Ricalcolo dalla sorgente |
| Nessun `timeout` | Worker bloccato indefinitamente | Limiti espliciti |
| Model nel costruttore del job | Payload grande, relazioni sparite | Identificatori |
| Logica nel job | Non invocabile in sincrono né testabile | Delegare all'Action |
| Una coda per tutto | Le importazioni bloccano le notifiche | Code separate |
| Comando senza verifica del contesto | Gira sul landlord | Controllo in `handle()` |
| Servizio usato in `register()` | Errore dipendente dall'ordine di caricamento | Solo in `boot()` |
| Immagine diversa per i worker | Difetti non riproducibili | Stessa immagine |
| Container come root | Un'esecuzione arbitraria diventa compromissione | `USER www-data` |
| `latest` come tag | Non si sa quale codice gira | Tag con il commit |
| Segreto realistico in `.env.example` | Copiato in produzione da chi ha fretta | Segnaposto evidenti |

---

## Checklist

- [ ] Ogni job usa `TenantAware` e dichiara `tries`, `backoff`, `timeout`, coda.
- [ ] Ogni job è idempotente, con un test che lo esegue due volte.
- [ ] `failed()` registra il contesto con lo slug del tenant.
- [ ] I comandi di dominio rifiutano di girare senza contesto tenant.
- [ ] `register()` contiene solo binding; tutto il resto sta in `boot()`.
- [ ] L'immagine è multi-stadio, gira non-root, ed è la stessa per web e worker.
- [ ] La pipeline gira su SQLite e MySQL, con un job dedicato all'isolamento.
- [ ] `.env.example` non contiene segreti né valori realistici.
- [ ] Il `CLAUDE.md` di progetto ha il glossario del dominio compilato.

---

## Riferimenti

- [Queue](../../rules/queue.md) · [Events](../../rules/events.md) · [Middleware](../../rules/middleware.md)
- [Deployment](../../rules/deployment.md) · [Configurazione](../../rules/configuration.md)
- [Code e scheduler](../../architecture/18-queue-scheduler.md) · [Osservabilità](../../architecture/25-observability.md)
- [Foundation — Tenancy](../../foundation/docs/02-tenancy.md)
- [Checklist di rilascio](../../checklists/release-checklist.md)
