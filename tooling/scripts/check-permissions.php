<?php

declare(strict_types=1);

/**
 * Verifica che ogni permesso usato nel codice sia creato da un seeder, e che i
 * permessi nuovi finiscano al ruolo amministratore.
 *
 *   php tooling/scripts/check-permissions.php [percorso-progetto]
 *
 * Il difetto che intercetta e' invisibile in sviluppo, dove si lavora con un
 * utente che ha tutti i permessi: dopo il deploy la funzionalita' esiste,
 * funziona, e nessuno ha il permesso di vederla.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();

if (! is_dir($root.'/app')) {
    fwrite(STDERR, "Nessuna cartella app/ sotto {$root}.\n");
    exit(2);
}

$report = new Report('Permessi');

// --- Permessi usati nel codice ----------------------------------------------

/** @var array<string, array{0: string, 1: int}> $used */
$used = [];

foreach (files($root.'/app', ['php']) as $path) {
    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);

    // Forma <risorsa>.<azione>, come prescritto da rules/policies.md.
    preg_match_all('/->(?:can|cannot)\(\s*[\'"]([a-z_]+\.[a-z_]+)[\'"]/', $contents, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[1] as [$permission, $offset]) {
        $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
        $used[$permission] ??= [$relative, $line];
    }
}

// --- Permessi dichiarati da seeder e moduli ---------------------------------

$declared = '';

foreach (array_merge(
    files($root.'/database/seeders', ['php']),
    files($root.'/app/Modules', ['php']),
    files($root.'/config', ['php']),
) as $path) {
    $declared .= (string) file_get_contents($path);
}

foreach ($used as $permission => [$file, $line]) {
    $report->counted();

    if (! str_contains($declared, $permission)) {
        $report->violation($file, $line, "Il permesso «{$permission}» non e' dichiarato in nessun seeder o modulo: dopo il deploy nessuno lo avra'.");
    }
}

// --- Il ruolo amministratore riceve i permessi nuovi ------------------------

$seeders = '';

foreach (files($root.'/database/seeders', ['php']) as $path) {
    $seeders .= (string) file_get_contents($path);
}

$report->counted();

if ($seeders !== '' && preg_match('/syncPermissions\(|givePermissionTo\(/', $seeders) !== 1) {
    $report->violation(
        'database/seeders/',
        null,
        'Nessun seeder assegna i permessi al ruolo amministratore: ogni funzionalita\' nuova sara\' invisibile a tutti dopo il deploy.'
    );
}

// --- I seeder di sistema sono idempotenti -----------------------------------

foreach (files($root.'/database/seeders', ['php']) as $path) {
    if (! str_contains($path, '/System/')) {
        continue;
    }

    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);
    $report->counted();

    if (preg_match('/::create\(/', $contents) === 1 && preg_match('/firstOrCreate\(|updateOrCreate\(/', $contents) !== 1) {
        $report->violation($relative, null, 'Seeder di sistema non idempotente: fallirebbe alla seconda esecuzione, bloccando il deploy.');
    }
}

exit($report->render());
