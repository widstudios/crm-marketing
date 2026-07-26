<?php

declare(strict_types=1);

use WidStudios\Foundation\Exceptions\MissingTenantContext;
use WidStudios\Foundation\Tenancy\Jobs\RestoreTenantContext;
use WidStudios\Foundation\Tenancy\TenantContext;

final class FakeJob
{
    public ?string $tenantKey = null;
}

it('fallisce rumorosamente quando il job non porta il tenant', function (): void {
    // Un job che perde il contesto e prosegue in silenzio scrive nel database
    // sbagliato: il fallimento e' l'unico comportamento accettabile.
    app(TenantContext::class)->forget();

    expect(fn (): mixed => (new RestoreTenantContext())->handle(new FakeJob(), fn (): string => 'eseguito'))
        ->toThrow(MissingTenantContext::class);
});

it('prosegue senza slug se un contesto e\' gia\' aperto', function (): void {
    // E' il caso della connessione 'sync': il job gira nello stesso processo
    // che lo ha accodato, quindi non c'e' nulla da ripristinare.
    app(TenantContext::class)->set(fakeTenant('acme'));

    $result = (new RestoreTenantContext())->handle(new FakeJob(), fn (): string => 'eseguito');

    expect($result)->toBe('eseguito');
});
