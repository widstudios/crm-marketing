<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Console;

use Illuminate\Console\Command;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantRepository;
use WidStudios\Foundation\Tenancy\TenantManager;

/**
 * Esegue un comando Artisan nel contesto di uno o piu' tenant.
 *
 * E' il modo corretto di far girare qualunque comando che tocchi dati di
 * dominio: un comando lanciato direttamente gira nel contesto di piattaforma,
 * dove le tabelle che cerca non esistono. Il fallimento, quando c'e', e'
 * fortunato: il caso peggiore e' un comando che trova tabelle omonime nel
 * landlord e le modifica.
 *
 *     php artisan tenants:artisan "stock:recalculate"
 *     php artisan tenants:artisan "stock:recalculate" --tenant=acme
 *     php artisan tenants:artisan "cache:clear" --chunk=20
 */
final class TenantsArtisanCommand extends Command
{
    protected $signature = 'tenants:artisan
                            {command_string : Il comando Artisan da eseguire, tra virgolette}
                            {--tenant=* : Slug dei tenant su cui eseguirlo. Senza questa opzione, tutti}
                            {--chunk= : Numero di tenant elaborati per lotto}';

    protected $description = 'Esegue un comando Artisan nel contesto di uno o piu\' tenant';

    public function handle(TenantManager $tenancy, TenantRepository $tenants): int
    {
        $commandString = (string) $this->argument('command_string');
        /** @var list<string> $only */
        $only = $this->option('tenant');

        $failures = [];
        $executed = 0;

        $run = function (Tenant $tenant) use ($commandString, &$executed): void {
            $this->line("→ [{$tenant->getSlug()}] {$commandString}");
            $this->call($commandString);
            $executed++;
        };

        if ($only !== []) {
            foreach ($only as $slug) {
                $tenant = $tenants->findBySlugOrFail($slug);

                try {
                    $tenancy->run($tenant, $run);
                } catch (\Throwable $exception) {
                    $failures[$slug] = $exception;
                }
            }
        } else {
            $chunk = $this->option('chunk');
            $failures = $tenancy->runForEach($run, $chunk === null ? null : (int) $chunk);
        }

        return $this->report($executed, $failures);
    }

    /**
     * @param  array<string, \Throwable>  $failures
     */
    private function report(int $executed, array $failures): int
    {
        $this->newLine();
        $this->info("Eseguito su {$executed} tenant.");

        if ($failures === []) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('Fallito su '.count($failures).' tenant:');

        foreach ($failures as $slug => $exception) {
            $this->line("  - {$slug}: {$exception->getMessage()}");
        }

        // Il codice di uscita e' diverso da zero anche se la maggior parte dei
        // tenant e' andata a buon fine: un comando che riporta successo con dei
        // fallimenti dentro rende inutile ogni allarme costruito su di esso.
        return self::FAILURE;
    }
}
