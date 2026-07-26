<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Contracts\Support;

use DateTimeImmutable;

/**
 * L'ora corrente come dipendenza esplicita.
 *
 * Il dominio non chiama now(): riceve un Clock. E' la differenza tra un test
 * che verifica il comportamento a fine mese e un test che si puo' eseguire solo
 * a fine mese.
 */
interface Clock
{
    public function now(): DateTimeImmutable;

    public function today(): DateTimeImmutable;
}
