<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Una lettura con un nome.
 *
 * I Query object esistono per separare cio' che si legge da cio' che si scrive:
 * le proiezioni hanno esigenze diverse dalle entita' (colonne selezionate,
 * join, aggregazioni) e farle convivere in un repository lo trasforma in un
 * contenitore di metodi senza confine.
 *
 * @template TResult
 */
interface Query
{
    /**
     * @return Collection<int, TResult>
     */
    public function get(): Collection;

    /**
     * @param  positive-int  $perPage
     * @return LengthAwarePaginator<int, TResult>
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator;

    public function count(): int;
}
