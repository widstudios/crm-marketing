<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Support;

use DateTimeImmutable;
use DateTimeZone;
use WidStudios\Foundation\Contracts\Support\Clock;

/**
 * Un orologio fermo, per i test.
 *
 * Rende verificabile cio' che altrimenti si potrebbe verificare solo aspettando:
 * scadenze, fine mese, cambio d'anno, periodi di ritenzione.
 *
 *     $this->app->instance(Clock::class, new FrozenClock('2026-12-31 23:59:00'));
 */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(DateTimeImmutable|string $now = 'now')
    {
        $this->now = $now instanceof DateTimeImmutable
            ? $now
            : new DateTimeImmutable($now, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function today(): DateTimeImmutable
    {
        return $this->now->setTime(0, 0);
    }

    public function travelTo(DateTimeImmutable|string $moment): void
    {
        $this->now = $moment instanceof DateTimeImmutable
            ? $moment
            : new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }

    public function advance(string $interval): void
    {
        $this->now = $this->now->modify($interval);
    }
}
