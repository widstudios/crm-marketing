<?php

declare(strict_types=1);

/**
 * Elenca gli agenti della Factory con il loro contratto.
 *
 *   php tooling/scripts/list-agents.php
 *   php tooling/scripts/list-agents.php --phase=4
 *   php tooling/scripts/list-agents.php --check
 *
 * Serve a due cose: sapere quale agente invocare per una fase, e verificare che
 * ogni file di agente abbia le sezioni che il protocollo richiede — «Limiti» e
 * «Prompt completo» sono obbligatorie li' e in nessun altro tipo di documento,
 * e sono anche quelle che si dimenticano.
 */

require_once __DIR__.'/_support.php';

use function WidStudios\Tooling\hasOption;
use function WidStudios\Tooling\option;
use function WidStudios\Tooling\repositoryRoot;

use WidStudios\Tooling\Report;

const REQUIRED_AGENT_SECTIONS = [
    'Identità', 'Responsabilità', 'Input', 'Output', 'Limiti',
    'Workflow', 'Quality gate', 'Prompt completo', 'Errori comuni', 'Riferimenti',
];

$root = repositoryRoot();
$files = glob($root.'/agents/[0-9][0-9]-*.md') ?: [];

if ($files === []) {
    fwrite(STDERR, "Nessun agente trovato in agents/.\n");
    exit(2);
}

if (hasOption($argv, 'check')) {
    $report = new Report('Contratto degli agenti');

    foreach ($files as $path) {
        if (str_contains($path, '00-agent-protocol')) {
            continue;
        }

        $contents = (string) file_get_contents($path);
        $relative = substr($path, strlen($root) + 1);
        $report->counted();

        preg_match_all('/^##\s+(.+)$/m', $contents, $matches);
        $headings = array_map(static fn (string $h): string => mb_strtolower(trim($h), 'UTF-8'), $matches[1]);

        foreach (REQUIRED_AGENT_SECTIONS as $section) {
            foreach ($headings as $heading) {
                if (str_contains($heading, mb_strtolower($section, 'UTF-8'))) {
                    continue 2;
                }
            }

            $report->violation($relative, null, "Sezione obbligatoria mancante: «{$section}»");
        }

        // Un agente senza prompt utilizzabile e' una descrizione, non un agente.
        if (preg_match('/```markdown/', $contents) !== 1 && preg_match('/```text/', $contents) !== 1) {
            $report->violation($relative, null, 'Nessun blocco di prompt utilizzabile nella sezione «Prompt completo».');
        }
    }

    exit($report->render());
}

$phase = option($argv, 'phase');

printf("\n%-4s %-26s %-28s %s\n", '#', 'Agente', 'File', 'Fase');
echo str_repeat('-', 96)."\n";

foreach ($files as $path) {
    $contents = (string) file_get_contents($path);
    $name = basename($path);

    preg_match('/^#\s+(.+)$/m', $contents, $title);
    preg_match('/\|\s*\*\*Fase\*\*\s*\|\s*([^|]+)\|/', $contents, $phaseMatch);

    $number = substr($name, 0, 2);
    $label = trim($title[1] ?? $name);
    $agentPhase = trim($phaseMatch[1] ?? '—');

    if ($phase !== null && $phase !== '' && ! str_contains($agentPhase, $phase)) {
        continue;
    }

    printf("%-4s %-26s %-28s %s\n", $number, mb_substr($label, 0, 25), $name, $agentPhase);
}

echo "\nDettaglio: agents/README.md · protocollo comune: agents/00-agent-protocol.md\n\n";

exit(0);
