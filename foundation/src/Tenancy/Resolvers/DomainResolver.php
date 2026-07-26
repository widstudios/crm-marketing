<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Resolvers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantRepository;
use WidStudios\Foundation\Contracts\Tenancy\TenantResolver;

/**
 * Deriva il tenant dal dominio della richiesta.
 *
 * E' il resolver di produzione, ed e' l'unico che soddisfa la regola per cui il
 * tenant deve venire da cio' che il client non controlla: il dominio e' scelto
 * dal DNS e dal certificato, non da chi invia la richiesta.
 *
 * I domini centrali (il pannello di piattaforma) non appartengono a nessun
 * tenant: su quelli il resolver restituisce null, ed e' corretto che sia cosi'.
 */
final class DomainResolver implements TenantResolver
{
    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly Config $config,
    ) {}

    public function resolve(Request $request): ?Tenant
    {
        $host = strtolower($request->getHost());

        /** @var list<string> $central */
        $central = $this->config->get('foundation.tenancy.central_domains', []);

        if (in_array($host, array_map(strtolower(...), $central), strict: true)) {
            return null;
        }

        return $this->tenants->findByDomain($host);
    }
}
