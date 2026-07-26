<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Contracts\Modules;

use WidStudios\Foundation\Exceptions\ModuleNotFound;

/**
 * Elenco dei moduli conosciuti e del loro stato.
 */
interface ModuleRegistry
{
    public function register(Module $module): void;

    public function has(string $name): bool;

    public function isEnabled(string $name): bool;

    /**
     * @throws ModuleNotFound
     */
    public function get(string $name): Module;

    /**
     * @return array<string, Module> indicizzati per nome
     */
    public function all(): array;

    /**
     * @return array<string, Module> i soli moduli attivi, in ordine di dipendenza
     */
    public function enabled(): array;

    /**
     * Tutti i permessi introdotti dai moduli attivi.
     *
     * @return list<string>
     */
    public function permissions(): array;
}
