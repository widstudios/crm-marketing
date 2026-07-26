<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Concerns;

use Closure;
use Illuminate\Support\Facades\Cache;
use WidStudios\Foundation\Tenancy\TenantCacheKey;

/**
 * Accesso alla cache con chiavi gia' nello spazio del tenant.
 *
 * Esiste per togliere di mezzo l'unico modo in cui la regola sulle chiavi viene
 * violata: la distrazione. Chi usa questi metodi non puo' scrivere una chiave
 * condivisa nemmeno volendo.
 *
 *     $stats = $this->tenantRemember('dashboard.stats', 300, fn () => $this->compute());
 *     $this->tenantForget('dashboard.stats');
 */
trait InteractsWithTenantCache
{
    /**
     * @template TValue
     *
     * @param  string|array<int, string|int>  $key
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    protected function tenantRemember(string|array $key, ?int $ttl, Closure $callback): mixed
    {
        $ttl ??= (int) config('foundation.cache.default_ttl', 3600);

        return Cache::remember(TenantCacheKey::for($key), $ttl, $callback);
    }

    /**
     * @param  string|array<int, string|int>  $key
     */
    protected function tenantForget(string|array $key): void
    {
        Cache::forget(TenantCacheKey::for($key));
    }

    /**
     * @param  string|array<int, string|int>  $key
     */
    protected function tenantHas(string|array $key): bool
    {
        return Cache::has(TenantCacheKey::for($key));
    }
}
