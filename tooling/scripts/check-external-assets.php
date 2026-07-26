<?php

declare(strict_types=1);

/**
 * Cerca asset caricati da domini esterni.
 *
 *   php tooling/scripts/check-external-assets.php [percorso-progetto]
 *
 * Un font o uno script servito da un CDN e' un terzo che vede l'indirizzo IP di
 * ogni utente, che puo' cambiare il contenuto servito senza preavviso, e che
 * decide la disponibilita' della nostra applicazione. Per un gestionale in
 * ambito sanitario o fiscale non e' una scelta di prestazioni: e' una decisione
 * sul trattamento dei dati.
 *
 * Gli asset si compilano con Vite e si servono dal nostro dominio.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();
$report = new Report('Asset esterni');

/** Domini che, se compaiono in un template, sono quasi sempre un difetto. */
const EXTERNAL_HOSTS = [
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'cdn.jsdelivr.net',
    'cdnjs.cloudflare.com',
    'unpkg.com',
    'ajax.googleapis.com',
    'stackpath.bootstrapcdn.com',
    'use.fontawesome.com',
    'code.jquery.com',
    'www.google-analytics.com',
    'googletagmanager.com',
];

$paths = array_merge(
    files($root.'/resources', ['php', 'blade.php', 'css', 'js', 'html']),
    files($root.'/app', ['php']),
    files($root.'/public', ['html', 'css', 'js']),
);

foreach ($paths as $path) {
    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);
    $report->counted();

    foreach (EXTERNAL_HOSTS as $host) {
        if (! str_contains($contents, $host)) {
            continue;
        }

        $offset = strpos($contents, $host);
        $line = substr_count(substr($contents, 0, (int) $offset), "\n") + 1;

        $report->violation($relative, $line, "Asset da dominio esterno: {$host}. Compilare con Vite e servire dal proprio dominio.");
    }

    // Un <script src> o <link href> verso http:// e' anche un contenuto misto.
    if (preg_match('/(src|href)\s*=\s*[\'"]http:\/\//', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, 'Risorsa caricata su http://: contenuto misto, bloccato dai browser su una pagina HTTPS.');
    }
}

exit($report->render());
