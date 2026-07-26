# Strategia di caching

> Come si costruiscono le chiavi, come si invalidano, e perché in un contesto multitenant una
> chiave sbagliata è un incidente di sicurezza.

---

## Indice

1. [Descrizione](#descrizione)
2. [Isolamento delle chiavi](#isolamento-delle-chiavi)
3. [Che cosa si mette in cache](#che-cosa-si-mette-in-cache)
4. [Invalidazione](#invalidazione)
5. [Cache di framework](#cache-di-framework)
6. [Lock distribuiti](#lock-distribuiti)
7. [Dimensionamento](#dimensionamento)
8. [Verifica](#verifica)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

La cache risolve problemi di prestazioni e, se mal usata, ne crea di sicurezza. In un'applicazione
multitenant una chiave senza prefisso del tenant significa che un cliente legge dalla cache i dati
di un altro: non un rallentamento, una fuga di dati.

Per questo la costruzione delle chiavi non è a discrezione di chi scrive il codice: passa da un
helper della Foundation.

---

## Isolamento delle chiavi

```php
final readonly class TenantCacheKey
{
    public static function for(string $key, string ...$parts): string
    {
        $tenant = TenantContext::current()?->slug ?? 'landlord';

        return implode(':', [$tenant, $key, ...$parts]);
    }
}
```

```php
// ✗ Chiave condivisa: il primo tenant che la scrive la serve a tutti
Cache::remember('stock-overview', 300, $callback);

// ✓ Chiave isolata
Cache::remember(TenantCacheKey::for('stock-overview'), 300, $callback);
```

Doppia protezione: il prefisso applicativo **e** il prefisso di connessione impostato dal
bootstrapper (`cache.prefix`). La ridondanza è deliberata: se una delle due fallisce, l'altra
regge.

Struttura di una chiave:

```
<tenant>:<ambito>:<identificatore>[:<parametri>]

acme:stock-overview
acme:batch:42:availability
acme:report:expiring:30
landlord:plans:active
```

---

## Che cosa si mette in cache

| Dato | TTL | Invalidazione |
|---|---|---|
| Configurazione del tenant | 24 h | ad ogni modifica |
| Permessi dell'utente | 1 h | al cambio di ruolo |
| Aggregati di dashboard | 5-15 min | a scadenza |
| Elenchi filtrati | 1-5 min | a scadenza |
| Risultati di report | 15-60 min | a scadenza o su richiesta |
| Contenuti CMS | 1 h | alla pubblicazione |
| Risposte API pubbliche | 1-5 min | a scadenza |
| Dati di dominio modificabili | — | **per evento**, non a scadenza |

Cosa **non** va in cache: dati che cambiano ad ogni richiesta, risultati di autorizzazione (i
permessi sì, la decisione no), dati sensibili non necessari, contenuti che devono essere sempre
coerenti.

---

## Invalidazione

Due strategie, con usi diversi.

### A scadenza (TTL)

Semplice, adatta ai dati la cui obsolescenza temporanea è tollerabile.

```php
Cache::remember(TenantCacheKey::for('stock-overview'), now()->addMinutes(5), $callback);
```

Il TTL si scegli in base alla domanda: *quanto può essere vecchio questo dato senza causare
problemi?* Cinque minuti su una dashboard sono accettabili; cinque minuti su una giacenza che
determina un prelievo non lo sono.

### Per evento

Necessaria quando l'obsolescenza produce decisioni errate.

```php
final class InvalidateStockCache
{
    public function handle(MovementRegistered $event): void
    {
        Cache::forget(TenantCacheKey::for('batch', (string) $event->batchId, 'availability'));
        Cache::forget(TenantCacheKey::for('stock-overview'));
    }
}
```

Questo listener è **sincrono**: se fosse asincrono, tra l'operazione e l'invalidazione qualcuno
potrebbe leggere il valore vecchio.

### Invalidazione per insiemi

Con Redis si usano i tag:

```php
Cache::tags([TenantCacheKey::for('stock')])->remember('overview', 300, $callback);
Cache::tags([TenantCacheKey::for('stock')])->flush();   // invalida l'insieme
```

I tag semplificano l'invalidazione a gruppi ma hanno un costo: si usano quando l'insieme delle
chiavi da invalidare non è noto a priori.

---

## Cache di framework

Diversa dalla cache applicativa: non contiene dati, contiene struttura.

| Cache | Comando | Quando |
|---|---|---|
| Configurazione | `config:cache` | al deploy |
| Rotte | `route:cache` | al deploy |
| Viste | `view:cache` | al deploy |
| Eventi | `event:cache` | al deploy |
| Autoload Composer | `dump-autoload -o` | al deploy |

`config:cache` in produzione è la ragione per cui `env()` non funziona fuori da `config/`: dopo la
compilazione, i file di configurazione non vengono più letti e `env()` ritorna `null`.

Al deploy: `optimize:clear` seguito da `optimize`, e riavvio dei worker.

---

## Lock distribuiti

Per le operazioni che non devono essere eseguite in parallelo.

```php
$lock = Cache::lock(TenantCacheKey::for('lock', 'stock-recalculation'), 300);

if (! $lock->get()) {
    return;   // un'altra esecuzione è in corso
}

try {
    // operazione esclusiva
} finally {
    $lock->release();
}
```

| Uso | Nota |
|---|---|
| Comandi schedulati | `withoutOverlapping()` lo usa internamente |
| Ricalcoli massivi | evita esecuzioni concorrenti |
| Operazioni su risorse esterne | evita chiamate duplicate |
| Provisioning | un tenant per volta |

Il lock ha **sempre** una scadenza: senza, un processo interrotto lascia il lock attivo per sempre
e blocca ogni esecuzione successiva.

---

## Dimensionamento

| Aspetto | Considerazione |
|---|---|
| Memoria | cresce col numero di tenant, non solo col volume di dati |
| Politica di rimozione | `allkeys-lru`: rimuove le chiavi meno usate |
| TTL obbligatorio | una chiave senza scadenza resta per sempre |
| Database logici separati | cache, code, sessioni, lock non si mescolano |
| Monitoraggio | tasso di hit, memoria, chiavi rimosse per limite |

Un tasso di hit sotto il 70% indica che la cache non sta servendo: chiavi troppo specifiche, TTL
troppo brevi, o invalidazione troppo aggressiva.

---

## Verifica

```php
it('non condivide la cache tra tenant', function (): void {
    $acme = createTenant('acme');
    $globex = createTenant('globex');

    tenancy()->run($acme, function (): void {
        Cache::put(TenantCacheKey::for('test'), 'dati-acme', 60);
    });

    tenancy()->run($globex, function (): void {
        expect(Cache::get(TenantCacheKey::for('test')))->toBeNull();
    });
});

arch('nessuna chiave di cache scritta a mano')
    ->expect('App')
    ->not->toUse(['Cache::remember', 'Cache::put'])   // ammessi solo tramite l'helper
    ->ignoring('App\Infrastructure\Cache');
```

Il test di isolamento della cache è obbligatorio in ogni progetto: è la verifica del punto più
insidioso della multitenancy.

---

## Esempi

### Esempio 1 — la fuga più comune

Un widget di dashboard usa `Cache::remember('dashboard-stats', 300, ...)`.

Il primo tenant che carica la dashboard scrive la chiave. Per i cinque minuti successivi, **tutti**
i tenant vedono i suoi numeri.

Il difetto non produce errori, non appare nei log, e viene scoperto quando un cliente segnala di
vedere dati che non riconosce.

### Esempio 2 — invalidazione corretta per evento

Una giacenza in cache determina se un prelievo è possibile. Con TTL di cinque minuti, un prelievo
potrebbe essere autorizzato su dati vecchi.

Soluzione: invalidazione sincrona su `MovementRegistered`, senza TTL lungo. La cache accelera le
letture ripetute e non introduce decisioni errate.

---

## Best practice

- Tutte le chiavi tramite `TenantCacheKey::for()`.
- TTL obbligatorio su ogni chiave.
- Invalidazione per evento sui dati che determinano decisioni.
- Listener di invalidazione **sincroni**.
- Lock sempre con scadenza.
- Database logici Redis separati per uso.
- Test di isolamento della cache obbligatorio.
- Monitorare il tasso di hit.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Chiave senza prefisso tenant | Un tenant legge i dati di un altro | `TenantCacheKey::for()` |
| Chiave senza TTL | Memoria che cresce indefinitamente | TTL obbligatorio |
| Invalidazione asincrona su dati critici | Letture di valori obsoleti | Listener sincrono |
| Cache su dati che decidono operazioni, con TTL lungo | Decisioni su dati vecchi | Invalidazione per evento |
| Lock senza scadenza | Blocco permanente dopo un'interruzione | Scadenza sempre |
| `env()` fuori da `config/` | `null` con `config:cache` | Solo `config()` |
| Worker non riavviati dopo il deploy | Configurazione vecchia in memoria | `queue:restart` |
| Nessun test di isolamento della cache | La fuga non emerge | Test obbligatorio |

---

## Checklist

- [ ] Tutte le chiavi passano da `TenantCacheKey::for()`.
- [ ] Ogni chiave ha un TTL.
- [ ] I dati che determinano decisioni sono invalidati per evento.
- [ ] I listener di invalidazione sono sincroni.
- [ ] I lock hanno una scadenza.
- [ ] Redis usa database logici separati per uso.
- [ ] Esiste il test di isolamento della cache tra tenant.
- [ ] Il tasso di hit è monitorato.
- [ ] La sequenza di deploy ricostruisce le cache e riavvia i worker.

---

## Riferimenti

- [Regole di cache](../rules/cache.md)
- [Multitenancy](03-multitenancy-overview.md) · [Risoluzione del tenant](06-tenant-resolution.md)
- [Guida alle prestazioni](../docs/04-quality/04-performance-guide.md)
- [Eventi](21-events-and-messaging.md)
