<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy;

use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Exceptions\MissingTenantContext;

/**
 * Compone le chiavi di cache in modo che appartengano a un tenant preciso.
 *
 * Esiste perche' la cache e' condivisa: due tenant che scrivono
 * Cache::put('dashboard.stats', ...) scrivono nella stessa casella, e il
 * secondo legge i numeri del primo. Non e' un errore che si nota: i dati sono
 * plausibili, semplicemente non sono i suoi.
 *
 * Nel codice applicativo non deve comparire nessuna stringa passata
 * direttamente a Cache::: la chiave si compone sempre qui. E' una regola
 * verificabile con una ricerca, ed e' verificata in pipeline.
 *
 *     Cache::remember(TenantCacheKey::for('dashboard.stats'), 300, $callback);
 *     Cache::forget(TenantCacheKey::for('dashboard.stats'));
 */
final class TenantCacheKey
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * Chiave nello spazio del tenant corrente.
     *
     * @param  string|array<int, string|int>  $key  i segmenti vengono uniti dal separatore
     *
     * @throws MissingTenantContext quando non c'e' un tenant: e' un difetto, non un caso previsto
     */
    public function make(string|array $key): string
    {
        return $this->compose($this->context->currentOrFail()->getSlug(), $key);
    }

    /**
     * @param  string|array<int, string|int>  $key
     */
    public function makeFor(Tenant $tenant, string|array $key): string
    {
        return $this->compose($tenant->getSlug(), $key);
    }

    /**
     * Chiave nello spazio di piattaforma.
     *
     * Da usare solo per dati che non appartengono a nessun cliente: elenco dei
     * tenant, configurazione globale, stato dei moduli.
     *
     * @param  string|array<int, string|int>  $key
     */
    public function makeLandlord(string|array $key): string
    {
        return $this->compose(
            (string) config('foundation.cache.landlord_scope', 'landlord'),
            $key,
        );
    }

    /**
     * Facciata statica, per la leggibilita' nei punti di chiamata.
     *
     * @param  string|array<int, string|int>  $key
     */
    public static function for(string|array $key): string
    {
        return app(self::class)->make($key);
    }

    /**
     * @param  string|array<int, string|int>  $key
     */
    public static function landlord(string|array $key): string
    {
        return app(self::class)->makeLandlord($key);
    }

    /**
     * @param  string|array<int, string|int>  $key
     */
    private function compose(string $scope, string|array $key): string
    {
        $separator = (string) config('foundation.cache.separator', ':');
        $prefix = (string) config('foundation.cache.prefix', 'ws');

        $segments = is_array($key)
            ? array_map(strval(...), $key)
            : [$key];

        return implode($separator, [$prefix, $scope, ...$segments]);
    }
}
