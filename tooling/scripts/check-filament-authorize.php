<?php

declare(strict_types=1);

/**
 * Cerca le azioni Filament personalizzate senza ->authorize() e le pagine senza
 * canAccess().
 *
 *   php tooling/scripts/check-filament-authorize.php [percorso-progetto]
 *
 * Filament autorizza automaticamente il CRUD, e questo copre la maggior parte
 * dell'interfaccia. Non copre i tre punti in cui i difetti si nascondono:
 * azioni personalizzate, pagine personalizzate, metodi pubblici Livewire.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();

if (! is_dir($root.'/app/Filament')) {
    fwrite(STDERR, "Nessuna cartella app/Filament sotto {$root}.\n");
    exit(2);
}

$report = new Report('Autorizzazione in Filament');

foreach (files($root.'/app/Filament', ['php']) as $path) {
    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);

    // --- Azioni personalizzate ---------------------------------------------
    preg_match_all('/Action::make\(\s*[\'"](\w+)[\'"]\s*\)(.*?)(?=Action::make\(|\]\s*\)|\z)/s', $contents, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    foreach ($matches as $match) {
        $name = $match[1][0];
        $chain = $match[2][0];

        // Le azioni predefinite hanno l'autorizzazione automatica.
        if (in_array($name, ['create', 'edit', 'view', 'delete', 'restore', 'forceDelete'], strict: true)) {
            continue;
        }

        $report->counted();

        if (preg_match('/->authorize\(|->visible\(|->hidden\(/', $chain) === 1) {
            continue;
        }

        $line = substr_count(substr($contents, 0, $match[0][1]), "\n") + 1;
        $report->violation($relative, $line, "Azione personalizzata «{$name}» senza ->authorize().");
    }

    // --- Pagine personalizzate ---------------------------------------------
    if (str_contains($path, '/Pages/') && preg_match('/extends\s+Page\b/', $contents) === 1) {
        $report->counted();

        if (preg_match('/function\s+canAccess\(/', $contents) !== 1) {
            $report->violation($relative, null, 'Pagina personalizzata senza canAccess(): non esiste una Policy che Filament possa invocare da sola.');
        }
    }

    // --- Widget -------------------------------------------------------------
    if (str_contains($path, '/Widgets/')) {
        $report->counted();

        if (preg_match('/function\s+canView\(/', $contents) !== 1) {
            $report->violation($relative, null, 'Widget senza canView(): visibile a chiunque acceda al pannello.');
        }

        if (preg_match('/Cache::/', $contents) === 1 && preg_match('/TenantCacheKey/', $contents) !== 1) {
            $report->violation($relative, null, 'Widget che usa la cache senza TenantCacheKey: un cliente puo\' leggere i numeri di un altro.');
        }
    }
}

exit($report->render());
