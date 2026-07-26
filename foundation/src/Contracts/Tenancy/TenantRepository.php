<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Contracts\Tenancy;

use WidStudios\Foundation\Exceptions\TenantNotFound;

/**
 * Accesso ai tenant nel database di piattaforma.
 *
 * Vive nel landlord per definizione: e' l'unico punto in cui la Foundation
 * interroga dati che non appartengono a un singolo cliente.
 */
interface TenantRepository
{
    public function findBySlug(string $slug): ?Tenant;

    /**
     * @throws TenantNotFound
     */
    public function findBySlugOrFail(string $slug): Tenant;

    public function findByDomain(string $domain): ?Tenant;

    /**
     * Tutti i tenant, in ordine di slug.
     *
     * @return iterable<int, Tenant>
     */
    public function all(): iterable;

    /**
     * I soli tenant che possono ricevere traffico ed elaborazioni.
     *
     * @return iterable<int, Tenant>
     */
    public function active(): iterable;

    /**
     * Scorre i tenant a lotti, senza caricarli tutti in memoria.
     *
     * @param  positive-int  $size
     * @param  callable(iterable<int, Tenant>): void  $callback
     */
    public function chunk(int $size, callable $callback): void;
}
