<?php

declare(strict_types=1);

/**
 * Cerca i punti di ingresso che non autorizzano.
 *
 *   php tooling/scripts/check-authorization.php [percorso-progetto]
 *
 * Un endpoint senza autorizzazione non produce errori: produce accessi. E'
 * il difetto piu' silenzioso che un'applicazione possa avere, e l'unico modo
 * di trovarlo prima di un incidente e' cercarlo.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();

if (! is_dir($root.'/app')) {
    fwrite(STDERR, "Nessuna cartella app/ sotto {$root}: questo script gira su un progetto generato.\n");
    exit(2);
}

$report = new Report('Autorizzazione nei punti di ingresso');

/** Metodi che non mutano e non leggono risorse: non richiedono autorizzazione. */
const EXEMPT_METHODS = ['__construct', 'middleware', 'render', 'mount', 'boot', 'rules', 'messages', 'authorize'];

foreach (files($root.'/app', ['php']) as $path) {
    $isController = str_contains($path, '/Controllers/');
    $isLivewire = str_contains($path, '/Livewire/');

    if (! $isController && ! $isLivewire) {
        continue;
    }

    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);

    preg_match_all(
        '/^\s*public function (\w+)\([^)]*\)[^{]*\{(.*?)^\s{4}\}/ms',
        $contents,
        $matches,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    foreach ($matches as $match) {
        $method = $match[1][0];
        $body = $match[2][0];

        if (in_array($method, EXEMPT_METHODS, strict: true)) {
            continue;
        }

        $report->counted();

        $authorized = preg_match('/\$this->authorize\(|Gate::(authorize|allows|denies)\(|->cannot\(|->can\(/', $body) === 1;

        if ($authorized) {
            continue;
        }

        // Un metodo Livewire che non tocca nulla e non risponde puo' essere
        // presentazione pura: si segnala solo se muta o se legge un model.
        $touchesData = preg_match('/::(create|update|delete|findOrFail|find|query)\(|->save\(|->delete\(|->update\(|Action::class|->execute\(/', $body) === 1;

        if (! $touchesData && $isLivewire) {
            continue;
        }

        $line = substr_count(substr($contents, 0, $match[0][1]), "\n") + 1;
        $kind = $isLivewire ? 'metodo pubblico Livewire' : 'metodo di controller';

        $report->violation($relative, $line, "{$kind} {$method}() senza autorizzazione esplicita.");
    }
}

exit($report->render());
