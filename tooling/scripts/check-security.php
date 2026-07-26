<?php

declare(strict_types=1);

/**
 * Verifica le regole di sicurezza che si possono controllare leggendo il codice.
 *
 *   php tooling/scripts/check-security.php [percorso-progetto]
 *
 * Non sostituisce la revisione del Security Agent: uno script trova le forme,
 * non le intenzioni. Trova pero' tutte le forme, ogni volta, anche il venerdi'
 * sera prima di un rilascio, che e' esattamente quando una persona non le
 * troverebbe.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();
$appPath = is_dir($root.'/app') ? $root.'/app' : $root;

$report = new Report('Sicurezza');

/**
 * Ogni voce: espressione => [messaggio, cartelle escluse].
 *
 * @var array<string, array{0: string, 1: list<string>}>
 */
$rules = [
    // R25: con $guarded = [] una richiesta puo' assegnare qualunque colonna.
    '/\$guarded\s*=\s*\[\s*\]/' => ['$guarded = [] consente l\'assegnazione massiva di qualunque colonna: dichiarare $fillable.', []],

    // R22: il nome di una colonna non e' un valore e non puo' essere legato.
    '/DB::raw\(\s*[\'"][^\'"]*\$/' => ['Interpolazione di una variabile dentro DB::raw(): usare parametri legati.', []],
    '/->whereRaw\(\s*[\'"][^\'"]*\$/' => ['Interpolazione di una variabile dentro whereRaw(): usare parametri legati.', []],
    '/->orderByRaw\(\s*[\'"][^\'"]*\$/' => ['Interpolazione dentro orderByRaw(): usare una lista bianca di colonne.', []],
    '/->orderBy\(\s*\$request->/' => ['Ordinamento da un parametro della richiesta: lista bianca obbligatoria (BaseQuery::sortBy).', []],

    // Cinque punti dell'isolamento.
    '/Cache::(get|put|remember|rememberForever|forget|has|increment|decrement)\(\s*[\'"]/' => ['Chiave di cache scritta a mano: usare TenantCacheKey::for().', ['/Concerns/', '/Foundation/']],
    '/Storage::disk\(\s*[\'"]public[\'"]\s*\)/' => ['Disco pubblico: nessun file di cliente puo\' viverci.', []],

    // R27: dati personali e segreti nei log.
    '/Log::\w+\([^)]*\$request->all\(\)/' => ['Corpo completo della richiesta nei log: contiene dati personali e potenzialmente segreti.', []],
    '/Log::\w+\([^)]*->toArray\(\)/' => ['Model intero nei log: registrare solo identificativi e valori di stato.', []],

    // R36 / configurazione.
    '/\benv\(/' => ['Chiamata a env() fuori da config/: con la cache di configurazione attiva restituisce null.', ['/config/', '/Foundation/']],

    // Igiene.
    '/\b(dd|dump|var_dump|ray|print_r)\s*\(/' => ['Helper di debug residuo.', ['/tests/']],
    '/\bexec\(|\bshell_exec\(|\bpassthru\(|\bsystem\(/' => ['Esecuzione di comandi di sistema: verificare che nessun input dell\'utente vi arrivi.', []],
    '/\beval\s*\(/' => ['eval(): non ammesso.', []],
    '/\bunserialize\s*\(\s*\$/' => ['unserialize() su dato variabile: usare json_decode().', []],
];

foreach (files($appPath, ['php'], ['/vendor/']) as $path) {
    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);
    $report->counted();

    foreach ($rules as $pattern => [$message, $excluded]) {
        foreach ($excluded as $fragment) {
            if (str_contains($path, $fragment)) {
                continue 2;
            }
        }

        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === 0) {
            continue;
        }

        foreach ($matches[0] as [, $offset]) {
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
            $report->violation($relative, $line, $message);
        }
    }
}

// --- Configurazione dell'ambiente ------------------------------------------

if (file_exists($root.'/.env.example')) {
    $env = (string) file_get_contents($root.'/.env.example');

    if (preg_match('/^TELESCOPE_ENABLED\s*=\s*true/mi', $env) === 1) {
        $report->violation('.env.example', null, 'Telescope attivo nel file di esempio: espone richieste, query e talvolta dati.');
    }
}

// --- Job senza TenantAware ---------------------------------------------------

foreach (files($appPath, ['php']) as $path) {
    if (! str_contains($path, '/Jobs/')) {
        continue;
    }

    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);
    $report->counted();

    if (preg_match('/\bTenantAware\b/', $contents) !== 1) {
        $report->violation($relative, null, 'Job senza il trait TenantAware: verrebbe eseguito nel contesto che il worker aveva per ultimo.');
    }

    if (preg_match('/function\s+middleware\s*\(/', $contents) === 1) {
        $report->violation($relative, null, 'Job che sovrascrive middleware(): rimuove il ripristino del contesto tenant. Sovrascrivere jobMiddleware().');
    }

    foreach (['tries', 'backoff', 'timeout'] as $limit) {
        if (preg_match('/\$'.$limit.'\s*=/', $contents) !== 1) {
            $report->violation($relative, null, "Job senza \${$limit} dichiarato: un job senza limiti che fallisce puo' saturare i worker.");
        }
    }
}

exit($report->render());
