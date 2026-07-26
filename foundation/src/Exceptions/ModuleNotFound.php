<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Exceptions;

final class ModuleNotFound extends FoundationException
{
    public static function named(string $name): self
    {
        return new self("Nessun modulo registrato con nome '{$name}'.");
    }
}
