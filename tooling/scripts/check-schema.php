<?php

declare(strict_types=1);

/**
 * Verifica le migration: cartella, reversibilita', tipi, vincoli, indici.
 *
 *   php tooling/scripts/check-schema.php [percorso-progetto]
 *
 * Lo schema e' la parte piu' costosa da correggere: il codice si riscrive, i
 * dati vanno migrati. Questi controlli costano un secondo e prevengono
 * progetti di migrazione.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;

use WidStudios\Tooling\Report;

$root = realpath($argv[1] ?? getcwd()) ?: getcwd();
$migrations = $root.'/database/migrations';

if (! is_dir($migrations)) {
    fwrite(STDERR, "Nessuna cartella database/migrations sotto {$root}.\n");
    exit(2);
}

$report = new Report('Schema e migration');

foreach (files($migrations, ['php']) as $path) {
    $contents = (string) file_get_contents($path);
    $relative = substr($path, strlen($root) + 1);
    $report->counted();

    $isTenant = str_contains($path, '/tenant/');
    $isLandlord = str_contains($path, '/landlord/');

    if (! $isTenant && ! $isLandlord) {
        $report->violation($relative, null, 'Migration fuori da tenant/ o landlord/: la destinazione deve essere esplicita.');
    }

    if (preg_match('/function\s+down\s*\(/', $contents) !== 1) {
        $report->violation($relative, null, 'Nessun down(): il rollback su N database sarebbe impossibile.');
    }

    // Il model puo' cambiare o sparire; la migration resta nella storia per
    // sempre.
    if (preg_match('/\\\\App\\\\Models\\\\|use App\\\\Models\\\\/', $contents) === 1) {
        $report->violation($relative, null, 'Riferimento a un model applicativo: usare il query builder.');
    }

    // Una trasformazione di dati qui rende il deploy lentissimo su N database
    // e il rollback impossibile.
    if (preg_match('/DB::table\([^)]*\)->(update|insert|delete)\(|->each\(|->chunk(ById)?\(/', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, 'Trasformazione di dati in una migration di schema: usare un comando Artisan riprendibile.');
    }

    if ($isTenant && preg_match('/[\'"]tenant_id[\'"]/', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, 'Colonna tenant_id in una migration tenant: il tenant e\' il database.');
    }

    // Errori di arrotondamento non recuperabili nei totali.
    if (preg_match('/->(float|double)\(/', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, 'Tipo float o double: usare decimal per quantita\' e importi.');
    }

    // Aggiungere un valore a un enum MySQL richiede un ALTER TABLE su tutti i
    // tenant.
    if (preg_match('/\$table->enum\(/', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
        $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
        $report->violation($relative, $line, 'Tipo enum di MySQL: usare varchar con cast a enum PHP.');
    }

    // Una chiave esterna senza vincolo accumula dati orfani in silenzio.
    if (preg_match_all('/->foreignId\([^)]*\)((?:(?!;).)*);/s', $contents, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0) {
        foreach ($matches as $match) {
            if (preg_match('/->constrained\(/', $match[1][0]) !== 1) {
                $line = substr_count(substr($contents, 0, $match[0][1]), "\n") + 1;
                $report->violation($relative, $line, 'Chiave esterna senza ->constrained(): nessun vincolo di integrita\' referenziale.');
            }
        }
    }

    if ($isLandlord && preg_match('/protected\s+\$connection/', $contents) !== 1) {
        $report->violation($relative, null, 'Migration landlord senza $connection dichiarata: dentro un contesto tenant creerebbe la tabella nel database sbagliato.');
    }
}

exit($report->render());
