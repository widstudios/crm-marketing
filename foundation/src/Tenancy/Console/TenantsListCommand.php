<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Console;

use Illuminate\Console\Command;
use WidStudios\Foundation\Contracts\Tenancy\TenantRepository;

/**
 * Elenca i tenant registrati, con stato e database.
 *
 * E' il primo comando che si esegue durante un incidente: serve a rispondere a
 * "quanti sono, quali sono attivi, dove stanno i dati" senza aprire il
 * database di piattaforma a mano.
 */
final class TenantsListCommand extends Command
{
    protected $signature = 'tenants:list {--status= : Filtra per stato}';

    protected $description = 'Elenca i tenant registrati nel database di piattaforma';

    public function handle(TenantRepository $tenants): int
    {
        $status = $this->option('status');
        $rows = [];

        foreach ($tenants->all() as $tenant) {
            if ($status !== null && $tenant->getStatus()->value !== $status) {
                continue;
            }

            $rows[] = [
                $tenant->getSlug(),
                $tenant->getStatus()->label(),
                $tenant->getDatabaseName(),
                implode(', ', $tenant->getDomains()),
            ];
        }

        if ($rows === []) {
            $this->warn('Nessun tenant corrisponde ai criteri indicati.');

            return self::SUCCESS;
        }

        $this->table(['Slug', 'Stato', 'Database', 'Domini'], $rows);
        $this->info(count($rows).' tenant.');

        return self::SUCCESS;
    }
}
