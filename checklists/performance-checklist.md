# Checklist — Prestazioni

> Verifica su volumi realistici, non su dieci righe: numero di query, tempi, indici, uso della
> memoria.

| | |
|---|---|
| **Fase** | 10 — Performance |
| **Agente** | [Performance Agent](../agents/09-performance-agent.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Quasi tutti i problemi di prestazioni di un gestionale nascono da tre cause: query dentro un ciclo,
indici mancanti, insiemi non limitati caricati in memoria. Sono tutte e tre invisibili con i dati di
prova, e tutte e tre evidenti al primo cliente con dati reali.

Questa checklist si verifica **su volumi realistici**. Una verifica su dieci righe non è una
verifica: è una conferma che il codice non va in errore.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Volumi di prova

- [ ] Esiste un seeder che genera volumi realistici per il dominio (ordine di grandezza dichiarato
      nel brief, o 100.000 righe sulla tabella principale se non dichiarato).
- [ ] Le misure sono state prese su questi volumi, non sui dati di sviluppo.
- [ ] I volumi usati sono dichiarati nel report, tabella per tabella.

### Query

- [ ] Nessuna query dentro un ciclo, in nessun percorso misurato.
- [ ] Ogni elenco carica in anticipo le relazioni che usa (`with()`).
- [ ] Il numero di query di ogni elenco è **costante** rispetto al numero di righe.
- [ ] Esiste un test che vincola il numero massimo di query dei percorsi principali.
- [ ] Nessun `SELECT *` nelle letture per la presentazione.
- [ ] Le aggregazioni si calcolano nel database, non in PHP.
- [ ] `withCount()` invece di contare relazioni caricate.
- [ ] Nessun `count()` su una relazione dentro un ciclo di rendering.

### Indici

- [ ] Ogni chiave esterna è indicizzata.
- [ ] Ogni colonna usata nei filtri e negli ordinamenti dichiarati ha un indice.
- [ ] Negli indici composti l'ordine è: uguaglianza, intervallo, ordinamento.
- [ ] `EXPLAIN` verificato su ogni query che tocca tabelle oltre le 100.000 righe.
- [ ] Nessuna scansione completa di tabella nei percorsi principali.
- [ ] Nessun indice senza una query che lo usi.

### Memoria

- [ ] Nessun `all()` o `get()` su un insieme non limitato.
- [ ] Le elaborazioni massive procedono a blocchi (`chunkById`, `lazyById`, cursore).
- [ ] Le esportazioni scrivono in streaming, non costruiscono l'intero risultato in memoria.
- [ ] L'uso di memoria di un job massivo è **costante** rispetto al numero di righe.

### Tempi di risposta

| Percorso | Soglia |
|---|---|
| Pagina di elenco | < 500 ms |
| Pagina di dettaglio | < 300 ms |
| Endpoint API di lettura | < 200 ms |
| Endpoint API di scrittura | < 500 ms |
| Widget di dashboard | < 400 ms, o asincrono |

- [ ] Ogni percorso principale rispetta la soglia sulla tabella qui sopra.
- [ ] Le operazioni oltre i **200 ms** che dipendono da sistemi esterni sono in coda.
- [ ] Nessun widget di dashboard esegue una query non memorizzata su tabelle grandi.

### Cache

- [ ] Ogni chiave di cache è tenant-scoped.
- [ ] Ogni valore memorizzato ha una scadenza dichiarata o una invalidazione esplicita.
- [ ] La cache è usata dove il costo di calcolo lo giustifica, non ovunque.
- [ ] Nessun dato che deve essere immediatamente coerente è memorizzato senza invalidazione.
- [ ] Esiste un test che verifica l'invalidazione dopo la mutazione.

### Frontend

- [ ] Gli asset sono compilati in produzione con Vite, minificati e con hash nel nome.
- [ ] Nessuna libreria caricata per una funzione sola.
- [ ] Le immagini caricate dagli utenti sono ridimensionate lato server.
- [ ] Gli elenchi lunghi sono paginati, non caricati interamente.

### Migration e code

- [ ] La durata delle migration tenant è stata misurata su un database con volumi realistici.
- [ ] Le migration tenant girano a lotti in produzione.
- [ ] Nessun job massivo occupa una coda ad alta priorità.

---

## Comandi di verifica

```bash
php artisan db:seed --class=PerformanceSeeder     # volumi realistici
php artisan test --testsuite=Performance          # vincoli sul numero di query
php artisan tenants:artisan "db:explain"          # EXPLAIN sulle query principali
composer test:queries                             # rilevamento di query N+1
```

Durante lo sviluppo, il rilevamento delle query N+1 va tenuto **attivo**: intercetta il difetto nel
momento in cui viene scritto, invece che in fase di ottimizzazione.

Le misure si riportano **con il volume a cui sono state prese**. «Elenco lotti: 180 ms» non è
un'informazione; «Elenco lotti: 180 ms, 11 query, su 120.000 lotti» lo è.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/performance-checklist.md: N/N soddisfatte.

Volumi: 120.000 lotti, 800.000 movimenti, 3.000 articoli.

| Percorso | Query | Tempo |
|---|---|---|
| Elenco lotti (50 righe) | 11 | 180 ms |
| Dettaglio lotto | 7 | 90 ms |
| API movimenti (100 righe) | 6 | 140 ms |
| Dashboard | 9 | 310 ms |

Il numero di query è costante rispetto al numero di righe, verificato a 10, 50 e 100 righe.
```

### Esito con voci non soddisfatte

```markdown
### Quality gate
- checklists/performance-checklist.md: N-2/N soddisfatte.

Volumi: 120.000 lotti, 800.000 movimenti.

Voci non soddisfatte:
- Query costanti rispetto alle righe: l'elenco lotti esegue 3+N query (161 con 50 righe).
  Causa: `$batch->article->name` nella colonna, senza eager loading.
  Correzione: `->with(['article', 'movements'])` nella query della tabella.
- EXPLAIN senza scansioni complete: il filtro «in scadenza» esegue una scansione completa su
  `batches` (120.000 righe, 2,4 s).
  Correzione: indice composto `(status, expiry_date)`, in quest'ordine.

Richiedo rework su questi punti.
```

---

## Best practice

- Misurare prima di ottimizzare, e misurare di nuovo dopo: senza numeri, l'ottimizzazione è
  un'opinione.
- Generare volumi realistici una volta e riusare lo stesso seeder su ogni progetto.
- Tenere attivo il rilevamento N+1 in sviluppo: costa nulla e intercetta il difetto subito.
- Vincolare il numero di query con un test: è l'unico modo perché il difetto non ritorni.
- Correggere l'indice prima di riscrivere la query: spesso è tutto ciò che serve.
- Non memorizzare in cache per mascherare una query lenta: prima si corregge la query.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Misure sui dati di sviluppo | Il problema emerge dal primo cliente reale | Volumi realistici |
| Query dentro un ciclo | Tempi che crescono con le righe | Eager loading |
| Nessun test sul numero di query | Il difetto ritorna al primo refactoring | Vincolo nel test |
| Indice mancante sui filtri | Scansione completa su tabelle grandi | `EXPLAIN` e indice |
| Ordine sbagliato nell'indice composto | Indice presente e inutile | Uguaglianza, intervallo, ordinamento |
| `all()` su insieme non limitato | Memoria esaurita sui tenant grandi | `chunkById` |
| Cache per mascherare una query lenta | Il problema resta, con dati potenzialmente vecchi | Correggere la query |
| Cache senza invalidazione | Dati vecchi mostrati come attuali | Invalidazione esplicita e testata |
| Widget non memorizzato su tabelle grandi | La dashboard è la pagina più lenta | Cache o calcolo asincrono |
| Misure riportate senza il volume | Il numero non è interpretabile | Sempre volume e query |

---

## Checklist

- [ ] Ho generato volumi realistici e li ho dichiarati.
- [ ] Ho misurato query e tempi su quei volumi, non sui dati di sviluppo.
- [ ] Ho verificato `EXPLAIN` sulle query che toccano tabelle grandi.
- [ ] Ho verificato che il numero di query sia costante rispetto alle righe.
- [ ] Ho aggiunto un test che vincola il numero di query dei percorsi principali.
- [ ] Ho riportato ogni misura con il volume a cui è stata presa.
- [ ] Se il gate è rosso, l'ho dichiarato.

---

## Riferimenti

- [Fase 10](../workflows/11-phase-performance.md) · [Performance Agent](../agents/09-performance-agent.md)
- [Regole di performance](../rules/performance.md) · [SQL](../rules/sql.md) · [Cache](../rules/cache.md)
- [Strategia di caching](../architecture/22-caching-strategy.md)
- [Guida alle prestazioni](../docs/04-quality/04-performance-guide.md)
