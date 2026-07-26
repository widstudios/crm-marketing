<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Contracts\Repositories;

/**
 * Accesso alle entita' di un aggregato.
 *
 * Un repository restituisce entita', non righe: se il chiamante ha bisogno di
 * una proiezione (elenco, esportazione, aggregato), il posto giusto e' un
 * Query object, non un metodo in piu' qui (rules/repository-pattern.md).
 *
 * Nessuna regola di business dentro un repository: il repository sa dove sono
 * i dati, non che cosa se ne fa.
 *
 * @template TEntity of object
 */
interface Repository
{
    /**
     * @return TEntity|null
     */
    public function find(int|string $id): ?object;

    /**
     * @return TEntity
     */
    public function findOrFail(int|string $id): object;

    public function exists(int|string $id): bool;

    /**
     * @param  TEntity  $entity
     */
    public function save(object $entity): void;

    /**
     * @param  TEntity  $entity
     */
    public function delete(object $entity): void;
}
