<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Contracts\Tenancy;

/**
 * Riconfigura una parte del framework quando il contesto tenant si apre.
 *
 * I bootstrapper sono la traduzione operativa dei punti in cui l'isolamento
 * dipende dal codice e non dalla struttura: database, cache, file, code.
 *
 * Ogni bootstrapper deve essere in grado di riportare il framework allo stato
 * precedente: senza revert(), un comando che elabora N tenant contamina il
 * secondo con la configurazione del primo.
 */
interface TenantBootstrapper
{
    public function bootstrap(Tenant $tenant): void;

    public function revert(): void;
}
