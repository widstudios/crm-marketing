<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Events;

/**
 * Il contesto tenant si e' aperto.
 *
 * Trasporta lo slug, non il model: un evento e' un fatto avvenuto, e chi lo
 * riceve puo' ricaricare cio' che gli serve. Vedi rules/events.md.
 */
final readonly class TenancyInitialized
{
    public function __construct(
        public string $tenantSlug,
    ) {}
}
