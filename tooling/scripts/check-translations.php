<?php

declare(strict_types=1);

/**
 * Verifica che ogni chiave di traduzione usata esista, e che le lingue siano
 * allineate.
 *
 *   php tooling/scripts/check-translations.php [percorso-progetto]
 *
 * Una chiave mancante non produce un errore: produce la chiave stessa a schermo
 * — «warehouse.batch.expired» dentro un messaggio all'utente. Passa la revisione
 * perche' nessuno apre quella schermata in quella condizione.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();
$langPath = $root.'/lang';

if (! is_dir($langPath)) {
    fwrite(STDERR, "Nessuna cartella lang/ sotto {$root}.\n");
    exit(2);
}

$report = new Report('Traduzioni');

/** @var array<string, array<string, true>> $available  lingua => chiavi */
$available = [];

foreach (glob($langPath.'/*', GLOB_ONLYDIR) ?: [] as $localePath) {
    $locale = basename($localePath);
    $available[$locale] = [];

    foreach (files($localePath, ['php']) as $file) {
        $group = basename($file, '.php');
        $values = require $file;

        if (! is_array($values)) {
            continue;
        }

        foreach (flatten($values, $group) as $key) {
            $available[$locale][$key] = true;
        }
    }
}

if ($available === []) {
    fwrite(STDERR, "Nessuna lingua trovata in lang/.\n");
    exit(2);
}

$locales = array_keys($available);
$primary = in_array('it', $locales, strict: true) ? 'it' : $locales[0];

// --- Chiavi usate ma inesistenti --------------------------------------------

foreach (array_merge(
    files($root.'/app', ['php']),
    files($root.'/resources', ['php', 'blade.php']),
) as $path) {
    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);

    preg_match_all('/(?:__|trans|@lang)\(\s*[\'"]([a-z0-9_]+\.[a-z0-9_.]+)[\'"]/i', $contents, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[1] as [$key, $offset]) {
        $report->counted();

        if (isset($available[$primary][$key])) {
            continue;
        }

        $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
        $report->violation($relative, $line, "Chiave di traduzione inesistente in «{$primary}»: {$key}");
    }
}

// --- Lingue disallineate -----------------------------------------------------

foreach ($locales as $locale) {
    if ($locale === $primary) {
        continue;
    }

    $report->counted();
    $missing = array_diff_key($available[$primary], $available[$locale]);

    if ($missing !== []) {
        $sample = implode(', ', array_slice(array_keys($missing), 0, 5));
        $count = count($missing);

        $report->violation("lang/{$locale}/", null, "{$count} chiavi presenti in «{$primary}» e assenti qui. Prime: {$sample}");
    }
}

// --- Identificatori in italiano nelle chiavi --------------------------------

foreach ($available[$primary] as $key => $_) {
    $report->counted();

    // Le chiavi sono identificatori: vanno in inglese, come tutto cio' che sta
    // nel codice. Il valore e' in italiano, la chiave no.
    if (preg_match('/\b(lotto|lotti|scadenza|magazzino|fornitore|utente|elenco)\b/', $key) === 1) {
        $report->violation("lang/{$primary}/", null, "Chiave con termini in italiano: {$key}. Le chiavi sono identificatori, e gli identificatori sono in inglese.");
    }
}

exit($report->render());

/**
 * @param  array<mixed>  $values
 * @return list<string>
 */
function flatten(array $values, string $prefix): array
{
    $keys = [];

    foreach ($values as $key => $value) {
        $full = "{$prefix}.{$key}";

        if (is_array($value)) {
            $keys = array_merge($keys, flatten($value, $full));

            continue;
        }

        $keys[] = $full;
    }

    return $keys;
}
