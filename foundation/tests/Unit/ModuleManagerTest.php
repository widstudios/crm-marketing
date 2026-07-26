<?php

declare(strict_types=1);

use Illuminate\Config\Repository as Config;
use WidStudios\Foundation\Exceptions\MissingModuleDependency;
use WidStudios\Foundation\Exceptions\ModuleNotFound;
use WidStudios\Foundation\Modules\ModuleDefinition;
use WidStudios\Foundation\Modules\ModuleManager;

function module(string $name, array $dependsOn = [], array $permissions = []): ModuleDefinition
{
    return new class($name, $dependsOn, $permissions) extends ModuleDefinition
    {
        public function __construct(string $name, array $dependsOn, array $permissions)
        {
            $this->name = $name;
            $this->dependsOn = $dependsOn;
            $this->permissions = $permissions;
            $this->description = "Modulo di prova {$name}.";
        }
    };
}

function manager(array $enabled): ModuleManager
{
    return new ModuleManager(new Config(['foundation' => ['modules' => ['enabled' => $enabled]]]));
}

it('ordina i moduli attivi dopo le loro dipendenze', function (): void {
    $manager = manager(['reporting', 'documents', 'audit']);

    $manager->register(module('reporting', dependsOn: ['documents']));
    $manager->register(module('documents', dependsOn: ['audit']));
    $manager->register(module('audit'));

    expect(array_keys($manager->enabled()))->toBe(['audit', 'documents', 'reporting']);
});

it('rifiuta una dipendenza non attiva', function (): void {
    $manager = manager(['reporting']);

    $manager->register(module('reporting', dependsOn: ['documents']));
    $manager->register(module('documents'));

    expect(fn (): array => $manager->enabled())
        ->toThrow(MissingModuleDependency::class, "richiede 'documents'");
});

it('rifiuta le dipendenze circolari', function (): void {
    $manager = manager(['a', 'b']);

    $manager->register(module('a', dependsOn: ['b']));
    $manager->register(module('b', dependsOn: ['a']));

    expect(fn (): array => $manager->enabled())
        ->toThrow(MissingModuleDependency::class, 'circolare');
});

it("considera attivo solo cio' che e' in configurazione", function (): void {
    $manager = manager(['audit']);

    $manager->register(module('audit'));
    $manager->register(module('documents'));

    expect($manager->isEnabled('audit'))->toBeTrue()
        ->and($manager->isEnabled('documents'))->toBeFalse()
        ->and($manager->has('documents'))->toBeTrue();
});

it('raccoglie i permessi dei soli moduli attivi, senza duplicati', function (): void {
    $manager = manager(['audit', 'documents']);

    $manager->register(module('audit', permissions: ['audit.view', 'audit.export']));
    $manager->register(module('documents', permissions: ['document.view', 'audit.view']));
    $manager->register(module('cms', permissions: ['page.view']));

    expect($manager->permissions())
        ->toBe(['audit.view', 'audit.export', 'document.view']);
});

it('fallisce con un messaggio utile su un modulo sconosciuto', function (): void {
    expect(fn () => manager([])->get('inesistente'))
        ->toThrow(ModuleNotFound::class, "'inesistente'");
});
