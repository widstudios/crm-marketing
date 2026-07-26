<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Contracts\Tenancy;

use Illuminate\Http\Request;

/**
 * Deriva il tenant dalla richiesta in ingresso.
 *
 * Regola assoluta: il tenant si deriva da cio' che il client non controlla
 * (dominio, token), mai da un parametro che puo' modificare
 * (rules/security.md R1).
 *
 * Un resolver che non riconosce la richiesta restituisce null: la decisione su
 * cosa fare in assenza di tenant non gli appartiene.
 */
interface TenantResolver
{
    public function resolve(Request $request): ?Tenant;
}
