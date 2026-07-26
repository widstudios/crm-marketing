<?php

declare(strict_types=1);

/**
 * Verifica i soli link, su una radice qualunque.
 *
 *   php tooling/scripts/check-links.php [percorso]
 *
 * check-docs.php fa questo e altro. Questo script esiste separato perche' e' il
 * controllo piu' rapido, e in un ciclo di scrittura conta la differenza tra due
 * secondi e venti.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;
use function WidStudios\Tooling\repositoryRoot;
use function WidStudios\Tooling\withoutFences;

use WidStudios\Tooling\Report;

$root = $argv[1] ?? repositoryRoot();
$root = realpath($root) ?: $root;

if (! is_dir($root)) {
    fwrite(STDERR, "Percorso inesistente: {$root}\n");
    exit(2);
}

$report = new Report('Link interni');

foreach (files($root, ['md'], ['/legacy/']) as $path) {
    $contents = withoutFences((string) file_get_contents($path));
    $relative = substr($path, strlen($root) + 1);
    $directory = dirname($path);

    preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $contents, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[1] as [$target, $offset]) {
        $report->counted();

        if (preg_match('~^(https?:|mailto:|#)~', $target) === 1) {
            continue;
        }

        $file = explode('#', $target, 2)[0];

        if ($file === '') {
            continue;
        }

        if (! file_exists($directory.'/'.$file)) {
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
            $report->violation($relative, $line, "Link a un file inesistente: {$file}");
        }
    }
}

exit($report->render());
