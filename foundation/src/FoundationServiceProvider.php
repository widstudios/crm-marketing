<?php

declare(strict_types=1);

namespace WidStudios\Foundation;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use WidStudios\Foundation\Concerns\TenantAware;
use WidStudios\Foundation\Contracts\Audit\AuditLogger;
use WidStudios\Foundation\Contracts\Modules\ModuleRegistry;
use WidStudios\Foundation\Contracts\Support\Clock;
use WidStudios\Foundation\Contracts\Tenancy\TenantResolver;
use WidStudios\Foundation\Modules\ModuleManager;
use WidStudios\Foundation\Support\SystemClock;
use WidStudios\Foundation\Tenancy\Console\TenantsArtisanCommand;
use WidStudios\Foundation\Tenancy\Console\TenantsListCommand;
use WidStudios\Foundation\Tenancy\Console\TenantsMigrateCommand;
use WidStudios\Foundation\Tenancy\TenantCacheKey;
use WidStudios\Foundation\Tenancy\TenantContext;
use WidStudios\Foundation\Tenancy\TenantManager;
use WidStudios\Foundation\Tenancy\TenantResolverChain;

/**
 * Registrazione della Foundation nel container dell'applicazione.
 *
 * Cio' che manca qui e' significativo quanto cio' che c'e': la Foundation non
 * registra middleware sulle rotte, non pubblica migration proprie e non
 * dichiara il model del tenant. Sono decisioni del progetto, e prenderle qui
 * significherebbe imporre a tutti la forma del primo progetto che ne ha avuto
 * bisogno.
 *
 * L'unico binding che il progetto deve fornire e' TenantRepository: la
 * Foundation non conosce il model con cui i tenant sono rappresentati.
 */
final class FoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/foundation.php', 'foundation');

        // Il contesto e' unico per processo: due istanze significherebbero due
        // idee diverse di quale tenant sia quello corrente.
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(TenantManager::class);
        $this->app->singleton(TenantCacheKey::class);

        $this->app->singleton(TenantResolver::class, TenantResolverChain::class);
        $this->app->singleton(ModuleRegistry::class, ModuleManager::class);

        $this->app->bind(Clock::class, SystemClock::class);

        $this->app->bind(AuditLogger::class, function (): AuditLogger {
            /** @var class-string<AuditLogger> $class */
            $class = config('foundation.audit.logger');

            return $this->app->make($class);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/foundation.php' => config_path('foundation.php'),
        ], 'foundation-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TenantsArtisanCommand::class,
                TenantsListCommand::class,
                TenantsMigrateCommand::class,
            ]);
        }

        $this->registerTenantAwareQueue();
    }

    /**
     * Fa in modo che ogni job accodato porti con se' il tenant di origine.
     *
     * Due meccanismi, complementari:
     *
     *   1. i job che usano il trait TenantAware ricevono lo slug in una
     *      proprieta' serializzata insieme al job, ed e' quella che il middleware
     *      RestoreTenantContext legge all'esecuzione;
     *   2. lo stesso slug viene aggiunto al payload della coda, dove serve alla
     *      diagnosi: il payload e' cio' che si legge nella tabella dei job
     *      falliti, quando bisogna capire quale cliente e' stato colpito.
     *
     * Il primo e' il meccanismo; il secondo e' la tracciabilita'. Servono
     * entrambi, e sono deliberatamente indipendenti: un job che perde il trait
     * resta comunque diagnosticabile.
     */
    private function registerTenantAwareQueue(): void
    {
        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);

        $events->listen(JobQueueing::class, function (JobQueueing $event): void {
            $job = $event->job;

            if (! is_object($job)) {
                return;
            }

            if (! in_array(TenantAware::class, $this->traitsOf($job::class), strict: true)) {
                return;
            }

            /** @var object{captureTenantContext: callable} $job */
            $job->captureTenantContext($this->app->make(TenantContext::class));
        });

        Queue::createPayloadUsing(function (): array {
            $slug = $this->app->make(TenantContext::class)->slug();
            $key = (string) config('foundation.queue.payload_key', 'tenant');

            return $slug === null ? [] : [$key => $slug];
        });
    }

    /**
     * Tutti i trait usati da una classe, comprese le classi padre e i trait
     * annidati.
     *
     * @param  class-string  $class
     * @return list<string>
     */
    private function traitsOf(string $class): array
    {
        $traits = [];

        for ($current = $class; $current !== false; $current = get_parent_class($current)) {
            $traits += class_uses($current) ?: [];
        }

        foreach ($traits as $trait) {
            $traits += class_uses($trait) ?: [];
        }

        return array_values($traits);
    }
}
