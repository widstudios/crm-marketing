<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use WidStudios\Foundation\Contracts\Repositories\Query;
use WidStudios\Foundation\Exceptions\InvalidSortColumn;

/**
 * Base per i Query object: una lettura con un nome, e con i suoi filtri.
 *
 * Il valore di questa classe non e' il risparmio di righe: e' il fatto che
 * l'ordinamento passi da una lista bianca. Un ORDER BY costruito da un
 * parametro della richiesta e' una delle poche vie di injection che
 * sopravvivono ai parametri legati, perche' il nome di una colonna non e' un
 * valore e non puo' essere legato (rules/security.md R21).
 *
 *     final class ExpiringBatchesQuery extends BaseQuery
 *     {
 *         protected array $sortable = ['expiry_date', 'number'];
 *
 *         public function __construct(private readonly int $withinDays) {}
 *
 *         protected function builder(): QueryBuilder { ... }
 *     }
 *
 * @template TResult
 *
 * @implements Query<TResult>
 */
abstract class BaseQuery implements Query
{
    /**
     * Colonne per cui l'ordinamento e' ammesso. Tutto il resto viene rifiutato.
     *
     * @var list<string>
     */
    protected array $sortable = [];

    protected ?string $sortColumn = null;

    protected string $sortDirection = 'asc';

    abstract protected function builder(): QueryBuilder;

    /**
     * @throws InvalidSortColumn quando la colonna non e' in lista bianca
     */
    public function sortBy(string $column, string $direction = 'asc'): static
    {
        if (! in_array($column, $this->sortable, strict: true)) {
            throw InvalidSortColumn::for($column, $this->sortable);
        }

        $this->sortColumn = $column;
        $this->sortDirection = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    /**
     * @return Collection<int, TResult>
     */
    public function get(): Collection
    {
        /** @var Collection<int, TResult> */
        return $this->applySort($this->builder())->get();
    }

    /**
     * @param  positive-int  $perPage
     * @return LengthAwarePaginator<int, TResult>
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, TResult> */
        return $this->applySort($this->builder())->paginate($perPage);
    }

    public function count(): int
    {
        return $this->builder()->count();
    }

    protected function applySort(QueryBuilder $builder): QueryBuilder
    {
        if ($this->sortColumn !== null) {
            $builder->orderBy($this->sortColumn, $this->sortDirection);
        }

        return $builder;
    }
}
