<?php

declare(strict_types=1);

use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Tenancy\TenantStatus;
use WidStudios\Foundation\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Un tenant minimo, senza database: serve a verificare cio' che dipende dallo
 * slug e non dalla persistenza.
 */
function fakeTenant(string $slug): Tenant
{
    return new class($slug) implements Tenant
    {
        public function __construct(private readonly string $slug) {}

        public function getKey(): int|string
        {
            return 1;
        }

        public function getSlug(): string
        {
            return $this->slug;
        }

        public function getDatabaseName(): string
        {
            return 'tenant_'.$this->slug;
        }

        public function getDomains(): array
        {
            return [$this->slug.'.example.test'];
        }

        public function getStatus(): TenantStatus
        {
            return TenantStatus::Active;
        }
    };
}
