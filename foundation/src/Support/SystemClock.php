<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Support;

use DateTimeImmutable;
use DateTimeZone;
use WidStudios\Foundation\Contracts\Support\Clock;

/**
 * L'orologio reale. Sempre UTC: la conversione al fuso dell'utente appartiene
 * alla presentazione, non ai dati (rules/sql.md R6).
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function today(): DateTimeImmutable
    {
        return $this->now()->setTime(0, 0);
    }
}
