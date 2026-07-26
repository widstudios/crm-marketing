<?php

declare(strict_types=1);

/**
 * Verifica la documentazione della Factory e dei progetti generati.
 *
 *   php tooling/scripts/check-docs.php                 link e ancore
 *   php tooling/scripts/check-docs.php --sections      sezioni obbligatorie
 *   php tooling/scripts/check-docs.php --placeholders  segnaposto residui
 *   php tooling/scripts/check-docs.php --orphans       documenti non collegati
 *   php tooling/scripts/check-docs.php --all           tutto quanto sopra
 *
 * Perche' esiste: la navigazione e' la struttura portante della Factory. Un
 * documento che nessuno raggiunge non esiste, e un link rotto spezza il
 * percorso di lettura in un punto che chi ha rinominato il file non vede.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\files;
use function WidStudios\Tooling\hasOption;
use function WidStudios\Tooling\repositoryRoot;
use function WidStudios\Tooling\withoutFences;

use WidStudios\Tooling\Report;

/**
 * Profili di documento.
 *
 * La maggior parte dei documenti segue l'ossatura predefinita. Tre classi no, e
 * non per tolleranza: hanno un contratto proprio, gia' dichiarato altrove, e
 * imporre loro le sezioni predefinite li peggiorerebbe.
 *
 *   adr         una decisione datata, non una guida. Non ha «best practice»:
 *               ha alternative valutate e conseguenze accettate.
 *   agent       la specifica di un attore, definita in agents/00-agent-protocol.md.
 *               «Limiti» e «Prompt completo» sono obbligatori li' e in nessun
 *               altro tipo di documento.
 *   repository  i file di primo livello che si rivolgono a chi arriva da fuori.
 *
 * Regola di riferimento: rules/documentation.md R3.
 */
const PROFILES = [
    'default' => ['Indice', 'Descrizione', 'Esempi', 'Best practice', 'Errori comuni', 'Checklist', 'Riferimenti'],
    'adr' => ['Indice', 'Contesto', 'Decisione', 'Alternative', 'Conseguenze', 'Riferimenti'],
    'agent' => ['Indice', 'Identità', 'Responsabilità', 'Input', 'Output', 'Limiti', 'Workflow', 'Quality gate', 'Prompt completo', 'Errori comuni', 'Riferimenti'],
    'repository' => ['Riferimenti'],
];

/*
 * Marcatori che in un documento consegnato non hanno ragione di esistere.
 *
 * L'elenco e' volutamente corto. Una formula generica come «da completare»
 * compare anche in prosa legittima («un tenant incompleto da completare a
 * mano»), e un controllo che segnala frasi corrette viene disattivato entro una
 * settimana: la sua utilita' netta e' negativa.
 */
const PLACEHOLDER_PATTERNS = ['TODO', 'TBD', 'FIXME', 'lorem ipsum', 'XXX'];

/** Cartelle escluse: il codice archiviato non segue le convenzioni della Factory. */
const EXCLUDED = ['/legacy/', '/node_modules/', '/vendor/'];

$root = repositoryRoot();
$all = hasOption($argv, 'all');
$documents = files($root, ['md'], EXCLUDED);

if ($documents === []) {
    fwrite(STDERR, "Nessun documento Markdown trovato sotto {$root}.\n");
    exit(2);
}

$exit = 0;

// --- Link interni e ancore --------------------------------------------------

if ($all || (! hasOption($argv, 'sections') && ! hasOption($argv, 'placeholders') && ! hasOption($argv, 'orphans'))) {
    $report = new Report('Link interni e ancore');

    foreach ($documents as $path) {
        $contents = withoutFences((string) file_get_contents($path));
        $relative = substr($path, strlen($root) + 1);
        $directory = dirname($path);

        preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $contents, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as [$target, $offset]) {
            $report->counted();
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;

            if (preg_match('#^(https?:|mailto:|#)#', $target) === 1) {
                continue;
            }

            [$file, $anchor] = array_pad(explode('#', $target, 2), 2, null);

            $resolved = $file === '' ? $path : realpath($directory.'/'.$file);

            if ($resolved === false || ! file_exists($resolved)) {
                $report->violation($relative, $line, "Link a un file inesistente: {$file}");

                continue;
            }

            if ($anchor === null || ! str_ends_with($resolved, '.md')) {
                continue;
            }

            if (! anchorExists((string) file_get_contents($resolved), $anchor)) {
                $report->violation($relative, $line, "Ancora inesistente: #{$anchor} in {$file}");
            }
        }
    }

    $exit = max($exit, $report->render());
}

// --- Sezioni obbligatorie ---------------------------------------------------

if ($all || hasOption($argv, 'sections')) {
    $report = new Report('Sezioni obbligatorie');

    foreach ($documents as $path) {
        $relative = substr($path, strlen($root) + 1);

        // I file di primo livello del repository e i CHANGELOG hanno una forma
        // propria: imporre loro la struttura dei documenti li peggiorerebbe.
        if (in_array(basename($path), ['CHANGELOG.md', 'CODEOWNERS.md'], strict: true)) {
            continue;
        }

        $contents = (string) file_get_contents($path);
        $report->counted();

        $profile = profileOf($relative);

        preg_match_all('/^##\s+(.+)$/m', $contents, $matches);
        $headings = array_map(static fn (string $h): string => trim(strip_tags($h)), $matches[1]);
        $normalized = array_map(static fn (string $h): string => mb_strtolower($h, 'UTF-8'), $headings);

        foreach (PROFILES[$profile] as $section) {
            $needle = mb_strtolower($section, 'UTF-8');

            foreach ($normalized as $heading) {
                if (str_contains($heading, $needle)) {
                    continue 2;
                }
            }

            $report->violation($relative, null, "Sezione obbligatoria mancante: «{$section}»");
        }
    }

    $exit = max($exit, $report->render());
}

// --- Segnaposto residui -----------------------------------------------------

if ($all || hasOption($argv, 'placeholders')) {
    $report = new Report('Segnaposto residui');

    foreach ($documents as $path) {
        $relative = substr($path, strlen($root) + 1);

        // Gli stub contengono segnaposto per definizione, e i documenti che
        // spiegano la convenzione li citano.
        if (str_contains($path, '/templates/') || str_contains($path, '_blueprint')) {
            continue;
        }

        // Oltre ai blocchi recintati si toglie il codice in linea: un documento
        // che prescrive «nessun `TODO` senza riferimento tracciato» non contiene
        // un TODO, contiene la regola che lo vieta.
        $contents = withoutInlineCode(withoutFences((string) file_get_contents($path)));
        $report->counted();

        foreach (PLACEHOLDER_PATTERNS as $pattern) {
            if (preg_match('/\b'.preg_quote($pattern, '/').'\b/i', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
                $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
                $report->violation($relative, $line, "Segnaposto residuo: «{$pattern}»");
            }
        }

        if (preg_match('/\{\{\s*[A-Za-z_]+\s*\}\}/', $contents, $m, PREG_OFFSET_CAPTURE) === 1) {
            $line = substr_count(substr($contents, 0, $m[0][1]), "\n") + 1;
            $report->violation($relative, $line, "Segnaposto di template non sostituito: {$m[0][0]}");
        }
    }

    $exit = max($exit, $report->render());
}

// --- Documenti orfani -------------------------------------------------------

if ($all || hasOption($argv, 'orphans')) {
    $report = new Report('Documenti orfani');

    $linked = [];

    foreach ($documents as $path) {
        $contents = withoutFences((string) file_get_contents($path));
        $directory = dirname($path);

        preg_match_all('/\[[^\]]*\]\(([^)\s#]+)/', $contents, $matches);

        foreach ($matches[1] as $target) {
            if (preg_match('#^(https?:|mailto:)#', $target) === 1) {
                continue;
            }

            $resolved = realpath($directory.'/'.$target);

            if ($resolved !== false) {
                $linked[str_replace('\\', '/', $resolved)] = true;
            }
        }
    }

    foreach ($documents as $path) {
        $relative = substr($path, strlen($root) + 1);
        $report->counted();

        // Un README e' l'indice della propria cartella: non deve essere
        // raggiunto, deve raggiungere.
        if (basename($path) === 'README.md' || basename($path) === 'CLAUDE.md') {
            continue;
        }

        if (! isset($linked[$path])) {
            $report->violation($relative, null, 'Documento non collegato da nessun altro documento.');
        }
    }

    $exit = max($exit, $report->render());
}

exit($exit);

/**
 * Verifica che un'ancora corrisponda a un titolo del documento.
 *
 * L'ancora si ricava dal titolo con le regole di GitHub: minuscolo, spazi in
 * trattini, punteggiatura rimossa. Gli accenti restano, ed e' il motivo per cui
 * non basta un confronto ASCII.
 */
function anchorExists(string $contents, string $anchor): bool
{
    preg_match_all('/^#{1,6}\s+(.+?)\s*$/m', $contents, $matches);

    // Titoli ripetuti nello stesso documento producono ancore distinte, con un
    // suffisso progressivo: la seconda occorrenza di «Esempi» e' #esempi-1.
    // Senza questa contabilita' i link corretti risultano rotti, ed e' peggio
    // di non controllare affatto.
    $seen = [];

    foreach ($matches[1] as $heading) {
        $slug = slug($heading);
        $occurrence = $seen[$slug] ?? 0;
        $seen[$slug] = $occurrence + 1;

        $candidate = $occurrence === 0 ? $slug : "{$slug}-{$occurrence}";

        if ($candidate === strtolower($anchor)) {
            return true;
        }
    }

    return false;
}

function slug(string $heading): string
{
    $heading = strip_tags($heading);
    $heading = preg_replace('/`([^`]*)`/', '$1', $heading) ?? $heading;
    $heading = mb_strtolower($heading, 'UTF-8');
    $heading = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $heading) ?? $heading;
    // Gli spazi diventano trattini uno a uno, senza accorpare: e' cosi' che
    // GitHub costruisce le ancore, ed e' il motivo per cui un titolo con un
    // simbolo in mezzo produce due trattini consecutivi.
    $heading = str_replace(' ', '-', trim($heading));

    return $heading;
}

/**
 * Profilo applicabile a un documento, dedotto dalla sua posizione.
 *
 * La deduzione e' dalla posizione e non da un'intestazione dentro il file:
 * un profilo dichiarabile dall'autore sarebbe un profilo scelto per comodita'
 * il giorno in cui una sezione costa fatica.
 */
function profileOf(string $relative): string
{
    return match (true) {
        str_starts_with($relative, 'architecture/decisions/') && basename($relative) !== 'README.md' => 'adr',
        // 00-agent-protocol.md non e' un agente: e' il contratto comune a tutti.
        (bool) preg_match('#^agents/(?!00-)\d\d-#', $relative) => 'agent',
        in_array($relative, ['README.md', 'CONTRIBUTING.md'], strict: true) => 'repository',
        default => 'default',
    };
}

/**
 * Sostituisce il codice in linea con spazi, mantenendo la lunghezza del testo
 * perche' i numeri di riga restino corretti.
 */
function withoutInlineCode(string $markdown): string
{
    return (string) preg_replace_callback(
        '/`[^`\n]*`/',
        static fn (array $m): string => str_repeat(' ', strlen($m[0])),
        $markdown,
    );
}
