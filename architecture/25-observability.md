# Osservabilità

> Come si capisce cosa sta facendo il sistema, per tenant, senza dover accedere ai dati dei
> clienti.

---

## Indice

1. [Descrizione](#descrizione)
2. [I tre pilastri](#i-tre-pilastri)
3. [Metriche](#metriche)
4. [Log strutturati](#log-strutturati)
5. [Tracciamento delle richieste](#tracciamento-delle-richieste)
6. [Health check](#health-check)
7. [Osservabilità per tenant](#osservabilità-per-tenant)
8. [Strumenti](#strumenti)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Osservabilità significa poter rispondere a domande **non previste** sul comportamento del sistema,
senza aggiungere codice. «Perché il tenant `acme` ha tempi di risposta tripli da martedì?» è una
domanda che nessuno aveva previsto, e a cui il sistema deve poter rispondere.

Vincolo specifico del nostro contesto: l'osservabilità non deve richiedere l'accesso ai dati dei
clienti. Si osservano numeri e comportamenti, non contenuti.

---

## I tre pilastri

| Pilastro | Risponde a | Strumento |
|---|---|---|
| **Metriche** | quanto? con quale andamento? | Pulse, esportatore di metriche |
| **Log** | cosa è successo esattamente? | log strutturati con contesto |
| **Tracce** | dove è stato speso il tempo? | identificatore di richiesta correlato |

Le metriche dicono che c'è un problema; i log dicono qual è; le tracce dicono dove.

---

## Metriche

| Categoria | Metriche | Granularità |
|---|---|---|
| Richieste | numero, durata (mediana e 95°), tasso di errore | per tenant, per rotta |
| Database | query al secondo, query lente, connessioni | per tenant |
| Code | in attesa, falliti, durata, throughput | per coda |
| Cache | tasso di hit, memoria, rimozioni per limite | globale |
| Storage | byte occupati | per tenant |
| Business | operazioni al giorno, utenti attivi | per tenant |
| Infrastruttura | CPU, memoria, disco, rete | per servizio |

```php
// Metrica applicativa, senza dati identificativi
Pulse::record('movements_registered', tenant()->slug, 1)->count();
```

Le metriche di business sono spesso le prime a segnalare un guasto: un crollo delle operazioni
registrate indica che qualcosa non funziona, anche quando tutti i controlli tecnici sono verdi.

---

## Log strutturati

I log si scrivono in **JSON**, non in testo libero: sono destinati a essere interrogati, non letti.

```php
Log::info('Movement registered', [
    'movement_id' => $movement->id,
    'batch_id' => $movement->batch_id,
    'type' => $movement->type->value,
    'quantity' => (string) $movement->quantity,
]);
```

Contesto aggiunto automaticamente da un middleware:

```php
Log::withContext([
    'tenant' => tenant()?->slug,
    'request_id' => $requestId,
    'user_id' => auth()->id(),
    'release' => config('app.release'),
    'environment' => config('app.env'),
]);
```

| Campo | Perché è obbligatorio |
|---|---|
| `tenant` | senza, l'errore non è attribuibile |
| `request_id` | correla le righe della stessa richiesta |
| `user_id` | chi ha compiuto l'azione |
| `release` | quale versione ha prodotto l'errore |
| `environment` | distingue staging da produzione |

Il campo `tenant` è la differenza tra un log diagnostico e un log inutile.

**Mai nei log**: password, token, dati sanitari, codici fiscali, contenuti di documenti, corpi di
richiesta completi.

---

## Tracciamento delle richieste

```php
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id', (string) Str::uuid());

        Log::withContext(['request_id' => $requestId]);

        return $next($request)->header('X-Request-Id', $requestId);
    }
}
```

L'identificatore:

- compare in tutte le righe di log della richiesta;
- viene restituito nell'header, così il client può citarlo in una segnalazione;
- si propaga ai job accodati durante la richiesta;
- si propaga alle chiamate verso servizi esterni.

Un cliente che segnala «errore alle 14:32» costringe a cercare tra migliaia di righe. Un cliente
che cita `X-Request-Id` porta direttamente alla riga giusta.

---

## Health check

```php
Route::get('/health', function (): JsonResponse {
    $checks = [
        'database' => fn (): bool => DB::connection('landlord')->getPdo() !== null,
        'redis' => fn (): bool => Redis::ping() === 'PONG',
        'storage' => fn (): bool => Storage::disk('landlord')->exists('.health'),
        'queue' => fn (): bool => Queue::size('default') < 10_000,
    ];

    $results = collect($checks)->map(function (callable $check): array {
        try {
            return ['ok' => $check(), 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    });

    $healthy = $results->every(fn (array $r): bool => $r['ok']);

    return response()->json([
        'status' => $healthy ? 'ok' : 'degraded',
        'release' => config('app.release'),
        'checks' => $results,
    ], $healthy ? 200 : 503);
});
```

| Endpoint | Uso |
|---|---|
| `/health` | verifica completa, per il monitoraggio |
| `/health/live` | il processo risponde (per l'orchestratore) |
| `/health/ready` | pronto a ricevere traffico (per il bilanciatore) |

La distinzione tra `live` e `ready` è ciò che permette un rilascio senza interruzione: un container
avviato ma non ancora pronto non deve ricevere traffico.

---

## Osservabilità per tenant

| Aspetto | Regola |
|---|---|
| Metriche | raccolte con etichetta del tenant |
| Log | campo `tenant` sempre presente |
| Dashboard | filtrabili per tenant |
| Allarmi | possono scattare per un singolo tenant |
| Confronti | rispetto alla norma **di quel tenant**, non alla media |
| Contenuti | mai: solo numeri e comportamenti |

La media aggregata nasconde il caso peggiore. Se un tenant su cinquanta ha tempi di risposta
decuplicati, la media resta accettabile e quel cliente è fermo.

```
Tempo di risposta mediano, ultimi 15 minuti
─────────────────────────────────────────────
aggregato          180 ms   ✓
acme               160 ms   ✓
globex             175 ms   ✓
initech          2.400 ms   ⚠  ← invisibile nell'aggregato
```

---

## Strumenti

| Strumento | Ruolo | Ambienti |
|---|---|---|
| Laravel Pulse | metriche applicative | tutti |
| Horizon | code | tutti |
| Telescope | ispezione dettagliata | locale, staging |
| Log strutturati | diagnosi | tutti |
| Esportatore di metriche | integrazione con il monitoraggio | staging, produzione |
| Health check | verifica dello stato | tutti |

Telescope **non** in produzione: registra corpi di richiesta e risultati di query, cioè dati
personali in un archivio non previsto dal trattamento, oltre a riempire il disco.

---

## Esempi

### Esempio 1 — diagnosi guidata dai tre pilastri

```
Metrica:  il 95° percentile del tenant `initech` passa da 400 ms a 3.200 ms
Log:      filtro tenant=initech, durata>1000 → tutte sulla rotta batches.index
Traccia:  request_id di una richiesta lenta → 1.240 query per richiesta
Causa:    initech ha 340 ubicazioni; la colonna «ubicazione» dell'elenco lotti
          non era in eager loading. Sugli altri tenant l'N+1 era invisibile.
Rimedio:  with(['location']) e test sul numero di query
```

Nessun accesso ai dati del cliente: solo metriche, log e conteggi.

### Esempio 2 — allarme per singolo tenant

`initech` supera i 2 secondi di mediana per 5 minuti consecutivi. L'allarme scatta anche se
l'aggregato è verde, perché la soglia è valutata per tenant.

---

## Best practice

- Log in JSON, con contesto obbligatorio.
- `tenant` e `request_id` in ogni riga.
- Restituire `X-Request-Id` al client.
- Metriche per tenant, oltre che aggregate.
- Allarmi valutati sul singolo tenant.
- `live` e `ready` distinti.
- Telescope solo fuori dalla produzione.
- Osservare numeri e comportamenti, mai contenuti.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Log senza `tenant` | Errori non attribuibili | Contesto obbligatorio |
| Log in testo libero | Non interrogabili | JSON strutturato |
| Solo metriche aggregate | Il tenant in difficoltà è invisibile | Metriche per tenant |
| Nessun identificatore di richiesta | Impossibile correlare | Middleware dedicato |
| Health check che verifica solo il processo | Il servizio risponde ma è degradato | Verifica delle dipendenze |
| `live` e `ready` non distinti | Traffico verso container non pronti | Endpoint separati |
| Telescope in produzione | Dati personali registrati, disco pieno | Disabilitato |
| Dati dei clienti nelle metriche | Fuga verso il monitoraggio | Solo numeri |

---

## Checklist

- [ ] I log sono in JSON con contesto obbligatorio.
- [ ] Ogni riga di log ha `tenant`, `request_id`, `user_id`, `release`.
- [ ] `X-Request-Id` è accettato e restituito.
- [ ] L'identificatore si propaga ai job e alle chiamate esterne.
- [ ] Le metriche sono raccolte per tenant.
- [ ] Gli allarmi possono scattare per un singolo tenant.
- [ ] `/health`, `/health/live`, `/health/ready` disponibili.
- [ ] L'health check verifica le dipendenze, non solo il processo.
- [ ] Telescope disabilitato in produzione.
- [ ] Nessun dato personale nelle metriche o nei log.

---

## Riferimenti

- [Monitoraggio e log](../docs/05-operations/03-monitoring-and-logging.md)
- [Regole di logging](../rules/logging.md)
- [Gestione degli incidenti](../docs/05-operations/05-incident-management.md)
- [Code e scheduler](18-queue-scheduler.md)
