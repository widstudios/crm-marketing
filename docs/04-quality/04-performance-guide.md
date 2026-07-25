# Guida alle prestazioni

> Come si misura, si diagnostica e si migliora la velocità di un'applicazione multitenant, e in
> quale ordine conviene intervenire.

---

## Indice

1. [Descrizione](#descrizione)
2. [Obiettivi di riferimento](#obiettivi-di-riferimento)
3. [Misurare prima di ottimizzare](#misurare-prima-di-ottimizzare)
4. [Le sei cause ricorrenti](#le-sei-cause-ricorrenti)
5. [Query e indici](#query-e-indici)
6. [Cache](#cache)
7. [Code](#code)
8. [Prestazioni e multitenancy](#prestazioni-e-multitenancy)
9. [Prestazioni percepite](#prestazioni-percepite)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Nei gestionali la lentezza raramente dipende da PHP: dipende quasi sempre da **quante volte si
interroga il database** e da **quanti dati si spostano**. Ottimizzare il codice prima di aver
misurato le query è il modo più comune di perdere una giornata senza risultati.

C'è inoltre una specificità multitenant: un'ottimizzazione che funziona sul tenant piccolo può
essere irrilevante su quello con dieci volte i dati. Si misura sul tenant più grande.

---

## Obiettivi di riferimento

| Metrica | Obiettivo | Soglia di allarme |
|---|---|---|
| Tempo di risposta mediano | < 200 ms | > 500 ms |
| 95° percentile | < 800 ms | > 2 s |
| Query per richiesta | < 20 | > 50 |
| Tempo di query più lenta | < 100 ms | > 500 ms |
| Memoria per richiesta | < 64 MB | > 128 MB |
| Job in coda in attesa | < 100 | > 1.000 |
| Durata di un job | < 30 s | > 120 s |
| LCP (pagine pubbliche) | < 2,5 s | > 4 s |

Le soglie valgono **per tenant**: la media aggregata nasconde il cliente che sta soffrendo.

---

## Misurare prima di ottimizzare

| Strumento | Cosa misura | Ambiente |
|---|---|---|
| Telescope | query per richiesta, durata, duplicati | locale, staging |
| Pulse | tempi aggregati, query lente, job | tutti |
| `EXPLAIN` | piano di esecuzione | tutti |
| Horizon | throughput e attese delle code | tutti |
| `DB::listen` | conteggio query in un blocco | locale |
| Test di prestazione | regressioni | CI |

Un test che blocca le regressioni vale più di dieci ottimizzazioni:

```php
it('non produce N+1 sull\'elenco dei lotti', function (): void {
    Batch::factory()->count(50)->create();

    DB::enableQueryLog();
    $this->get('/admin/batches')->assertOk();

    expect(DB::getQueryLog())->toHaveCount(lessThan(15));
});
```

---

## Le sei cause ricorrenti

In ordine di frequenza reale:

### 1. N+1

```php
// ✗ 1 + N query
foreach (Batch::all() as $batch) {
    echo $batch->article->name;
}

// ✓ 2 query
foreach (Batch::with('article')->get() as $batch) {
    echo $batch->article->name;
}
```

### 2. Indice mancante

```sql
EXPLAIN SELECT * FROM stock_movements WHERE batch_id = 42 ORDER BY occurred_at DESC;
-- type: ALL  →  scansione completa: manca l'indice
```

### 3. Dati non paginati

```php
// ✗ Tutto in memoria
$movements = StockMovement::all();

// ✓ Paginazione, o elaborazione a blocchi
$movements = StockMovement::paginate(50);
StockMovement::chunkById(500, fn ($chunk) => /* … */);
```

### 4. Aggregati ricalcolati

Somme e conteggi ricalcolati ad ogni caricamento su tabelle grandi. → cache o colonna di
aggregato aggiornata da listener.

### 5. Operazioni sincrone lente

Invio mail, generazione PDF, chiamate a servizi esterni dentro la richiesta HTTP. → coda.

### 6. Serializzazione eccessiva

Risposte API che includono relazioni non richieste, o componenti Livewire con proprietà pubbliche
pesanti. → serializzare solo il necessario.

---

## Query e indici

```sql
-- Query: WHERE status = ? AND expiry_date < ? ORDER BY expiry_date
INDEX (status, expiry_date)
```

Ordine delle colonne nell'indice composto: **uguaglianza**, poi **intervallo**, poi
**ordinamento**. Un ordine sbagliato rende l'indice inutile per quella query.

| Situazione | Intervento |
|---|---|
| Filtro frequente su colonna non indicizzata | aggiungere l'indice |
| Ordinamento su colonna non indicizzata | indice composto con il filtro |
| `LIKE '%testo%'` | ricerca FULLTEXT o indice dedicato |
| Molte colonne in `SELECT *` | selezionare le colonne necessarie |
| `COUNT(*)` su tabelle enormi | conteggio approssimato o colonna di aggregato |
| Join su colonne non indicizzate | indice sulle chiavi esterne |

Ogni indice rallenta le scritture: si aggiungono per query reali, misurate, non per ipotesi.

---

## Cache

| Cosa | TTL indicativo | Invalidazione |
|---|---|---|
| Configurazione, permessi | 24 h | al deploy |
| Aggregati di dashboard | 5-15 min | a scadenza |
| Elenchi filtrati | 1-5 min | a scadenza |
| Dati di dominio modificabili | — | per evento, non a scadenza |
| Risposte API pubbliche | 1-5 min | a scadenza |

**Regola vincolante:** ogni chiave di cache è prefissata dal tenant.

```php
// ✗ Un tenant legge i dati di un altro
cache()->remember('stock-overview', 300, $callback);

// ✓ Chiave tenant-scoped
cache()->remember(TenantCacheKey::for('stock-overview'), 300, $callback);
```

Questo è l'errore di prestazioni che diventa un incidente di sicurezza: la contromisura è usare
sempre l'helper della Foundation, mai stringhe scritte a mano.

---

## Code

Va in coda tutto ciò che è lento o dipende da un sistema esterno.

| Operazione | Coda | Priorità |
|---|---|---|
| Invio mail e notifiche | `notifications` | media |
| Generazione documenti | `documents` | bassa |
| Chiamate a servizi esterni | `integrations` | media |
| Ricalcolo di aggregati | `default` | media |
| Importazioni ed esportazioni | `bulk` | bassa |
| Operazioni che l'utente attende | `high` | alta |

Code separate per priorità: un'importazione da 100.000 righe non deve ritardare la notifica che
un utente sta aspettando.

Ogni job dichiara `tries`, `backoff` e `timeout`. Un job senza limiti che fallisce può ripartire
all'infinito e saturare i worker.

---

## Prestazioni e multitenancy

| Aspetto | Implicazione |
|---|---|
| Connessioni al database | una per tenant attivo: il pool va dimensionato |
| Migration | N esecuzioni: si programmano a lotti |
| Cache | dimensione moltiplicata per il numero di tenant |
| Backup | N database: la finestra si allunga |
| Metriche | vanno lette per tenant, non aggregate |
| Job | portano con sé il contesto: nessun batch cross-tenant |
| Tenant grandi | dettano il dimensionamento |

Il modo corretto di dimensionare è sul **tenant più grande**, non sulla media: la media descrive
un cliente che non esiste.

---

## Prestazioni percepite

Talvolta la percezione conta più del tempo assoluto:

- indicatori di caricamento sulle operazioni oltre i 300 ms;
- risposta immediata con elaborazione in coda, quando il risultato non è necessario subito;
- paginazione invece di attese lunghe;
- caricamento differito delle parti non visibili;
- risultati parziali invece della pagina bianca.

Un'operazione da 3 secondi con avanzamento visibile viene percepita meglio di una da 2 secondi
senza alcun segnale.

---

## Esempi

### Esempio 1 — N+1 dentro Filament

Elenco dei lotti con colonna «articolo»: 1 query per l'elenco più 1 per riga.

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->with(['article', 'location']);
}
```

Da 51 query a 3.

### Esempio 2 — aggregato che non regge la crescita

`available()` somma tutti i movimenti del lotto ad ogni chiamata. Con 200.000 movimenti l'elenco
impiega secondi.

Soluzione: colonna `current_quantity` aggiornata da un listener sull'evento `MovementRegistered`,
più un ricalcolo notturno di controllo che verifica la coerenza.

La colonna denormalizzata introduce un rischio (disallineamento): il ricalcolo notturno è ciò che
lo rende accettabile.

---

## Best practice

- Misurare prima, ottimizzare dopo, misurare di nuovo.
- Ottimizzare sul tenant più grande.
- Aggiungere test che bloccano le regressioni sulle query.
- Chiavi di cache sempre tenant-scoped, tramite l'helper della Foundation.
- Code separate per priorità.
- Indici derivati da query misurate.
- Denormalizzare solo con un meccanismo di verifica della coerenza.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Ottimizzare senza misurare | Tempo speso senza risultati | Misurare prima |
| Misurare sul tenant piccolo | L'ottimizzazione non serve dove serve | Tenant più grande |
| Chiave di cache senza tenant | Dati di un tenant visibili a un altro | Helper della Foundation |
| Indici «preventivi» | Scritture rallentate senza beneficio | Solo per query misurate |
| Cache su dati che cambiano spesso | Dati obsoleti mostrati all'utente | Invalidazione per evento |
| Job senza `timeout` e `tries` | Worker saturati da job in errore | Limiti sempre dichiarati |
| Denormalizzazione senza verifica | Disallineamento silenzioso | Ricalcolo periodico |

---

## Checklist

- [ ] Ho misurato prima di intervenire.
- [ ] Ho verificato sul tenant con più dati.
- [ ] Nessun N+1 sugli elenchi principali.
- [ ] Indici presenti sulle colonne di filtro e ordinamento.
- [ ] Tutte le collezioni sono paginate.
- [ ] Le chiavi di cache sono tenant-scoped.
- [ ] Le operazioni lente sono in coda, con code separate per priorità.
- [ ] Esiste un test che blocca la regressione sul numero di query.
- [ ] Gli aggregati denormalizzati hanno un ricalcolo di controllo.

---

## Riferimenti

- [Regole di cache](../../rules/cache.md) · [Queue](../../rules/queue.md) · [SQL](../../rules/sql.md)
- [Agente Performance](../../agents/09-performance-agent.md)
- [Strategia di caching](../../architecture/22-caching-strategy.md)
- [Guida al debug](../03-development/07-debugging-guide.md)
- [Checklist prestazioni](../../checklists/performance-checklist.md)
