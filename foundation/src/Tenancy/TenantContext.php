<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy;

use Closure;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Exceptions\MissingTenantContext;

/**
 * Il tenant corrente, per la durata di una richiesta, di un job o di un
 * comando.
 *
 * E' deliberatamente stupido: conserva un riferimento e nient'altro. La
 * riconfigurazione del framework appartiene ai bootstrapper, orchestrati dal
 * TenantManager. Tenere separate le due cose permette di leggere il tenant
 * corrente senza far partire alcun effetto collaterale.
 *
 * Si ottiene dal container, non staticamente: e' una dipendenza come le altre.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * @throws MissingTenantContext quando il codice presuppone un tenant e non c'e'
     */
    public function currentOrFail(): Tenant
    {
        return $this->tenant ?? throw MissingTenantContext::inCurrentScope();
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function slug(): ?string
    {
        return $this->tenant?->getSlug();
    }

    public function is(Tenant $tenant): bool
    {
        return $this->tenant !== null && $this->tenant->getSlug() === $tenant->getSlug();
    }

    /**
     * Esegue il callback con un tenant diverso, ripristinando il precedente
     * anche in caso di eccezione.
     *
     * Non riconfigura il framework: per quello serve TenantManager::run().
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function pretend(?Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
