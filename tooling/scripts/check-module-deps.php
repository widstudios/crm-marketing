<?php

declare(strict_types=1);

/**
 * Verifica le dipendenze dichiarate dai moduli e le dipendenze non dichiarate.
 *
 *   php tooling/scripts/check-module-deps.php [percorso-progetto]
 *
 * La verifica definitiva resta module:disable seguito da composer test: questo
 * script trova in un secondo la maggior parte dei casi che quella verifica
 * troverebbe in tre minuti, e li trova indicando il file.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();
$modulesPath = $root.'/app/Modules';

if (! is_dir($modulesPath)) {
    fwrite(STDERR, "Nessuna cartella app/Modules sotto {$root}.\n");
    exit(2);
}

$report = new Report('Dipendenze tra moduli');

/** @var array<string, array{path: string, namespace: string, depends: list<string>}> $modules */
$modules = [];

foreach (glob($modulesPath.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
    $name = basename($directory);
    $definition = glob($directory.'/*Module.php')[0] ?? null;

    if ($definition === null) {
        $report->violation("app/Modules/{$name}/", null, 'Nessuna classe *Module.php: il modulo non dichiara il proprio contratto.');

        continue;
    }

    $contents = (string) file_get_contents($definition);

    preg_match('/\$name\s*=\s*[\'"]([a-z_]+)[\'"]/', $contents, $m);
    $slug = $m[1] ?? strtolower($name);

    preg_match('/\$dependsOn\s*=\s*\[(.*?)\]/s', $contents, $d);
    preg_match_all('/[\'"]([a-z_]+)[\'"]/', $d[1] ?? '', $deps);

    $modules[$slug] = [
        'path' => $directory,
        'namespace' => "App\\Modules\\{$name}",
        'depends' => $deps[1],
    ];
}

// --- Dipendenze dichiarate ma inesistenti -----------------------------------

foreach ($modules as $slug => $module) {
    $report->counted();

    foreach ($module['depends'] as $dependency) {
        if (! isset($modules[$dependency])) {
            $report->violation(
                substr($module['path'], strlen($root) + 1),
                null,
                "Dipendenza dichiarata verso «{$dependency}», che non esiste."
            );
        }
    }
}

// --- Cicli -------------------------------------------------------------------

foreach (array_keys($modules) as $slug) {
    $report->counted();
    $visited = [];

    if (hasCycle($slug, $modules, $visited)) {
        $report->violation("app/Modules/", null, "Dipendenza circolare rilevata a partire da «{$slug}».");
    }
}

// --- Dipendenze non dichiarate ----------------------------------------------

foreach ($modules as $slug => $module) {
    foreach (files($module['path'], ['php']) as $path) {
        $contents = (string) file_get_contents($path);
        $relative = substr($path, strlen($root) + 1);

        foreach ($modules as $otherSlug => $other) {
            if ($otherSlug === $slug) {
                continue;
            }

            if (! str_contains($contents, $other['namespace'])) {
                continue;
            }

            $report->counted();

            if (! in_array($otherSlug, $module['depends'], strict: true)) {
                $report->violation(
                    $relative,
                    null,
                    "Usa classi del modulo «{$otherSlug}» senza dichiararlo in dependsOn: il modulo si rompe quando l'altro viene disattivato."
                );
            }
        }
    }
}

// --- Il codice del progetto usa i moduli senza verifica ---------------------

foreach (files($root.'/app', ['php'], ['/Modules/']) as $path) {
    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);

    foreach ($modules as $slug => $module) {
        if (! str_contains($contents, $module['namespace'])) {
            continue;
        }

        $report->counted();

        if (preg_match('/isEnabled\(\s*[\'"]'.preg_quote($slug, '/').'[\'"]\s*\)/', $contents) !== 1) {
            $report->violation(
                $relative,
                null,
                "Usa classi del modulo «{$slug}» senza verificarne l'attivazione: errore di classe non trovata quando il modulo viene disattivato."
            );
        }
    }
}

exit($report->render());

/**
 * @param  array<string, array{path: string, namespace: string, depends: list<string>}>  $modules
 * @param  array<string, bool>  $visiting
 */
function hasCycle(string $slug, array $modules, array &$visiting): bool
{
    if (isset($visiting[$slug])) {
        return true;
    }

    if (! isset($modules[$slug])) {
        return false;
    }

    $visiting[$slug] = true;

    foreach ($modules[$slug]['depends'] as $dependency) {
        if (hasCycle($dependency, $modules, $visiting)) {
            return true;
        }
    }

    unset($visiting[$slug]);

    return false;
}
