<?php

declare(strict_types=1);

/**
 * Verifica che la baseline di PHPStan non cresca.
 *
 *   php tooling/scripts/check-baseline.php [percorso-progetto]
 *   php tooling/scripts/check-baseline.php --max=0
 *
 * Una baseline serve a introdurre l'analisi statica su un progetto che esiste
 * gia': si congelano gli errori noti e si impedisce che ne nascano di nuovi.
 * Una baseline che si allunga a ogni commit e' un modo di disattivare
 * l'analisi mantenendone l'aspetto — e mantenendone anche il tempo di
 * esecuzione, che e' il peggio dei due mondi.
 *
 * Il confronto e' con il valore registrato in tooling/baseline-count.txt, che
 * si aggiorna solo verso il basso.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\option;
use function WidStudios\Tooling\repositoryRoot;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();
$report = new Report('Baseline dell\'analisi statica');

$candidates = [
    $root.'/phpstan-baseline.neon',
    $root.'/tooling/configs/phpstan-baseline.neon',
];

$baseline = null;

foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        $baseline = $candidate;

        break;
    }
}

if ($baseline === null) {
    // Nessuna baseline e' lo stato desiderato, non un errore.
    echo "\nBaseline dell'analisi statica\n";
    echo "-----------------------------\n";
    echo "  Nessuna baseline presente: l'analisi gira senza eccezioni congelate.\n\n";

    exit(0);
}

$contents = (string) file_get_contents($baseline);
$count = preg_match_all('/^\s*-\s*$/m', $contents) + preg_match_all('/^\s*-\s+message:/m', $contents);

$relative = substr($baseline, strlen($root) + 1);
$report->counted($count);

$recorded = $root.'/tooling/baseline-count.txt';
$max = option($argv, 'max');

if ($max !== null && $max !== '') {
    $limit = (int) $max;
} elseif (file_exists($recorded)) {
    $limit = (int) trim((string) file_get_contents($recorded));
} else {
    // Prima esecuzione: si registra il valore corrente come tetto.
    file_put_contents($recorded, (string) $count."\n");

    echo "\nBaseline dell'analisi statica\n";
    echo "-----------------------------\n";
    echo "  Valore iniziale registrato: {$count} eccezioni congelate.\n";
    echo "  Da ora in poi puo' solo diminuire.\n\n";

    exit(0);
}

if ($count > $limit) {
    $report->violation(
        $relative,
        null,
        "La baseline e' cresciuta: {$count} eccezioni contro un tetto di {$limit}. "
        .'Un nuovo errore congelato non e\' un errore risolto.'
    );

    exit($report->render());
}

if ($count < $limit) {
    file_put_contents($recorded, (string) $count."\n");
    echo "\nBaseline dell'analisi statica\n";
    echo "-----------------------------\n";
    echo "  Ridotta da {$limit} a {$count} eccezioni. Nuovo tetto registrato.\n\n";

    exit(0);
}

exit($report->render());
