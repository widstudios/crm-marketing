<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Bootstrappers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\DatabaseManager;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantBootstrapper;

/**
 * Punta la connessione applicativa al database del tenant.
 *
 * L'ordine delle operazioni non e' negoziabile:
 *
 *   1. si riscrive la configurazione della connessione;
 *   2. si esegue purge(), che chiude e dimentica la connessione esistente;
 *   3. si esegue reconnect(), che ne apre una nuova con la configurazione nuova.
 *
 * Senza purge() prima di reconnect(), il DatabaseManager riusa l'istanza gia'
 * costruita con la configurazione precedente: il codice sembra funzionare, e
 * scrive nel database del cliente sbagliato. E' il difetto piu' pericoloso
 * dell'intera Foundation, ed e' invisibile finche' non ci sono due tenant.
 */
final class DatabaseBootstrapper implements TenantBootstrapper
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Config $config,
    ) {}

    public function bootstrap(Tenant $tenant): void
    {
        $connection = $this->tenantConnection();
        $template = (string) $this->config->get('foundation.tenancy.tenant_connection_template');

        /** @var array<string, mixed> $settings */
        $settings = $this->config->get("database.connections.{$template}", []);
        $settings['database'] = $tenant->getDatabaseName();

        $this->config->set("database.connections.{$connection}", $settings);
        $this->config->set('database.default', $connection);

        $this->database->purge($connection);
        $this->database->reconnect($connection);
    }

    public function revert(): void
    {
        $connection = $this->tenantConnection();

        $this->database->purge($connection);
        $this->config->set('database.default', $this->config->get('foundation.tenancy.landlord_connection'));
    }

    private function tenantConnection(): string
    {
        return (string) $this->config->get('foundation.tenancy.tenant_connection');
    }
}
