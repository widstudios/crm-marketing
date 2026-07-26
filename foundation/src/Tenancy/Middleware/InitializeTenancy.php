<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WidStudios\Foundation\Contracts\Tenancy\TenantResolver;
use WidStudios\Foundation\Exceptions\TenantNotFound;
use WidStudios\Foundation\Tenancy\TenantManager;

/**
 * Apre il contesto tenant per la richiesta in corso.
 *
 * Va applicato a tutte le rotte del tenant, e a nessuna rotta di piattaforma.
 * Una richiesta che arriva su un dominio non riconosciuto non prosegue senza
 * tenant: si ferma con 404. Proseguire significherebbe eseguire query sul
 * database di piattaforma con codice scritto per un tenant.
 */
final class InitializeTenancy
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantManager $tenancy,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        if ($tenant === null) {
            throw TenantNotFound::forHost($request->getHost());
        }

        $this->tenancy->initializeForRequest($tenant);

        return $next($request);
    }
}
