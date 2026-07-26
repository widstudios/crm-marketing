# Modulo — reporting

> Report pianificati ed esportazioni, senza far attendere nessuno e senza saturare la memoria.

| | |
|---|---|
| **Nome** | `reporting` |
| **Categoria** | opzionale |
| **Dipende da** | `documents` |
| **Contratti implementati** | `ReportRunner`, `Exporter` |

---

## Indice

1. [Descrizione](#descrizione) 2. [Che cosa fornisce](#che-cosa-fornisce)
3. [Che cosa non fa](#che-cosa-non-fa) 4. [Il percorso di un'esportazione](#il-percorso-di-unesportazione)
5. [Perché non in sincrono](#perché-non-in-sincrono) 6. [Configurazione](#configurazione)
7. [Integrazione](#integrazione) 8. [Adozione](#adozione) 9. [Esempi](#esempi)
10. [Best practice](#best-practice) 11. [Errori comuni](#errori-comuni) 12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Ogni gestionale, dopo qualche mese di uso reale, riceve la stessa richiesta: «posso avere questi
dati in Excel?». La risposta ingenua — un pulsante che genera il file durante la richiesta —
funziona in prova e fallisce con i dati veri, in due modi: il tempo di risposta scade, oppure la
memoria si esaurisce.

Questo modulo esiste per rendere la risposta corretta la più semplice da dare. Fornisce
l'infrastruttura — esecuzione asincrona, streaming, pianificazione, archiviazione, notifica — e
lascia al progetto la sola cosa che gli appartiene: **quali dati**.

---

## Che cosa fornisce

### Tabelle — tenant

| Tabella | Contenuto |
|---|---|
| `report_definitions` | report disponibili, con parametri e permesso richiesto |
| `report_schedules` | pianificazioni, con destinatari e frequenza |
| `report_runs` | esecuzioni: stato, avanzamento, durata, righe, esito |
| `export_jobs` | esportazioni su richiesta |

`report_runs` conserva l'avanzamento perché un'esportazione da mezz'ora senza indicazione di
progresso viene lanciata tre volte dallo stesso utente convinto che non funzioni.

### Permessi

| Permesso | Consente |
|---|---|
| `report.view` | vedere l'elenco ed eseguire i report consentiti |
| `report.schedule` | pianificare invii ricorrenti |
| `report.export` | esportare elenchi |
| `report.manage` | definire nuovi report |

Ogni definizione dichiara inoltre il **proprio** permesso: un report che aggrega dati sensibili non
è accessibile a chiunque abbia `report.view`.

### Comandi

| Comando | Quando |
|---|---|
| `reports:run --definition=` | esecuzione manuale |
| `reports:dispatch-scheduled` | pianificato: avvia i report maturi |
| `reports:prune --older-than=30` | pulizia dei file generati |

### Eventi

| Evento | Emesso quando |
|---|---|
| `ReportCompleted` · `ReportFailed` | esito dell'esecuzione |
| `ExportReady` | il file è disponibile |

---

## Che cosa non fa

| Non fa | Dove va cercato |
|---|---|
| Definire i report del dominio | progetto: quali dati servano è dominio |
| Costruttore di query visuale | non previsto: vedi sotto |
| Grafici e cruscotti interattivi | widget Filament, o uno strumento di visualizzazione |
| Data warehouse, OLAP | fuori ambito |
| Aggregazione tra tenant | impossibile per costruzione: nessuna query cross-tenant |
| Invio dei report | usa [notifications](notifications.md), se attivo |
| Archiviazione dei file | usa [documents](documents.md) |

**Costruttore di query visuale.** Un'interfaccia che permette all'utente di comporre query
arbitrarie è, dal punto di vista del sistema, un'interfaccia che permette di eseguire query
arbitrarie: si perde il controllo sugli indici usati, sui tempi, sul volume dei dati estratti e
sulle colonne accessibili. Un report è definito da uno sviluppatore, con un permesso e una query
verificata. È meno flessibile, e funziona anche fra due anni.

---

## Il percorso di un'esportazione

```
richiesta
   │
   ├─ autorizzazione: permesso generico + permesso della definizione
   ├─ parametri validati contro lo schema della definizione
   │
   ├─ export_jobs: riga creata, stato 'in_coda'
   └─ risposta 202 con l'identificativo          ← l'utente non attende
             │
             ▼
        job in coda 'bulk'
             │
             ├─ query a blocchi (chunkById / cursore)
             ├─ scrittura in streaming su file temporaneo
             ├─ avanzamento aggiornato ogni N righe
             │
             ├─ archiviazione tramite documents
             └─ notifica: il file è pronto
                       │
                       ▼
              scaricabile con URL firmato a scadenza breve
```

La coda è `bulk`: un'esportazione da 500.000 righe non deve ritardare una notifica che qualcuno sta
aspettando.

---

## Perché non in sincrono

| Approccio | Con 1.000 righe | Con 500.000 righe |
|---|---|---|
| Sincrono, tutto in memoria | funziona | memoria esaurita |
| Sincrono, in streaming | funziona | tempo di risposta scaduto |
| **Asincrono, in streaming** | funziona | funziona |

Le prime due righe sono il motivo per cui questo modulo esiste. Entrambe funzionano in sviluppo, e
con dati di prova sembrano scelte ragionevoli: il difetto compare dal primo cliente con dati reali,
cioè nel momento peggiore.

L'uso della memoria di un'esportazione deve essere **costante** rispetto al numero di righe. Non è
un obiettivo di ottimizzazione: è la differenza tra un'esportazione che funziona e una che dipende
da quanto è grande il cliente.

---

## Configurazione

```php
// config/reporting.php
return [
    'queue' => 'bulk',

    'chunk_size' => 1000,

    // Oltre questo limite l'esportazione viene rifiutata con un messaggio che
    // suggerisce di restringere i filtri. Un limite esplicito è meglio di un
    // fallimento dopo venti minuti.
    'max_rows' => 500000,

    'formats' => ['csv', 'xlsx', 'pdf'],

    'timeout_seconds' => 1800,

    // I file generati non si conservano: contengono dati che esistono già
    // altrove, e occupano spazio moltiplicato per il numero di tenant.
    'retention_days' => 30,

    'signed_url_ttl' => 900,

    'schedules' => [
        'max_per_tenant' => 50,
    ],
];
```

---

## Integrazione

```php
final class ExpiringBatchesReport extends ReportDefinition
{
    protected string $name = 'expiring_batches';

    protected string $label = 'Lotti in scadenza';

    // Permesso proprio, oltre a report.view
    protected string $permission = 'batch.view';

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'within_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function query(array $parameters): Builder
    {
        // Un Query object del progetto: colonne esplicite, indici verificati.
        return (new ExpiringBatchesQuery($parameters['within_days']))->toBuilder();
    }

    /** @return array<string, string> */
    public function columns(): array
    {
        return [
            'number' => 'Numero lotto',
            'article_name' => 'Articolo',
            'expiry_date' => 'Scadenza',
            'quantity' => 'Giacenza',
        ];
    }
}
```

Il progetto dichiara **cosa** estrarre; il modulo si occupa di come farlo senza rompere nulla.

---

## Adozione

```bash
# config/foundation.php → 'modules' => ['enabled' => [..., 'documents', 'reporting']]

php artisan tenants:migrate
php artisan tenants:artisan "auth:sync-permissions"
```

```php
// routes/console.php
Schedule::command('tenants:artisan "reports:dispatch-scheduled"')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
```

Verificare che la coda `bulk` abbia **almeno un worker dedicato**: senza, i report competono con il
resto e il ritardo si accumula in modo invisibile.

---

## Esempi

### Streaming, non accumulo

```php
// ✗ Tutto in memoria: funziona finché il cliente è piccolo
$rows = $query->get();
Excel::store(new Export($rows), $path);

// ✓ Memoria costante rispetto al numero di righe
$handle = fopen($path, 'wb');
fputcsv($handle, array_values($definition->columns()));

$query->chunkById(1000, function ($chunk) use ($handle, $run): void {
    foreach ($chunk as $row) {
        fputcsv($handle, $row->toArray());
    }

    $run->increment('rows_written', $chunk->count());
});

fclose($handle);
```

### Autorizzazione a due livelli

```php
// Il permesso generico non basta: un report che aggrega dati sensibili
// richiede anche il permesso di quei dati.
if (! $user->can('report.export') || ! $user->can($definition->permission())) {
    abort(403);
}
```

---

## Best practice

- Esportazioni sempre asincrone, sempre in streaming.
- Coda `bulk` con almeno un worker dedicato.
- Limite massimo di righe dichiarato: meglio un rifiuto immediato di un fallimento dopo venti minuti.
- Aggiornare l'avanzamento: senza, l'utente rilancia l'operazione.
- Permesso proprio per ogni definizione che tocca dati sensibili.
- Non conservare i file generati oltre il necessario: i dati esistono già altrove.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Esportazione sincrona | Tempo di risposta scaduto sui clienti reali | Job in coda, risposta 202 |
| Risultato accumulato in memoria | Memoria esaurita | Streaming a blocchi |
| Report sulla coda `default` | Ritardano le notifiche attese | Coda `bulk` |
| Nessun limite di righe | Fallimento dopo venti minuti | `max_rows` esplicito |
| Nessun avanzamento | L'utente rilancia tre volte | `report_runs` aggiornato |
| Solo il permesso generico verificato | Dati sensibili in un report accessibile | Permesso della definizione |
| File conservati per sempre | Spazio moltiplicato per il numero di tenant | Politica di conservazione |
| URL di download senza scadenza | Condivisione permanente di dati | URL firmato a TTL breve |
| Query non indicizzata nel report | Scansione completa a ogni esecuzione | `EXPLAIN` sulla definizione |
| Costruttore di query per l'utente | Query arbitrarie sul database di produzione | Definizioni scritte e verificate |

---

## Checklist

- [ ] Ogni esportazione è asincrona e scrive in streaming.
- [ ] L'uso di memoria è costante rispetto al numero di righe.
- [ ] La coda `bulk` ha un worker dedicato.
- [ ] È dichiarato un limite massimo di righe.
- [ ] L'avanzamento è visibile all'utente.
- [ ] Ogni definizione dichiara il proprio permesso.
- [ ] I file generati hanno una politica di conservazione.
- [ ] Il download avviene con URL firmato a scadenza breve.
- [ ] Le query dei report sono verificate con `EXPLAIN` su volumi realistici.

---

## Riferimenti

- [Code e scheduler](../../architecture/18-queue-scheduler.md) · [Ricerca](../../architecture/23-search.md)
- [Performance](../../rules/performance.md) · [SQL](../../rules/sql.md) · [Queue](../../rules/queue.md)
- [documents](documents.md) · [notifications](notifications.md)
- [Checklist prestazioni](../../checklists/performance-checklist.md)
