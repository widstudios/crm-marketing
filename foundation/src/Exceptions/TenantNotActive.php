<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Exceptions;

use WidStudios\Foundation\Contracts\Tenancy\Tenant;

/**
 * Il tenant esiste ma non puo' ricevere traffico.
 *
 * A differenza di TenantNotFound, qui l'esistenza e' gia' nota a chi sta
 * chiedendo — e' il suo cliente — quindi dire perche' l'accesso e' sospeso e'
 * legittimo e utile.
 */
final class TenantNotActive extends FoundationException
{
    public static function for(Tenant $tenant): self
    {
        return new self(sprintf(
            "Il tenant '%s' non e' attivo (stato: %s).",
            $tenant->getSlug(),
            $tenant->getStatus()->value,
        ));
    }
}
