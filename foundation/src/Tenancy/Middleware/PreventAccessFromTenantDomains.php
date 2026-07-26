<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chiude le rotte di piattaforma ai domini dei tenant.
 *
 * Senza questo middleware il pannello Super Admin risponde anche su
 * acme.example.com: l'autorizzazione lo protegge, ma la sua sola esistenza su
 * un dominio cliente e' un'informazione che non ha motivo di essere data.
 */
final class PreventAccessFromTenantDomains
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $central */
        $central = $this->config->get('foundation.tenancy.central_domains', []);

        if (! in_array(strtolower($request->getHost()), array_map(strtolower(...), $central), strict: true)) {
            abort(404);
        }

        return $next($request);
    }
}
