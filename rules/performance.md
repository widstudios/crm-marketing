# Regole — Prestazioni

> Misurare prima, ottimizzare dopo, sul tenant più grande.

---

## Indice

1. [Descrizione](#descrizione)
2. [Obiettivi](#obiettivi)
3. [Regole](#regole)
4. [Query](#query)
5. [Cache](#cache)
6. [Asincronia](#asincronia)
7. [Frontend](#frontend)
8. [Regressioni](#regressioni)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Nei gestionali la lentezza dipende quasi sempre da quante volte si interroga il database e da quanti
dati si spostano, non dalla velocità di PHP. Ottimizzare il codice prima di aver contato le query è
il modo più comune di perdere una giornata senza risultati.

---

## Obiettivi

| Metrica | Obiettivo | Allarme |
|---|---|---|
| Tempo di risposta mediano | < 200 ms | > 500 ms |
| 95° percentile | < 800 ms | > 2 s |
| Query per richiesta | < 20 | > 50 |
| Query più lenta | < 100 ms | > 500 ms |
| Memoria per richiesta | < 64 MB | > 128 MB |
| Durata di un job | < 30 s | > 120 s |
| LCP (pagine pubbliche) | < 2,5 s | > 4 s |

**R1.** Le soglie valgono **per tenant**, non in aggregato.
*Motivo:* la media nasconde il cliente che sta soffrendo. *Verifica:* metriche per tenant.

---

## Regole

**R2.** Nessuna ottimizzazione senza misurazione preliminare.
*Verifica:* revisione: la PR di ottimizzazione riporta i numeri prima e dopo.

**R3.** Le ottimizzazioni si verificano sul tenant con **più dati**.
*Verifica:* revisione.

**R4.** Ogni ottimizzazione è accompagnata da un test che blocca la regressione.

```php
it('non produce N+1 sull\'elenco dei lotti', function (): void {
    Batch::factory()->count(50)->create();

    DB::enableQueryLog();
    $this->get('/admin/batches')->assertOk();

    expect(DB::getQueryLog())->toHaveCount(lessThan(15));
});
```

*Verifica:* pipeline. *Livello: vincolante.*

**R5.** Nessuna denormalizzazione senza un meccanismo di verifica della coerenza.
*Motivo:* un aggregato che si disallinea silenziosamente è peggio di una query lenta.
*Verifica:* presenza del ricalcolo periodico.

---

## Query

**R6.** Eager loading su ogni relazione usata in un elenco.
*Verifica:* test sul numero di query.

**R7.** Paginazione o elaborazione a blocchi su ogni collezione: mai `all()` su tabelle di dominio.
*Verifica:* revisione, test.

**R8.** Indici su tutte le colonne usate in filtri e ordinamenti dichiarati.
*Verifica:* revisione con lo schema alla mano.

**R9.** `EXPLAIN` verificato sulle query che interessano tabelle oltre le 100.000 righe.
*Verifica:* revisione.

**R10.** Nessuna query dentro un ciclo.
*Verifica:* test sul numero di query.

**R11.** Le colonne calcolate su relazioni usano `withCount()`, `withSum()`, non accessor con query.
*Verifica:* revisione.

---

## Cache

**R12.** Gli aggregati di dashboard sono in cache, con chiave tenant-scoped.
*Verifica:* revisione.

**R13.** I dati che determinano decisioni si invalidano per evento, non a scadenza.
*Verifica:* revisione.

**R14.** Nessuna cache su dati che cambiano ad ogni richiesta.
*Verifica:* revisione.

---

## Asincronia

**R15.** Ogni operazione oltre i 200 ms o dipendente da un servizio esterno va in coda.
*Verifica:* revisione, metriche.

**R16.** Le code sono separate per priorità: un'importazione non ritarda una notifica.
*Verifica:* configurazione.

**R17.** Le operazioni massive procedono a blocchi, con avanzamento visibile.
*Verifica:* revisione.

---

## Frontend

**R18.** Peso JS iniziale sotto i 150 KB compressi.
*Verifica:* analisi del build.

**R19.** Immagini ottimizzate, dimensionate, differite sotto la piega.
*Verifica:* metriche di prestazione.

**R20.** `wire:model.live` solo dove serve; altrove `debounce` o `blur`.
*Verifica:* revisione.

**R21.** Cache HTTP sulle pagine pubbliche.
*Verifica:* verifica degli header.

---

## Regressioni

**R22.** Le metriche di prestazione si confrontano prima e dopo ogni rilascio.
*Verifica:* processo di rilascio.

**R23.** Un peggioramento del tempo di risposta mediano oltre il 50% impone il rollback.
*Verifica:* soglie di monitoraggio.

---

## Esempi

### Esempio 1 — misurazione prima e dopo

```
Prima:  elenco lotti (50 righe) → 101 query, 1.240 ms
Dopo:   with(['article', 'location']) → 3 query, 85 ms
Test:   verifica che le query restino sotto 15
```

### Esempio 2 — denormalizzazione con verifica

`current_quantity` sul lotto, aggiornata da un listener su `MovementRegistered`, più un ricalcolo
notturno che verifica la coerenza con la somma dei movimenti e segnala le divergenze.

Senza il ricalcolo, un evento perso produce un valore errato che nessuno rileva.

---

## Best practice

- Contare le query su ogni elenco nuovo, prima di considerarlo finito.
- Provare con volumi realistici: 50 righe non rivelano nulla.
- Aggiungere il test di regressione insieme all'ottimizzazione.
- Preferire l'indice alla cache: risolve la causa invece del sintomo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Ottimizzare senza misurare | Tempo speso senza risultati | Misurare prima |
| Misurare sul tenant piccolo | L'ottimizzazione non serve dove serve | Tenant più grande |
| Eager loading dimenticato | N+1 sugli elenchi | `with()` |
| Nessuna paginazione | Memoria esaurita con la crescita | Paginazione |
| Indici preventivi | Scritture rallentate su N tenant | Solo per query misurate |
| Cache al posto dell'indice | Sintomo curato, causa intatta | Indice |
| Denormalizzazione senza verifica | Disallineamento silenzioso | Ricalcolo periodico |
| Nessun test di regressione | L'N+1 ritorna al prossimo refactoring | Test sul numero di query |

---

## Checklist

- [ ] Misurazione eseguita prima dell'ottimizzazione.
- [ ] Verifica sul tenant con più dati.
- [ ] Eager loading su tutti gli elenchi.
- [ ] Paginazione o elaborazione a blocchi.
- [ ] Indici sulle colonne di filtro e ordinamento.
- [ ] `EXPLAIN` verificato sulle tabelle grandi.
- [ ] Aggregati in cache con chiave tenant-scoped.
- [ ] Operazioni lente in coda, code separate per priorità.
- [ ] Test che blocca la regressione sul numero di query.
- [ ] Denormalizzazioni con ricalcolo di verifica.

---

## Riferimenti

- [Guida alle prestazioni](../docs/04-quality/04-performance-guide.md)
- [SQL](sql.md) · [Cache](cache.md) · [Queue](queue.md)
- [Agente Performance](../agents/09-performance-agent.md)
- [Checklist prestazioni](../checklists/performance-checklist.md)
