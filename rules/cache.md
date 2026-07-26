# Regole — Cache

> Chiavi tenant-scoped, TTL obbligatorio, invalidazione per evento sui dati che determinano
> decisioni.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole delle chiavi](#regole-delle-chiavi)
3. [Regole del TTL](#regole-del-ttl)
4. [Invalidazione](#invalidazione)
5. [Cosa non va in cache](#cosa-non-va-in-cache)
6. [Lock](#lock)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

La cache risolve problemi di prestazioni e, se mal usata, ne crea di sicurezza: una chiave senza
prefisso del tenant significa che un cliente legge i dati di un altro. Non un rallentamento, una
fuga di dati.

---

## Regole delle chiavi

**R1.** Ogni chiave si costruisce con l'helper della Foundation, mai a mano.

```php
Cache::remember(TenantCacheKey::for('stock-overview'), 300, $callback);
```

*Motivo:* la costruzione manuale prima o poi dimentica il prefisso.
*Verifica:* test di isolamento della cache, revisione. *Livello: assoluto.*

**R2.** La struttura della chiave è `<tenant>:<ambito>:<identificatore>[:<parametri>]`.
*Verifica:* revisione.

**R3.** Nessun dato personale nella chiave.
*Motivo:* le chiavi compaiono nei log e negli strumenti di ispezione.
*Verifica:* revisione.

**R4.** Le chiavi che dipendono da parametri li includono in modo deterministico (ordine stabile).
*Motivo:* `?a=1&b=2` e `?b=2&a=1` devono produrre la stessa chiave.
*Verifica:* revisione.

---

## Regole del TTL

**R5.** Ogni chiave ha un TTL. Nessuna chiave permanente.
*Motivo:* la memoria di Redis è finita e cresce con il numero di tenant.
*Verifica:* revisione, monitoraggio delle chiavi senza scadenza. *Livello: vincolante.*

| Dato | TTL indicativo |
|---|---|
| Configurazione del tenant | 24 h |
| Permessi dell'utente | 1 h |
| Aggregati di dashboard | 5-15 min |
| Elenchi filtrati | 1-5 min |
| Risultati di report | 15-60 min |
| Contenuti CMS | 1 h |
| Risposte API pubbliche | 1-5 min |

**R6.** Il TTL si scegli rispondendo a: *quanto può essere vecchio questo dato senza causare
problemi?*
*Verifica:* revisione.

---

## Invalidazione

**R7.** I dati che determinano **decisioni operative** si invalidano per evento, non a scadenza.
*Motivo:* una giacenza obsoleta autorizza un prelievo che non dovrebbe avvenire.
*Verifica:* revisione. *Livello: vincolante.*

**R8.** I listener di invalidazione sono **sincroni**.
*Motivo:* tra l'operazione e un'invalidazione asincrona qualcuno può leggere il valore vecchio.
*Verifica:* revisione.

**R9.** L'invalidazione a gruppi usa i tag di Redis, con tag tenant-scoped.
*Verifica:* revisione.

**R10.** Al deploy la cache di framework si ricostruisce (`optimize:clear` poi `optimize`) e i
worker si riavviano.
*Verifica:* script di deploy.

---

## Cosa non va in cache

| Dato | Motivo |
|---|---|
| Risultati di autorizzazione | l'esito dipende dallo stato corrente della risorsa |
| Dati che cambiano ad ogni richiesta | nessun beneficio |
| Dati sensibili non necessari | superficie di esposizione aggiuntiva |
| Sessioni in cache applicativa | hanno un archivio dedicato |
| Contenuti che devono essere sempre coerenti | usare la lettura diretta |

I **permessi** si mettono in cache (cambiano raramente); la **decisione** della Policy no.

---

## Lock

**R11.** Ogni lock ha una scadenza.
*Motivo:* un processo interrotto lascerebbe il lock attivo per sempre.
*Verifica:* revisione. *Livello: vincolante.*

**R12.** Il rilascio del lock avviene in un blocco `finally`.
*Verifica:* revisione.

**R13.** I lock usano un database logico Redis separato dalla cache.
*Motivo:* uno svuotamento della cache non deve rilasciare i lock.
*Verifica:* configurazione.

---

## Esempi

### Esempio 1 — conforme

```php
final readonly class StockOverviewQuery
{
    public function execute(): StockSummaryData
    {
        return Cache::remember(
            TenantCacheKey::for('stock-overview'),
            now()->addMinutes(5),
            fn (): StockSummaryData => $this->calculate(),
        );
    }
}
```

```php
final class InvalidateStockCache          // sincrono
{
    public function handle(MovementRegistered $event): void
    {
        Cache::forget(TenantCacheKey::for('batch', (string) $event->batchId, 'availability'));
        Cache::forget(TenantCacheKey::for('stock-overview'));
    }
}
```

### Esempio 2 — la fuga più comune

```php
// ✗ Il primo tenant che carica la dashboard scrive la chiave: per 5 minuti tutti vedono i suoi numeri
Cache::remember('dashboard-stats', 300, $callback);
```

Il difetto non produce errori, non appare nei log, e viene scoperto quando un cliente segnala di
vedere dati che non riconosce.

---

## Best practice

- Usare sempre l'helper: la disciplina sulle stringhe non regge nel tempo.
- Invalidare per evento i dati che determinano decisioni; a scadenza il resto.
- Monitorare il tasso di hit: sotto il 70% la cache non sta servendo.
- Verificare l'isolamento della cache con un test, in ogni progetto.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Chiave senza prefisso tenant | Un tenant legge i dati di un altro | Helper della Foundation |
| Chiave senza TTL | Memoria che cresce indefinitamente | TTL obbligatorio |
| Invalidazione asincrona su dati critici | Decisioni su valori obsoleti | Listener sincrono |
| Cache della decisione di autorizzazione | Accessi non più validi consentiti | Cache dei permessi, non della decisione |
| Lock senza scadenza | Blocco permanente dopo un'interruzione | Scadenza sempre |
| Lock nello stesso database della cache | `cache:clear` rilascia i lock | Database logici separati |
| Dati personali nella chiave | Esposti nei log e negli strumenti | Solo identificatori |

---

## Checklist

- [ ] Tutte le chiavi passano dall'helper tenant-scoped.
- [ ] Ogni chiave ha un TTL.
- [ ] I dati che determinano decisioni si invalidano per evento.
- [ ] I listener di invalidazione sono sincroni.
- [ ] Nessun dato personale nelle chiavi.
- [ ] I lock hanno scadenza e vengono rilasciati in `finally`.
- [ ] Database logici Redis separati per cache, code, sessioni, lock.
- [ ] Test di isolamento della cache presente e verde.

---

## Riferimenti

- [Strategia di caching](../architecture/22-caching-strategy.md)
- [Performance](performance.md) · [Sicurezza](security.md) · [Events](events.md)
- [Guida alle prestazioni](../docs/04-quality/04-performance-guide.md)
