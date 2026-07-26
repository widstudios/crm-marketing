# Foundation — Repository e Query object

> Perché le letture per la presentazione non appartengono ai repository, e come si evita
> l'injection sugli ordinamenti.

---

## Indice

1. [Descrizione](#descrizione)
2. [Repository](#repository)
3. [Query object](#query-object)
4. [Ordinamenti in lista bianca](#ordinamenti-in-lista-bianca)
5. [Come si sceglie tra i due](#come-si-sceglie-tra-i-due)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Il repository non esiste per nascondere Eloquent. Nasconderlo non serve a nulla: nessun progetto
della Factory cambierà ORM, e un'astrazione costruita per un evento che non accadrà è solo un
livello in più da attraversare.

Esiste per **dare un nome alle query che il dominio usa davvero**. Un metodo in un repository esiste
perché un caso d'uso lo richiede, e il suo nome dice quale. Quando il repository si riempie di
metodi generici — `where()`, `orderBy()`, `withRelations()` — non sta astraendo niente, e la logica
di lettura torna a sparpagliarsi nei chiamanti.

---

## Repository

```php
final class BatchRepository extends BaseEloquentRepository
{
    protected function model(): string
    {
        return Batch::class;
    }

    public function findByNumber(string $number): ?Batch
    {
        return $this->query()->where('number', $number)->first();
    }

    public function expiringWithin(int $days): Collection
    {
        return $this->query()
            ->where('status', BatchStatus::Available)
            ->whereBetween('expiry_date', [now(), now()->addDays($days)])
            ->get();
    }
}
```

Fornito da `BaseEloquentRepository`: `find()`, `findOrFail()`, `exists()`, `save()`, `delete()` e
`findForUpdateOrFail()`.

Quest'ultimo è il metodo che vale la classe base: applica un lock pessimistico, e va usato dentro
una transazione ogni volta che l'operazione legge un valore, decide in base a quello e poi scrive.
Fuori da una transazione il lock viene rilasciato subito e non protegge nulla.

Ciò che **non** appartiene a un repository:

| Non appartiene | Dove va |
|---|---|
| Regole di business | nel dominio |
| Proiezioni per la presentazione | in un Query object |
| Metodi generici (`where`, `scope`, `orderBy`) | in nessun posto: il chiamante non compone la query |
| Paginazione di elenchi con filtri | in un Query object |

---

## Query object

Una lettura con un nome, e con i suoi filtri.

```php
final class ExpiringBatchesQuery extends BaseQuery
{
    protected array $sortable = ['expiry_date', 'number', 'quantity'];

    public function __construct(
        private readonly int $withinDays,
        private readonly ?int $articleId = null,
    ) {}

    protected function builder(): Builder
    {
        return DB::table('batches')
            ->join('articles', 'articles.id', '=', 'batches.article_id')
            ->select([
                'batches.id',
                'batches.number',
                'batches.expiry_date',
                'batches.quantity',
                'articles.name as article_name',
            ])
            ->where('batches.status', BatchStatus::Available->value)
            ->whereBetween('batches.expiry_date', [now(), now()->addDays($this->withinDays)])
            ->when($this->articleId, fn (Builder $q, int $id): Builder => $q->where('batches.article_id', $id));
    }
}
```

Uso:

```php
$rows = (new ExpiringBatchesQuery(withinDays: 30))
    ->sortBy($request->string('sort', 'expiry_date')->toString(), $request->string('dir')->toString())
    ->paginate(25);
```

Le colonne sono **esplicite**: un `SELECT *` trasferisce dati inutili, e una colonna aggiunta domani
cambia silenziosamente la risposta di un'API.

---

## Ordinamenti in lista bianca

È la ragione principale per cui `BaseQuery` esiste.

```php
$builder->orderBy($request->input('sort'));   // ✗
```

Un `ORDER BY` costruito da un parametro della richiesta è una delle poche vie di injection che
sopravvivono ai parametri legati: il nome di una colonna **non è un valore**, e non può essere
legato. Il query builder lo inserisce nella query come identificatore.

```php
protected array $sortable = ['expiry_date', 'number', 'quantity'];
```

`sortBy()` rifiuta tutto ciò che non è in elenco, con `InvalidSortColumn`, il cui messaggio elenca
le colonne ammesse — sono già pubbliche per chi usa l'elenco, e senza l'elenco l'errore non è
correggibile da chi lo riceve.

Una lista nera non funziona: l'insieme dei valori pericolosi è aperto, quello dei valori ammessi è
finito e noto.

---

## Come si sceglie tra i due

| Domanda | Repository | Query object |
|---|---|---|
| Che cosa restituisce? | entità del dominio | righe per la presentazione |
| Chi lo usa? | Action, servizi | controller, Filament, API, esportazioni |
| Il chiamante ci scrive sopra? | sì | no, mai |
| Ha filtri combinabili? | no | sì |
| Ha ordinamento dall'esterno? | no | sì, in lista bianca |

Una lettura serve a decidere qualcosa nel dominio → repository.
Una lettura finisce su uno schermo o in un file → Query object.

---

## Esempi

### Il repository nel percorso di scrittura

```php
public function execute(RegisterMovementData $data): StockMovement
{
    return $this->transaction(function () use ($data): StockMovement {
        $batch = $this->batches->findForUpdateOrFail($data->batchId);   // entità, con lock
        $batch->assertCanRelease($data->quantity);

        $movement = $batch->release($data->quantity);
        $this->batches->save($batch);

        return $movement;
    });
}
```

### Il Query object nel percorso di lettura

```php
final class BatchController
{
    public function index(IndexBatchesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Batch::class);

        $batches = (new ExpiringBatchesQuery(
            withinDays: $request->integer('within_days', 30),
            articleId: $request->integer('article_id') ?: null,
        ))
            ->sortBy($request->string('sort', 'expiry_date')->toString())
            ->paginate($request->integer('per_page', 25));

        return BatchResource::collection($batches)->response();
    }
}
```

I due percorsi non condividono codice, e va bene così: hanno esigenze diverse, e farli convivere in
una classe sola produce il repository con quaranta metodi che nessuno riesce più a leggere.

---

## Best practice

- Un metodo in un repository esiste perché un caso d'uso lo richiede: il nome dice quale.
- `findForUpdateOrFail()` ogni volta che si legge, si decide e si scrive.
- Colonne esplicite nei Query object: mai `SELECT *` per la presentazione.
- Lista bianca sugli ordinamenti, anche quando le colonne ammesse sono molte.
- Se un repository supera i dieci metodi, quasi sempre contiene proiezioni che appartengono ai Query
  object.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Metodi generici nel repository | La logica di lettura si sparpaglia | Metodi con un nome di dominio |
| Regole di business nel repository | Non applicate quando l'entità serve altrove | Nel dominio |
| Proiezioni nel repository | Cresce senza confine | Query object |
| `ORDER BY` da parametro | Injection che i parametri legati non fermano | `sortBy()` con lista bianca |
| `SELECT *` per la presentazione | Trasferimento inutile, risposte che cambiano da sole | Colonne esplicite |
| `findOrFail()` dove serve il lock | Giacenze negative sotto concorrenza | `findForUpdateOrFail()` |
| Lock fuori dalla transazione | Rilasciato subito, non protegge nulla | Dentro `transaction()` |

---

## Checklist

- [ ] Ogni metodo del repository corrisponde a un caso d'uso.
- [ ] Nessun metodo generico che fa comporre la query al chiamante.
- [ ] Nessuna regola di business nei repository.
- [ ] Le proiezioni sono Query object.
- [ ] Colonne esplicite in ogni Query object.
- [ ] Ordinamenti in lista bianca.
- [ ] `findForUpdateOrFail()` dove c'è contesa, dentro una transazione.

---

## Riferimenti

- [Repository Pattern](../../rules/repository-pattern.md) · [SQL](../../rules/sql.md) · [Sicurezza](../../rules/security.md)
- [Livello infrastruttura](../../architecture/14-infrastructure-layer.md)
- [Action e DTO](03-action-e-dto.md)
