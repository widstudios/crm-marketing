<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Jobs;

use Closure;
use WidStudios\Foundation\Exceptions\MissingTenantContext;
use WidStudios\Foundation\Tenancy\TenantContext;
use WidStudios\Foundation\Tenancy\TenantManager;

/**
 * Job middleware che riapre il contesto tenant prima di handle().
 *
 * Viene applicato automaticamente da ogni job che usa il trait TenantAware.
 *
 * Sulla connessione 'sync' il job viene eseguito nello stesso processo che lo
 * ha accodato, quindi il contesto e' gia' aperto e non c'e' nulla da
 * ripristinare: in quel caso la mancanza dello slug non e' un errore. In tutti
 * gli altri casi lo e', e il job fallisce senza eseguire nulla.
 */
final class RestoreTenantContext
{
    /**
     * @param  object{tenantKey?: string|null}  $job
     */
    public function handle(object $job, Closure $next): mixed
    {
        $context = app(TenantContext::class);
        $tenancy = app(TenantManager::class);

        $slug = $job->tenantKey ?? null;

        if ($slug === null) {
            if ($context->has()) {
                return $next($job);
            }

            throw MissingTenantContext::forJob($job::class);
        }

        if ($context->slug() === $slug) {
            return $next($job);
        }

        $tenancy->initializeBySlug($slug);

        try {
            return $next($job);
        } finally {
            $tenancy->end();
        }
    }
}
