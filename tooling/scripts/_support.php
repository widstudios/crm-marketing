<?php

declare(strict_types=1);

namespace WidStudios\Tooling;

/**
 * Supporto comune agli script di verifica.
 *
 * Gli script della Factory hanno un contratto uniforme, e non e' una questione
 * di eleganza: girano in pipeline, e una pipeline che non distingue "nessun
 * problema" da "non ho potuto controllare" e' peggio di nessuna pipeline.
 *
 *   uscita 0    nessuna violazione
 *   uscita 1    violazioni trovate, elencate con file e riga
 *   uscita 2    lo script non ha potuto eseguire la verifica
 *
 * Il terzo codice esiste perche' il caso peggiore non e' il fallimento: e'
 * l'errore silenzioso che passa per successo.
 */
final class Report
{
    /** @var list<array{file: string, line: int|null, message: string}> */
    private array $violations = [];

    private int $checked = 0;

    public function __construct(
        private readonly string $title,
    ) {}

    public function violation(string $file, ?int $line, string $message): void
    {
        $this->violations[] = ['file' => $file, 'line' => $line, 'message' => $message];
    }

    public function counted(int $n = 1): void
    {
        $this->checked += $n;
    }

    public function isClean(): bool
    {
        return $this->violations === [];
    }

    /**
     * Stampa l'esito e restituisce il codice di uscita.
     */
    public function render(): int
    {
        echo "\n{$this->title}\n";
        echo str_repeat('-', max(20, strlen($this->title)))."\n";

        if ($this->violations === []) {
            echo "  Nessuna violazione. Elementi verificati: {$this->checked}.\n\n";

            return 0;
        }

        foreach ($this->violations as $v) {
            $position = $v['line'] === null ? $v['file'] : "{$v['file']}:{$v['line']}";
            echo "  {$position}\n      {$v['message']}\n";
        }

        $n = count($this->violations);
        echo "\n  {$n} violazioni su {$this->checked} elementi verificati.\n\n";

        return 1;
    }
}

/**
 * Elenca i file corrispondenti alle estensioni indicate, sotto una radice.
 *
 * @param  list<string>  $extensions
 * @param  list<string>  $exclude  frammenti di percorso da saltare
 * @return list<string>  percorsi relativi alla radice
 */
function files(string $root, array $extensions, array $exclude = []): array
{
    if (! is_dir($root)) {
        return [];
    }

    $exclude = array_merge($exclude, ['/vendor/', '/node_modules/', '/.git/', '/storage/framework/']);
    $result = [];

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
    );

    /** @var \SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        $normalized = str_replace('\\', '/', $path);

        foreach ($exclude as $fragment) {
            if (str_contains($normalized, $fragment)) {
                continue 2;
            }
        }

        if (! in_array(strtolower($file->getExtension()), $extensions, strict: true)) {
            continue;
        }

        $result[] = $normalized;
    }

    sort($result);

    return $result;
}

/**
 * Numero della riga in cui compare la prima occorrenza di una sottostringa.
 */
function lineOf(string $contents, string $needle): ?int
{
    $offset = strpos($contents, $needle);

    if ($offset === false) {
        return null;
    }

    return substr_count(substr($contents, 0, $offset), "\n") + 1;
}

/**
 * Rimuove i blocchi di codice recintati da un testo Markdown, sostituendoli con
 * righe vuote perche' i numeri di riga restino corretti.
 *
 * Serve a non segnalare come difetti gli esempi: un documento che mostra un
 * link rotto come esempio di link rotto non ha un link rotto.
 */
function withoutFences(string $markdown): string
{
    $lines = explode("\n", $markdown);
    $inFence = false;

    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*```/', $line) === 1) {
            $inFence = ! $inFence;
            $lines[$i] = '';

            continue;
        }

        if ($inFence) {
            $lines[$i] = '';
        }
    }

    return implode("\n", $lines);
}

/**
 * Radice del repository, dedotta dalla posizione di questo file.
 */
function repositoryRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * Legge un'opzione da riga di comando nella forma --nome oppure --nome=valore.
 *
 * @param  list<string>  $argv
 */
function option(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $argument) {
        if ($argument === "--{$name}") {
            return '';
        }

        if (str_starts_with($argument, "--{$name}=")) {
            return substr($argument, strlen($name) + 3);
        }
    }

    return $default;
}

/**
 * @param  list<string>  $argv
 */
function hasOption(array $argv, string $name): bool
{
    return option($argv, $name) !== null;
}
