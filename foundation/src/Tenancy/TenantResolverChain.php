<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantResolver;

/**
 * Applica i resolver configurati, in ordine: vince il primo che riconosce la
 * richiesta.
 *
 * L'ordine e' significativo e appartiene alla configurazione, non al codice: un
 * progetto che aggiunge la risoluzione da token la mette dove gli serve senza
 * modificare la Foundation.
 */
final class TenantResolverChain implements TenantResolver
{
    /** @var list<TenantResolver>|null */
    private ?array $resolvers = null;

    public function __construct(
        private readonly Container $container,
    ) {}

    public function resolve(Request $request): ?Tenant
    {
        foreach ($this->resolvers() as $resolver) {
            $tenant = $resolver->resolve($request);

            if ($tenant !== null) {
                return $tenant;
            }
        }

        return null;
    }

    /**
     * @return list<TenantResolver>
     */
    private function resolvers(): array
    {
        if ($this->resolvers !== null) {
            return $this->resolvers;
        }

        /** @var list<class-string<TenantResolver>> $classes */
        $classes = config('foundation.tenancy.resolvers', []);

        return $this->resolvers = array_map(
            fn (string $class): TenantResolver => $this->container->make($class),
            $classes,
        );
    }
}
