<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Nessun tenant corrisponde alla richiesta.
 *
 * Produce 404 e non 403: un 403 confermerebbe che quel dominio o quello slug
 * esistono, e per un sistema multi-cliente l'elenco dei clienti e'
 * un'informazione riservata (rules/security.md R20).
 */
final class TenantNotFound extends FoundationException implements HttpExceptionInterface
{
    private function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }

    public static function forSlug(string $slug): self
    {
        return new self("Nessun tenant con slug '{$slug}'.");
    }

    public static function forHost(string $host): self
    {
        return new self("Nessun tenant associato al dominio '{$host}'.");
    }

    public function getStatusCode(): int
    {
        return 404;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
