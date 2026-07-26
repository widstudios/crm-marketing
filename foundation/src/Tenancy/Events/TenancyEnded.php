<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Events;

final readonly class TenancyEnded
{
    public function __construct(
        public string $tenantSlug,
    ) {}
}
