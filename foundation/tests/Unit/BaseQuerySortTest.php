<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use WidStudios\Foundation\Exceptions\InvalidSortColumn;
use WidStudios\Foundation\Repositories\BaseQuery;

final class SortableQuery extends BaseQuery
{
    protected array $sortable = ['expiry_date', 'number'];

    protected function builder(): Builder
    {
        throw new RuntimeException('Non usato in questo test.');
    }

    public function currentSort(): ?string
    {
        return $this->sortColumn;
    }

    public function currentDirection(): string
    {
        return $this->sortDirection;
    }
}

it('accetta le colonne in lista bianca', function (): void {
    $query = (new SortableQuery())->sortBy('expiry_date', 'desc');

    expect($query->currentSort())->toBe('expiry_date')
        ->and($query->currentDirection())->toBe('desc');
});

it('rifiuta le colonne fuori dalla lista bianca', function (): void {
    // Il nome di una colonna non e' un valore e non puo' essere legato:
    // la lista bianca e' l'unica difesa.
    expect(fn () => (new SortableQuery())->sortBy('(select password from users)'))
        ->toThrow(InvalidSortColumn::class);
});

it('elenca le colonne ammesse nel messaggio di errore', function (): void {
    expect(fn () => (new SortableQuery())->sortBy('quantity'))
        ->toThrow(InvalidSortColumn::class, 'expiry_date, number');
});

it('normalizza una direzione non riconosciuta ad ascendente', function (): void {
    $query = (new SortableQuery())->sortBy('number', 'qualunque-cosa');

    expect($query->currentDirection())->toBe('asc');
});
