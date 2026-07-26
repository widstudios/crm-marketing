<?php

declare(strict_types=1);

use WidStudios\Foundation\Exceptions\MissingTenantContext;
use WidStudios\Foundation\Tenancy\TenantCacheKey;
use WidStudios\Foundation\Tenancy\TenantContext;

it('compone la chiave nello spazio del tenant corrente', function (): void {
    app(TenantContext::class)->set(fakeTenant('acme'));

    expect(TenantCacheKey::for('dashboard.stats'))->toBe('ws:acme:dashboard.stats');
});

it('produce chiavi diverse per tenant diversi', function (): void {
    $keys = [];

    foreach (['acme', 'globex'] as $slug) {
        app(TenantContext::class)->set(fakeTenant($slug));
        $keys[] = TenantCacheKey::for('dashboard.stats');
    }

    expect($keys[0])->not->toBe($keys[1]);
});

it('unisce i segmenti di una chiave composta', function (): void {
    app(TenantContext::class)->set(fakeTenant('acme'));

    expect(TenantCacheKey::for(['batch', 42, 'stock']))->toBe('ws:acme:batch:42:stock');
});

it('compone le chiavi di piattaforma senza contesto tenant', function (): void {
    expect(TenantCacheKey::landlord('tenants.count'))->toBe('ws:landlord:tenants.count');
});

it('fallisce se non c\'e\' un tenant nel contesto', function (): void {
    // Comporre una chiave senza tenant significherebbe scrivere in uno spazio
    // condiviso: e' un difetto, non un caso previsto.
    app(TenantContext::class)->forget();

    expect(fn (): string => TenantCacheKey::for('dashboard.stats'))
        ->toThrow(MissingTenantContext::class);
});
