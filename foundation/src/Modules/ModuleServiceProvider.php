<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Modules;

use Illuminate\Support\ServiceProvider;
use WidStudios\Foundation\Contracts\Modules\Module;
use WidStudios\Foundation\Contracts\Modules\ModuleRegistry;

/**
 * Base per il service provider di un modulo.
 *
 * Registra il modulo nel registro e carica i suoi artefatti solo se e' attivo:
 * un modulo disattivato non deve lasciare tracce — niente rotte, niente
 * traduzioni, niente migration — altrimenti "disattivato" e' una parola senza
 * conseguenze e l'indipendenza dei moduli non e' verificabile.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    abstract protected function module(): Module;

    public function boot(): void
    {
        $module = $this->module();

        /** @var ModuleRegistry $registry */
        $registry = $this->app->make(ModuleRegistry::class);
        $registry->register($module);

        if (! $registry->isEnabled($module->name())) {
            return;
        }

        $this->bootModule($module);
    }

    /**
     * Registrazioni valide solo a modulo attivo: rotte, viste, traduzioni,
     * componenti, policy, listener.
     */
    abstract protected function bootModule(Module $module): void;
}
