<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Console;

use Illuminate\Console\Command;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantRepository;
use WidStudios\Foundation\Tenancy\TenantManager;

/**
 * Applica le migration tenant su tutti i database dei clienti.
 *
 * Riporta la durata per tenant, non solo quella totale: e' il numero che serve
 * per stimare la finestra di rilascio, e l'unico modo di accorgersi in tempo
 * che una migration che dura tre secondi su un tenant di prova ne dura
 * quaranta su quello grande.
 *
 *     php artisan tenants:migrate --chunk=50
 *     php artisan tenants:migrate --tenant=acme --pretend
 */
final class TenantsMigrateCommand extends Command
{
    protected $signature = 'tenants:migrate
                            {--tenant=* : Slug dei tenant da migrare. Senza questa opzione, tutti}
                            {--chunk= : Numero di tenant elaborati per lotto}
                            {--pretend : Mostra le query senza eseguirle}
                            {--force : Esegue in produzione senza conferma}';

    protected $description = 'Applica le migration tenant sui database dei clienti';

    public function handle(TenantManager $tenancy, TenantRepository $tenants): int
    {
        /** @var list<string> $only */
        $only = $this->option('tenant');
        $durations = [];

        $migrate = function (Tenant $tenant) use (&$durations): void {
            $started = microtime(true);

            $this->call('migrate', array_filter([
                '--database' => (string) config('foundation.tenancy.tenant_connection'),
                '--path' => 'database/migrations/tenant',
                '--pretend' => (bool) $this->option('pretend'),
                '--force' => true,
            ]));

            $durations[$tenant->getSlug()] = round(microtime(true) - $started, 2);
        };

        $failures = [];

        if ($only !== []) {
            foreach ($only as $slug) {
                try {
                    $tenancy->run($tenants->findBySlugOrFail($slug), $migrate);
                } catch (\Throwable $exception) {
                    $failures[$slug] = $exception;
                }
            }
        } else {
            $chunk = $this->option('chunk');
            $failures = $tenancy->runForEach($migrate, $chunk === null ? null : (int) $chunk);
        }

        $this->renderDurations($durations);

        if ($failures !== []) {
            $this->error('Migration fallite su '.count($failures).' tenant:');

            foreach ($failures as $slug => $exception) {
                $this->line("  - {$slug}: {$exception->getMessage()}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, float>  $durations
     */
    private function renderDurations(array $durations): void
    {
        if ($durations === []) {
            return;
        }

        arsort($durations);
        $total = array_sum($durations);

        $this->newLine();
        $this->info(sprintf(
            'Migrati %d tenant in %.2f s (media %.2f s, massimo %.2f s).',
            count($durations),
            $total,
            $total / count($durations),
            (float) reset($durations),
        ));

        $this->line('I cinque tenant piu\' lenti:');

        foreach (array_slice($durations, 0, 5, preserve_keys: true) as $slug => $seconds) {
            $this->line(sprintf('  %-30s %6.2f s', $slug, $seconds));
        }
    }
}
