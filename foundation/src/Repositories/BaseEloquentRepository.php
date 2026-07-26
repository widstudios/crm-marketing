<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use WidStudios\Foundation\Contracts\Repositories\Repository;

/**
 * Implementazione Eloquent di un repository.
 *
 * Il repository non e' un modo per nascondere Eloquent: e' un modo per dare un
 * nome alle query che il dominio usa davvero. Un metodo qui dentro esiste
 * perche' un caso d'uso lo richiede, e il suo nome dice quale.
 *
 * Cio' che non appartiene a un repository:
 *
 *   - le regole di business: stanno nel dominio;
 *   - le proiezioni per la presentazione: stanno nei Query object;
 *   - i metodi generici del tipo where(), orderBy(), scope(): se il chiamante
 *     compone la query, il repository non sta astraendo nulla e la logica di
 *     lettura si sparpaglia.
 *
 * @template TModel of Model
 *
 * @implements Repository<TModel>
 */
abstract class BaseEloquentRepository implements Repository
{
    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * @return Builder<TModel>
     */
    protected function query(): Builder
    {
        return $this->model()::query();
    }

    /**
     * @return TModel|null
     */
    public function find(int|string $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * @return TModel
     *
     * @throws ModelNotFoundException<TModel>
     */
    public function findOrFail(int|string $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function exists(int|string $id): bool
    {
        return $this->query()->whereKey($id)->exists();
    }

    /**
     * Come findOrFail(), ma con un lock pessimistico.
     *
     * E' il metodo da usare quando l'operazione legge un valore, decide in base
     * a quello e poi scrive: senza lock, due richieste simultanee possono
     * entrambe superare la verifica e produrre un risultato che nessuna delle
     * due avrebbe consentito (rules/database.md R25).
     *
     * Va usato dentro una transazione: fuori, il lock viene rilasciato subito e
     * non protegge nulla.
     *
     * @return TModel
     *
     * @throws ModelNotFoundException<TModel>
     */
    public function findForUpdateOrFail(int|string $id): Model
    {
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    /**
     * @param  TModel  $entity
     */
    public function save(object $entity): void
    {
        $entity->save();
    }

    /**
     * @param  TModel  $entity
     */
    public function delete(object $entity): void
    {
        $entity->delete();
    }
}
