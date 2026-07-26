<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Audit;

use DateTimeImmutable;
use WidStudios\Foundation\Data\BaseData;

/**
 * Una voce di audit: chi ha fatto cosa, quando, da dove.
 *
 * Il contesto ammette solo scalari, deliberatamente: impedisce di passare un
 * model intero e di finire per registrare, insieme all'evento, dati sanitari o
 * fiscali che nel registro non devono comparire (rules/security.md R27).
 */
final readonly class AuditEntry extends BaseData
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function __construct(
        public string $event,
        public int|string|null $subjectId = null,
        public array $context = [],
        public int|string|null $actorId = null,
        public ?string $ipAddress = null,
        public ?DateTimeImmutable $occurredAt = null,
    ) {}
}
