<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Resolvers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantRepository;
use WidStudios\Foundation\Contracts\Tenancy\TenantResolver;

/**
 * Deriva il tenant da un header della richiesta.
 *
 * E' una comodita' di sviluppo e di test: permette di colpire un tenant senza
 * configurare un dominio locale.
 *
 * Fuori dagli ambienti dichiarati in configurazione restituisce sempre null,
 * senza eccezioni e senza modo di disattivare il controllo: un header e' dato
 * dal client, e in produzione significherebbe che chiunque puo' scegliere di
 * quale cliente leggere i dati (rules/security.md R7).
 *
 * La verifica e' nel codice e non solo nella configurazione, perche' una
 * configurazione sbagliata in produzione e' un errore plausibile, mentre
 * modificare questa classe non lo e'.
 */
final class HeaderResolver implements TenantResolver
{
    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly Config $config,
        private readonly Application $app,
    ) {}

    public function resolve(Request $request): ?Tenant
    {
        if (! $this->isAllowedEnvironment()) {
            return null;
        }

        $header = (string) $this->config->get('foundation.tenancy.header_name', 'X-Tenant');
        $slug = $request->header($header);

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return $this->tenants->findBySlug($slug);
    }

    private function isAllowedEnvironment(): bool
    {
        /** @var list<string> $allowed */
        $allowed = $this->config->get('foundation.tenancy.header_resolver_environments', []);

        return in_array($this->app->environment(), $allowed, strict: true);
    }
}
