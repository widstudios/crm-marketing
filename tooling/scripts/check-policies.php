<?php

declare(strict_types=1);

/**
 * Verifica che ogni model abbia una Policy, che nessuna Policy sia permissiva e
 * che ogni Policy abbia almeno un test di rifiuto.
 *
 *   php tooling/scripts/check-policies.php [percorso-progetto]
 *
 * Il terzo controllo e' quello che intercetta di piu': una Policy con soli test
 * di successo non ha mai dimostrato di negare, ed e' il negare che protegge.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;
use function WidStudios\Tooling\lineOf;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();

if (! is_dir($root.'/app')) {
    fwrite(STDERR, "Nessuna cartella app/ sotto {$root}: questo script gira su un progetto generato.\n");
    exit(2);
}

$report = new Report('Policy');

// --- Ogni model di dominio ha una Policy ------------------------------------

$policies = [];

foreach (files($root.'/app', ['php']) as $path) {
    if (! str_contains($path, '/Policies/')) {
        continue;
    }

    $policies[basename($path, 'Policy.php')] = $path;
}

foreach (files($root.'/app', ['php']) as $path) {
    $contents = (string) file_get_contents($path);

    if (preg_match('/^\s*(final\s+)?class\s+(\w+)\s+extends\s+Model\b/m', $contents, $m) !== 1) {
        continue;
    }

    $report->counted();
    $model = $m[2];

    if (! isset($policies[$model])) {
        $report->violation(
            substr($path, strlen($root) + 1),
            lineOf($contents, "class {$model}"),
            "Il model {$model} non ha una Policy corrispondente ({$model}Policy)."
        );
    }
}

// --- Nessuna Policy permissiva ----------------------------------------------

foreach ($policies as $model => $path) {
    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);
    $report->counted();

    // Un metodo che ritorna true senza verificare nulla e' deny by default
    // scritto al contrario.
    if (preg_match('/function\s+(\w+)\([^)]*\)\s*:\s*bool\s*\{\s*return\s+true\s*;/m', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, "{$m[1][0]}() ritorna true incondizionatamente: deny by default violato.");
    }

    // I ruoli cambiano, i permessi no.
    if (preg_match('/->hasRole\(|->hasAnyRole\(/', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, 'Controllo su un nome di ruolo: verificare il permesso.');
    }

    // Con un database per tenant, un record di un altro cliente non esiste in
    // questa connessione: un confronto su tenant_id segnala un modello mentale
    // sbagliato, e spesso accompagna altri difetti.
    if (preg_match('/tenant_id/', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, 'Riferimento a tenant_id in una Policy: il tenant e\' il database.');
    }
}

// --- Ogni Policy ha almeno un test di rifiuto -------------------------------

$tests = '';

foreach (files($root.'/tests', ['php']) as $path) {
    $tests .= (string) file_get_contents($path);
}

foreach ($policies as $model => $path) {
    $report->counted();

    $mentioned = preg_match('/\b'.preg_quote($model, '/').'Policy\b/', $tests) === 1
        || preg_match('/assertForbidden|assertStatus\(403\)|->toBeFalse\(\)/', $tests) === 1;

    if (! $mentioned) {
        $report->violation(
            substr($path, strlen($root) + 1),
            null,
            "Nessun test di rifiuto trovato per {$model}Policy: il rifiuto non e' mai stato dimostrato."
        );
    }
}

exit($report->render());
