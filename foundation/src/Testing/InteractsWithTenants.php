<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Testing;

use Closure;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantRepository;
use WidStudios\Foundation\Tenancy\TenantContext;
use WidStudios\Foundation\Tenancy\TenantManager;

/**
 * Aiuti per i test che coinvolgono piu' tenant.
 *
 * Esistono per rendere banale il test che nessuno scrive volentieri: quello che
 * crea due clienti e verifica che non si vedano. Se scriverlo costa venti
 * righe, non viene scritto; se costa tre, viene scritto per ogni entita'.
 *
 *     it('non espone i movimenti di un altro tenant', function (): void {
 *         $acme = $this->tenant('acme');
 *         $globex = $this->tenant('globex');
 *
 *         $this->forTenant($acme, fn () => StockMovement::factory()->count(5)->create());
 *
 *         $this->forTenant($globex, function (): void {
 *             expect(StockMovement::query()->count())->toBe(0);
 *         });
 *     });
 */
trait InteractsWithTenants
{
    /**
     * Recupera un tenant per slug, fallendo con un messaggio comprensibile se
     * l'impianto del test non lo ha creato.
     */
    protected function tenant(string $slug): Tenant
    {
        return app(TenantRepository::class)->findBySlugOrFail($slug);
    }

    /**
     * @template TReturn
     *
     * @param  Closure(Tenant): TReturn  $callback
     * @return TReturn
     */
    protected function forTenant(Tenant|string $tenant, Closure $callback): mixed
    {
        $tenant = is_string($tenant) ? $this->tenant($tenant) : $tenant;

        return app(TenantManager::class)->run($tenant, $callback);
    }

    /**
     * Esegue il callback nel contesto di piattaforma, ripristinando poi quello
     * precedente.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function asLandlord(Closure $callback): mixed
    {
        $tenancy = app(TenantManager::class);
        $previous = app(TenantContext::class)->current();

        $tenancy->end();

        try {
            return $callback();
        } finally {
            if ($previous !== null) {
                $tenancy->initialize($previous);
            }
        }
    }

    /**
     * Verifica che due tenant non vedano i dati l'uno dell'altro per un dato
     * conteggio.
     *
     * @param  Closure(): void  $seed  eseguito nel contesto del primo tenant
     * @param  Closure(): int  $count  eseguito nel contesto del secondo
     */
    protected function assertTenantsAreIsolated(
        Tenant|string $first,
        Tenant|string $second,
        Closure $seed,
        Closure $count,
    ): void {
        $this->forTenant($first, $seed);

        $visible = $this->forTenant($second, $count);

        \PHPUnit\Framework\Assert::assertSame(
            0,
            $visible,
            'Il secondo tenant vede dati creati dal primo: isolamento violato.',
        );
    }
}
