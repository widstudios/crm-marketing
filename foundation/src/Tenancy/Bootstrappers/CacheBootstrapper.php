<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Bootstrappers;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository as Config;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantBootstrapper;

/**
 * Separa lo spazio di cache dei tenant.
 *
 * A differenza del database, qui la struttura non protegge nulla: Redis e' uno
 * solo, e due tenant che usano la stessa chiave leggono lo stesso valore. La
 * separazione esiste solo se il codice la produce.
 *
 * Questo bootstrapper e' la rete di sicurezza: sposta il prefisso dello store
 * sotto lo slug del tenant, cosi' che anche una chiave scritta a mano finisca
 * in uno spazio separato. Non sostituisce TenantCacheKey, che resta il modo
 * corretto di comporre una chiave: lo store puo' essere sostituito, un array
 * store nei test non ha prefissi, e la disciplina esplicita e' verificabile
 * mentre una configurazione non lo e'.
 */
final class CacheBootstrapper implements TenantBootstrapper
{
    /** @var array<string, string> prefissi originali, per store */
    private array $originalPrefixes = [];

    public function __construct(
        private readonly CacheManager $cache,
        private readonly Config $config,
    ) {}

    public function bootstrap(Tenant $tenant): void
    {
        $store = (string) $this->config->get('cache.default');
        $key = "cache.stores.{$store}.prefix";

        $this->originalPrefixes[$store] ??= (string) $this->config->get($key, '');

        $this->config->set($key, $this->originalPrefixes[$store].'_'.$tenant->getSlug());

        $this->cache->forgetDriver($store);
    }

    public function revert(): void
    {
        foreach ($this->originalPrefixes as $store => $prefix) {
            $this->config->set("cache.stores.{$store}.prefix", $prefix);
            $this->cache->forgetDriver($store);
        }

        $this->originalPrefixes = [];
    }
}
