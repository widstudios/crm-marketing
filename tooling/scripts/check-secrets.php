<?php

declare(strict_types=1);

/**
 * Cerca segreti nel working tree e, se richiesto, nella storia dei commit.
 *
 *   php tooling/scripts/check-secrets.php
 *   php tooling/scripts/check-secrets.php --history
 *
 * Perche' anche la storia: un segreto rimosso da un file resta nei commit
 * precedenti, e da li' e' recuperabile da chiunque abbia il repository. La
 * rimozione dal working tree non e' una correzione: e' un occultamento. La
 * correzione e' la ROTAZIONE della credenziale.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;
use function WidStudios\Tooling\hasOption;
use function WidStudios\Tooling\repositoryRoot;

use WidStudios\Tooling\Report;

/**
 * Ogni voce: descrizione => espressione.
 *
 * L'elenco e' costruito su cio' che si e' visto finire davvero in un
 * repository, non su una tassonomia completa dei segreti possibili.
 */
const SIGNATURES = [
    'Chiave applicativa Laravel valorizzata' => '/APP_KEY\s*=\s*base64:[A-Za-z0-9+\/]{40,}/',
    'Token AWS' => '/\b(AKIA|ASIA)[A-Z0-9]{16}\b/',
    'Chiave privata' => '/-----BEGIN (RSA |EC |OPENSSH |PGP )?PRIVATE KEY-----/',
    'Token GitHub' => '/\bgh[pousr]_[A-Za-z0-9]{36,}\b/',
    'Token Slack' => '/\bxox[baprs]-[A-Za-z0-9-]{10,}\b/',
    'Chiave Stripe' => '/\b[sr]k_(live|test)_[A-Za-z0-9]{20,}\b/',
    'Password in stringa di connessione' => '/\b(mysql|postgres|redis|amqp):\/\/[^:\s\/]+:[^@\s]{6,}@/',
    'JSON Web Token' => '/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/',
];

/**
 * Valori che sembrano segreti e non lo sono.
 *
 * Un controllo che segnala i propri esempi viene disattivato: i segnaposto
 * evidenti devono restare evidenti anche per lo script.
 */
const PLACEHOLDERS = ['SOSTITUIRE', 'CAMBIAMI', 'PLACEHOLDER', 'esempio', 'example', 'xxxxx', 'null'];

$root = repositoryRoot();
$report = new Report('Segreti nel repository');

$paths = files($root, ['php', 'env', 'yaml', 'yml', 'json', 'md', 'sh', 'stub', 'neon', 'ini', 'conf'], ['/legacy/']);
$paths = array_merge($paths, array_filter([
    file_exists($root.'/.env.example') ? $root.'/.env.example' : null,
]));

foreach ($paths as $path) {
    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);
    $report->counted();

    foreach (SIGNATURES as $description => $pattern) {
        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === 0) {
            continue;
        }

        foreach ($matches[0] as [$match, $offset]) {
            foreach (PLACEHOLDERS as $placeholder) {
                if (stripos($match, $placeholder) !== false) {
                    continue 2;
                }
            }

            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
            $report->violation($relative, $line, "{$description}. Se e' reale: ruotare la credenziale, non limitarsi a rimuoverla.");
        }
    }
}

// Un file .env versionato e' un difetto a prescindere dal contenuto.
if (file_exists($root.'/.env')) {
    $tracked = shell_exec('git -C '.escapeshellarg($root).' ls-files --error-unmatch .env 2>/dev/null');

    if (is_string($tracked) && trim($tracked) !== '') {
        $report->violation('.env', null, 'File .env tracciato da git: va rimosso dall\'indice e aggiunto a .gitignore.');
    }
}

if (hasOption($argv, 'history')) {
    $log = shell_exec('git -C '.escapeshellarg($root).' log -p --no-color -- "*.env" "*.php" "*.yaml" "*.yml" 2>/dev/null');

    if (! is_string($log)) {
        fwrite(STDERR, "Impossibile leggere la storia dei commit: git non disponibile.\n");
        exit(2);
    }

    foreach (SIGNATURES as $description => $pattern) {
        if (preg_match($pattern, $log, $m) === 1) {
            $needle = $m[0];

            foreach (PLACEHOLDERS as $placeholder) {
                if (stripos($needle, $placeholder) !== false) {
                    continue 2;
                }
            }

            $report->violation('(storia dei commit)', null, "{$description}. Presente in un commit precedente: la credenziale va considerata compromessa e ruotata.");
        }
    }
}

exit($report->render());
