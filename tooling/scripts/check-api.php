<?php

declare(strict_types=1);

/**
 * Verifica il contratto delle API: versione, autorizzazione, Resource,
 * paginazione, test.
 *
 *   php tooling/scripts/check-api.php [percorso-progetto]
 *   php tooling/scripts/check-api.php --diff    confronto con il contratto pubblicato
 *
 * Un'API pubblicata e' un contratto che non si puo' ritirare. Il confronto con
 * la versione pubblicata e' la verifica piu' importante di questo script: e'
 * l'unica che intercetta una rottura PRIMA che raggiunga un integratore.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;
use function WidStudios\Tooling\hasOption;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();

if (! is_dir($root.'/app')) {
    fwrite(STDERR, "Nessuna cartella app/ sotto {$root}.\n");
    exit(2);
}

$report = new Report('Contratto delle API');

// --- Rotte versionate --------------------------------------------------------

$routes = $root.'/routes/api.php';

if (file_exists($routes)) {
    $contents = (string) file_get_contents($routes);
    $report->counted();

    if (preg_match('/prefix\(\s*[\'"]v\d+[\'"]\s*\)|[\'"]v\d+\//', $contents) !== 1) {
        $report->violation('routes/api.php', null, 'Nessun prefisso di versione: un\'API non versionata non si puo\' far evolvere senza rompere gli integratori.');
    }

    // Un verbo nel percorso significa che l'azione non e' nel metodo HTTP.
    preg_match_all('/[\'"]\/?([a-z0-9\-\/{}]*\b(create|update|delete|get|list|store|edit)\b[a-z0-9\-\/{}]*)[\'"]/', $contents, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[1] as [$path, $offset]) {
        $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
        $report->violation('routes/api.php', $line, "Verbo nel percorso: /{$path}. L'azione appartiene al metodo HTTP.");
    }
}

// --- Controller API ----------------------------------------------------------

foreach (files($root.'/app', ['php']) as $path) {
    if (! preg_match('#/Controllers/Api/#', $path)) {
        continue;
    }

    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);
    $report->counted();

    if (preg_match('/\$this->authorize\(|Gate::authorize\(/', $contents) !== 1) {
        $report->violation($relative, null, 'Controller API senza autorizzazione esplicita.');
    }

    // Un toArray() del model espone tutte le colonne, comprese quelle aggiunte
    // domani: da quel momento fanno parte del contratto.
    if (preg_match('/->toArray\(\)|response\(\)->json\(\s*\$\w+\s*\)/', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, 'Risposta costruita dal model: usare una API Resource con campi espliciti.');
    }

    // Un elenco non paginato e' una risposta la cui dimensione dipende dal
    // cliente.
    if (preg_match('/function\s+index\s*\(/', $contents) === 1 && preg_match('/->paginate\(|->cursorPaginate\(/', $contents) !== 1) {
        $report->violation($relative, null, 'Metodo index() senza paginazione.');
    }

    if (preg_match('/->paginate\(\s*\$request->(?:integer|input)\([^)]*\)\s*\)/', $contents) === 1
        && preg_match('/max:\d+|min\(\s*\d+/', $contents) !== 1) {
        $report->violation($relative, null, 'Dimensione di pagina dalla richiesta senza massimo dichiarato: per_page=100000 satura il server.');
    }
}

// --- Test degli endpoint -----------------------------------------------------

$tests = '';

foreach (files($root.'/tests', ['php']) as $path) {
    $tests .= (string) file_get_contents($path);
}

foreach (['assertForbidden|assertStatus\(403\)' => 'autorizzazione negata',
    'assertStatus\(422\)|assertUnprocessable' => 'validazione fallita',
    'assertNotFound|assertStatus\(404\)' => 'risorsa inesistente'] as $pattern => $description) {
    $report->counted();

    if (preg_match('/'.$pattern.'/', $tests) !== 1) {
        $report->violation('tests/', null, "Nessun test di «{$description}» in tutta la suite: uno dei quattro test obbligatori per endpoint manca ovunque.");
    }
}

// --- Confronto con il contratto pubblicato ----------------------------------

if (hasOption($argv, 'diff')) {
    $published = $root.'/docs/api/openapi.json';

    if (! file_exists($published)) {
        fwrite(STDERR, "Nessun contratto pubblicato in docs/api/openapi.json: impossibile confrontare.\n");
        exit(2);
    }

    /** @var array{paths?: array<string, array<string, mixed>>} $spec */
    $spec = json_decode((string) file_get_contents($published), true, flags: JSON_THROW_ON_ERROR);

    $current = [];

    if (file_exists($routes)) {
        preg_match_all('/Route::(get|post|put|patch|delete)\(\s*[\'"]([^\'"]+)[\'"]/', (string) file_get_contents($routes), $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $current[strtolower($match[1]).' /'.ltrim($match[2], '/')] = true;
        }
    }

    foreach ($spec['paths'] ?? [] as $path => $methods) {
        foreach (array_keys($methods) as $method) {
            $signature = strtolower((string) $method).' '.$path;
            $report->counted();

            if (! isset($current[$signature])) {
                $report->violation('routes/api.php', null, "Endpoint pubblicato e non piu' presente: {$signature}. Rimuoverlo rompe ogni integratore.");
            }
        }
    }
}

exit($report->render());
