<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Bootstrappers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Queue\QueueManager;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantBootstrapper;

/**
 * Fa viaggiare lo slug del tenant nel payload di ogni job accodato.
 *
 * Il meccanismo e' registrato una volta sola, all'avvio, dal
 * FoundationServiceProvider: qui il bootstrapper esiste per completare il
 * quadro dei quattro punti dell'isolamento e per garantire che il payload venga
 * ricalcolato dopo un cambio di contesto.
 *
 * La coda e' il punto in cui l'isolamento si perde piu' facilmente, perche' il
 * job viene eseguito minuti dopo, in un processo che non ha mai visto la
 * richiesta originale.
 */
final class QueueBootstrapper implements TenantBootstrapper
{
    public function __construct(
        private readonly QueueManager $queue,
        private readonly Config $config,
    ) {}

    public function bootstrap(Tenant $tenant): void
    {
        // Il payload viene composto al momento del push da createPayloadUsing(),
        // che legge il TenantContext corrente: qui basta invalidare le
        // connessioni gia' risolte, perche' possano essere ricostruite.
        $this->forgetConnections();
    }

    public function revert(): void
    {
        $this->forgetConnections();
    }

    private function forgetConnections(): void
    {
        $default = (string) $this->config->get('queue.default');

        if (method_exists($this->queue, 'forgetConnection')) {
            $this->queue->forgetConnection($default);
        }
    }
}
