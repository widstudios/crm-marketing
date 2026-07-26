# Regole — Repository e Query object

> Il Repository astrae la persistenza delle entità; il Query object incapsula le letture
> ottimizzate. Confonderli produce entrambi i problemi che dovevano risolvere.

---

## Indice

1. [Descrizione](#descrizione)
2. [La distinzione](#la-distinzione)
3. [Regole del Repository](#regole-del-repository)
4. [Regole del Query object](#regole-del-query-object)
5. [Quando il Repository non serve](#quando-il-repository-non-serve)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Il Repository esiste per una ragione sola: permettere al dominio di dichiarare **di cosa ha
bisogno** senza sapere **come viene fornito**. Non serve a «poter cambiare database»: quello non
succede quasi mai. Serve a rendere il dominio testabile senza database e a impedire che le query si
disperdano nel codice applicativo.

Il Query object risolve un problema diverso: le letture per la presentazione hanno bisogno di
proiezioni, aggregazioni e paginazione, che non appartengono al Repository.

---

## La distinzione

| | Repository | Query object |
|---|---|---|
| Ritorna | **entità** o aggregati | proiezioni, elenchi paginati, aggregazioni |
| Contratto | nel **dominio** | nessuno (è già applicativo) |
| Usato da | dominio e Action | presentazione, report |
| Metodi | `findById`, `save`, ricerche di dominio | `execute()` con parametri di filtro |
| Ottimizzazioni | nessuna, ritorna entità complete | `select`, `with`, `join`, aggregazioni |
| Collocazione | contratto in `Domain/`, impl. in `Infrastructure/` | `Application/<Context>/Queries/` |

La tensione che questa distinzione risolve: senza Query object, il Repository si riempie di metodi
come `findForListingWithArticleAndLocationPaginated()`, e non è più un'astrazione di persistenza.

---

## Regole del Repository

**R1.** Il **contratto** vive in `Domain/<Context>/Contracts/`, l'implementazione in
`Infrastructure/Repositories/`.
*Motivo:* è la direzione della dipendenza che rende il dominio indipendente.
*Verifica:* test di architettura.

**R2.** Ogni entità con logica di dominio significativa ha un Repository.
*Livello: vincolante* nel grado puro, *consigliato* nel grado pragmatico.
*Verifica:* revisione.

**R3.** I metodi ritornano entità, aggregati, `null` o `Collection` di entità. Mai array
associativi, mai risultati paginati.
*Verifica:* PHPStan sui tipi di ritorno.

**R4.** I nomi dei metodi esprimono il **linguaggio del dominio**, non SQL.

```php
// ✓ Linguaggio del dominio
public function expiringWithin(int $days): Collection;
public function availableForPicking(ArticleId $id): Collection;

// ✗ Linguaggio SQL
public function whereExpiryDateLessThan(string $date): Collection;
```

*Verifica:* revisione.

**R5.** Nessuna regola di business nel Repository.

```php
// ✗ La regola «i lotti scaduti non si prelevano» è nel repository:
//   ogni implementazione alternativa dovrà ricordarsene.
public function findForPicking(int $id): ?Batch
{
    return Batch::query()->where('expiry_date', '>', now())->find($id);
}
```

*Verifica:* revisione.

**R6.** Il Repository solleva eccezioni di **dominio**, non tecniche.

```php
public function findOrFail(BatchId $id): Batch
{
    return Batch::query()->findOr($id->value, fn () => throw BatchNotFound::withId($id));
}
```

*Verifica:* revisione.

**R7.** Nessun filtro sul tenant: la connessione attiva è già quella del tenant corrente.
*Verifica:* revisione.

**R8.** Nessuna paginazione, ordinamento di presentazione o `select` parziale.
*Motivo:* sono esigenze della presentazione, non della persistenza.
*Verifica:* revisione.

**R9.** Un Repository per aggregato, non per tabella.
*Motivo:* le tabelle di supporto di un aggregato non hanno un Repository proprio.
*Verifica:* revisione.

**R10.** L'implementazione è registrata con un binding esplicito in un service provider.
*Verifica:* test di risoluzione dal contenitore.

---

## Regole del Query object

**R11.** Il Query object vive in `Application/<Context>/Queries/`, con suffisso `Query`.
*Verifica:* test di architettura.

**R12.** Ha un solo metodo pubblico, `execute()`, con parametri di filtro tipizzati.
*Verifica:* test di architettura.

**R13.** Ritorna proiezioni: `Collection` di array o DTO, `LengthAwarePaginator`, valori scalari.
*Verifica:* revisione.

**R14.** Seleziona solo le colonne necessarie e dichiara l'eager loading.

```php
Batch::query()
    ->select(['id', 'article_id', 'number', 'expiry_date', 'quantity'])
    ->with(['article:id,code,name'])
```

*Motivo:* le letture per gli elenchi sono la parte più sollecitata dell'applicazione.
*Verifica:* test sul numero di query.

**R15.** I filtri e gli ordinamenti accettati sono a **lista bianca**.
*Motivo:* un ordinamento su colonna non indicizzata produce una scansione completa; un filtro
arbitrario è una superficie d'attacco. *Verifica:* revisione.

**R16.** Paginazione obbligatoria su ogni collezione potenzialmente grande.
*Verifica:* revisione.

**R17.** Nessuna mutazione dentro un Query object.
*Verifica:* revisione.

---

## Quando il Repository non serve

| Caso | Perché |
|---|---|
| Tabella di riferimento in sola lettura (comuni, codici ISO) | nessuna logica da proteggere |
| CRUD banale in grado pragmatico | il model Eloquent è sufficiente |
| Lettura per la presentazione | è un Query object |
| Aggregazione statistica | è un Query object |

In questi casi il Repository aggiunge indirezione senza beneficio. La scelta va dichiarata nel
README del modulo, insieme al grado di purezza.

---

## Esempi

### Esempio 1 — contratto e implementazione

```php
// Domain/Inventory/Contracts/BatchRepository.php
interface BatchRepository
{
    public function findById(BatchId $id): ?Batch;

    public function findOrFail(BatchId $id): Batch;

    /** @return Collection<int, Batch> */
    public function expiringWithin(int $days): Collection;

    public function save(Batch $batch): void;
}
```

```php
// Infrastructure/Repositories/EloquentBatchRepository.php
final readonly class EloquentBatchRepository implements BatchRepository
{
    public function findById(BatchId $id): ?Batch
    {
        return Batch::query()->find($id->value);
    }

    public function findOrFail(BatchId $id): Batch
    {
        return Batch::query()->findOr($id->value, fn () => throw BatchNotFound::withId($id));
    }

    public function expiringWithin(int $days): Collection
    {
        return Batch::query()
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('quantity', '>', 0)
            ->get();
    }

    public function save(Batch $batch): void
    {
        $batch->save();
    }
}
```

### Esempio 2 — Query object

```php
final readonly class BatchListQuery
{
    private const SORTABLE = ['number', 'expiry_date', 'quantity'];

    public function execute(
        ?BatchStatus $status = null,
        ?int $warehouseId = null,
        string $sort = 'expiry_date',
        int $perPage = 25,
    ): LengthAwarePaginator {
        return Batch::query()
            ->select(['id', 'article_id', 'warehouse_id', 'number', 'expiry_date', 'quantity', 'status'])
            ->with(['article:id,code,name', 'warehouse:id,name'])
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->orderBy(in_array($sort, self::SORTABLE, true) ? $sort : 'expiry_date')
            ->paginate(min($perPage, 100));
    }
}
```

### Esempio 3 — Repository degenerato

```php
// ✗ Non è più un'astrazione di persistenza: è un contenitore di query di presentazione
interface BatchRepository
{
    public function findForListingWithArticleAndLocationPaginated(int $perPage): LengthAwarePaginator;
    public function countGroupedByStatusForDashboard(): array;
    public function findForPickingExcludingExpired(int $articleId): Collection;
}
```

Correzione: `findById`, `expiringWithin`, `save` nel Repository; le altre tre diventano Query
object, e la regola sui lotti scaduti torna nel dominio.

---

## Best practice

- Definire il contratto partendo da ciò che serve al dominio, non da ciò che il database offre.
- Nominare i metodi con il linguaggio del committente.
- Spostare in Query object appena un metodo di Repository parla di paginazione o di colonne.
- Verificare il numero di query prodotte dai Query object con dati realistici.
- Nei test unitari sostituire il Repository con un doppio in memoria.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Contratto nell'infrastruttura | Dipendenza invertita male | Contratto nel dominio |
| Repository che ritorna array o paginatori | Confine tra livelli sfumato | Entità nel Repository, proiezioni nelle Query |
| Regola di business nel Repository | Duplicata a ogni implementazione | Regola nel dominio |
| Nomi di metodo in linguaggio SQL | Il dominio dipende dalla persistenza | Linguaggio del dominio |
| Un Repository per tabella | Astrazione senza valore | Uno per aggregato |
| Query object senza lista bianca | Scansioni complete, superficie aperta | Lista bianca |
| Query object senza `select` e `with` | N+1 e trasferimenti inutili | Colonne ed eager loading espliciti |
| Repository per una tabella di riferimento | Indirezione inutile | Model diretto |

---

## Checklist

- [ ] Il contratto del Repository è nel dominio.
- [ ] L'implementazione è in `Infrastructure/Repositories/` e registrata con un binding.
- [ ] I metodi ritornano entità, non proiezioni.
- [ ] Nessuna regola di business nel Repository.
- [ ] Nessun filtro sul tenant.
- [ ] Le letture per la presentazione sono Query object.
- [ ] I Query object dichiarano `select` ed eager loading.
- [ ] Filtri e ordinamenti a lista bianca.
- [ ] Paginazione su ogni collezione grande.
- [ ] Numero di query verificato con dati realistici.

---

## Riferimenti

- [Livello di infrastruttura](../architecture/14-infrastructure-layer.md) · [Applicativo](../architecture/13-application-layer.md)
- [Action Pattern](action-pattern.md) · [SQL](sql.md) · [Performance](performance.md)
- [Template Repository](../templates/backend/README.md)
