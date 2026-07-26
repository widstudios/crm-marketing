<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Modules;

use WidStudios\Foundation\Contracts\Modules\Module;

/**
 * Implementazione dichiarativa di un modulo.
 *
 * Un modulo che non ha bisogno di comportamento proprio si descrive estendendo
 * questa classe e riempiendo le proprieta': niente metodi da scrivere, niente
 * modi diversi di fare la stessa cosa tra un modulo e l'altro.
 *
 *     final class AuditModule extends ModuleDefinition
 *     {
 *         protected string $name = 'audit';
 *         protected string $version = '1.2.0';
 *         protected string $description = 'Registro immutabile delle mutazioni sensibili.';
 *         protected array $permissions = ['audit.view', 'audit.export'];
 *     }
 */
abstract class ModuleDefinition implements Module
{
    protected string $name = '';

    protected string $version = '0.1.0';

    protected string $description = '';

    /** @var list<string> */
    protected array $dependsOn = [];

    /** @var list<string> */
    protected array $permissions = [];

    /** @var list<string> */
    protected array $tenantMigrationPaths = [];

    /** @var list<string> */
    protected array $landlordMigrationPaths = [];

    public function name(): string
    {
        return $this->name;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return list<string>
     */
    public function dependsOn(): array
    {
        return $this->dependsOn;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /**
     * @return list<string>
     */
    public function tenantMigrationPaths(): array
    {
        return $this->tenantMigrationPaths;
    }

    /**
     * @return list<string>
     */
    public function landlordMigrationPaths(): array
    {
        return $this->landlordMigrationPaths;
    }
}
