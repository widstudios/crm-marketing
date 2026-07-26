<?php

declare(strict_types=1);

/**
 * Verifica la composizione della suite: categorie, isolamento, tre test per
 * operazione, factory.
 *
 *   php tooling/scripts/check-tests.php [percorso-progetto]
 *
 * Una suite verde non dimostra nulla se non si sa CHE COSA verifica. Questo
 * script guarda la composizione, non l'esito.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();

if (! is_dir($root.'/tests')) {
    fwrite(STDERR, "Nessuna cartella tests/ sotto {$root}.\n");
    exit(2);
}

$report = new Report('Composizione della suite');

// --- Le quattro categorie esistono ------------------------------------------

foreach (['Unit', 'Feature', 'Tenant', 'Architecture'] as $suite) {
    $report->counted();

    if (! is_dir($root.'/tests/'.$suite)) {
        $report->violation("tests/{$suite}/", null, "Categoria di test mancante: {$suite}.");
    }
}

// --- Ogni entita' ha un test di isolamento ----------------------------------

$isolation = '';

foreach (files($root.'/tests/Tenant', ['php']) as $path) {
    $isolation .= (string) file_get_contents($path);
}

foreach (files($root.'/app', ['php']) as $path) {
    $contents = (string) file_get_contents($path);

    if (preg_match('/^\s*(final\s+)?class\s+(\w+)\s+extends\s+Model\b/m', $contents, $m) !== 1) {
        continue;
    }

    $model = $m[2];
    $report->counted();

    if (preg_match('/\b'.preg_quote($model, '/').'\b/', $isolation) !== 1) {
        $report->violation(
            substr($path, strlen($root) + 1),
            null,
            "Nessun test di isolamento in tests/Tenant/ per {$model}."
        );
    }
}

// --- I tre test per ogni Action ---------------------------------------------

$features = '';

foreach (array_merge(files($root.'/tests/Feature', ['php']), files($root.'/tests/Unit', ['php'])) as $path) {
    $features .= (string) file_get_contents($path);
}

foreach (files($root.'/app', ['php']) as $path) {
    if (! str_contains($path, '/Actions/')) {
        continue;
    }

    $action = basename($path, '.php');
    $report->counted();

    if (preg_match('/\b'.preg_quote($action, '/').'\b/', $features) !== 1) {
        $report->violation(substr($path, strlen($root) + 1), null, "Nessun test trovato per {$action}.");
    }
}

// --- Igiene della suite ------------------------------------------------------

foreach (files($root.'/tests', ['php']) as $path) {
    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);
    $report->counted();

    if (preg_match('/\bsleep\s*\(/', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, 'sleep() in un test: rende la suite lenta e instabile insieme. Usare travel().');
    }

    if (preg_match('/Http::(get|post|put|patch|delete)\(/', $contents, $m, PREG_OFFSET_CAPTURE) === 1
        && preg_match('/Http::fake\(/', $contents) !== 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, 'Chiamata HTTP reale in un test: usare Http::fake().');
    }

    if (preg_match('/->(skip|markTestSkipped)\(\s*\)/', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, 'Test saltato senza motivazione: motivare o rimuovere.');
    }
}

// --- Ogni model ha una factory ----------------------------------------------

$factories = [];

foreach (files($root.'/database/factories', ['php']) as $path) {
    $factories[basename($path, 'Factory.php')] = true;
}

foreach (files($root.'/app', ['php']) as $path) {
    $contents = (string) file_get_contents($path);

    if (preg_match('/^\s*(final\s+)?class\s+(\w+)\s+extends\s+Model\b/m', $contents, $m) !== 1) {
        continue;
    }

    $report->counted();

    if (! isset($factories[$m[2]])) {
        $report->violation(substr($path, strlen($root) + 1), null, "Il model {$m[2]} non ha una factory.");
    }
}

exit($report->render());
