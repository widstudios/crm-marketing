<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Modules;

use Illuminate\Contracts\Config\Repository as Config;
use WidStudios\Foundation\Contracts\Modules\Module;
use WidStudios\Foundation\Contracts\Modules\ModuleRegistry;
use WidStudios\Foundation\Exceptions\MissingModuleDependency;
use WidStudios\Foundation\Exceptions\ModuleNotFound;

/**
 * Registro dei moduli e loro ordinamento per dipendenza.
 *
 * L'ordinamento topologico serve a una cosa sola: garantire che le migration e
 * i seeder di un modulo girino dopo quelli dei moduli da cui dipende. Un
 * modulo che dichiara una dipendenza non soddisfatta, o una dipendenza
 * circolare, viene rifiutato all'avvio e non a runtime: e' il momento in cui il
 * messaggio d'errore puo' ancora essere utile.
 */
final class ModuleManager implements ModuleRegistry
{
    /** @var array<string, Module> */
    private array $modules = [];

    /** @var array<string, Module>|null */
    private ?array $enabledCache = null;

    public function __construct(
        private readonly Config $config,
    ) {}

    public function register(Module $module): void
    {
        $this->modules[$module->name()] = $module;
        $this->enabledCache = null;
    }

    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    public function isEnabled(string $name): bool
    {
        /** @var list<string> $enabled */
        $enabled = $this->config->get('foundation.modules.enabled', []);

        return $this->has($name) && in_array($name, $enabled, strict: true);
    }

    public function get(string $name): Module
    {
        return $this->modules[$name] ?? throw ModuleNotFound::named($name);
    }

    /**
     * @return array<string, Module>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * I moduli attivi, ordinati in modo che ogni modulo compaia dopo le proprie
     * dipendenze.
     *
     * @return array<string, Module>
     *
     * @throws MissingModuleDependency
     */
    public function enabled(): array
    {
        if ($this->enabledCache !== null) {
            return $this->enabledCache;
        }

        /** @var list<string> $names */
        $names = $this->config->get('foundation.modules.enabled', []);

        $sorted = [];
        $visiting = [];

        foreach ($names as $name) {
            $this->visit($name, $names, $sorted, $visiting);
        }

        return $this->enabledCache = $sorted;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->enabled() as $module) {
            foreach ($module->permissions() as $permission) {
                $permissions[$permission] = true;
            }
        }

        return array_keys($permissions);
    }

    /**
     * Visita in profondita' con rilevamento dei cicli.
     *
     * @param  list<string>  $enabledNames
     * @param  array<string, Module>  $sorted
     * @param  array<string, true>  $visiting
     */
    private function visit(string $name, array $enabledNames, array &$sorted, array &$visiting): void
    {
        if (isset($sorted[$name])) {
            return;
        }

        if (isset($visiting[$name])) {
            throw MissingModuleDependency::circular($name);
        }

        $module = $this->get($name);
        $visiting[$name] = true;

        foreach ($module->dependsOn() as $dependency) {
            if (! in_array($dependency, $enabledNames, strict: true)) {
                throw MissingModuleDependency::for($name, $dependency);
            }

            $this->visit($dependency, $enabledNames, $sorted, $visiting);
        }

        unset($visiting[$name]);

        $sorted[$name] = $module;
    }
}
