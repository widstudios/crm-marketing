<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Exceptions;

/**
 * Il codice presuppone un tenant e non c'e'.
 *
 * E' sempre un difetto, mai un caso previsto: significa che un percorso di
 * esecuzione raggiunge codice scritto per un cliente senza sapere quale. Il
 * comportamento corretto e' fermarsi rumorosamente, perche' l'alternativa —
 * proseguire sul database di piattaforma — produce risultati silenziosamente
 * sbagliati.
 */
final class MissingTenantContext extends FoundationException
{
    public static function inCurrentScope(): self
    {
        return new self(
            'Nessun tenant nel contesto corrente. '
            .'Se si tratta di un job, verificare il trait TenantAware; '
            .'se di un comando, eseguirlo tramite tenants:artisan.'
        );
    }

    public static function forJob(string $jobClass): self
    {
        return new self(
            "Il job {$jobClass} e' stato eseguito senza contesto tenant. "
            ."Il tenant non e' stato catturato al momento della messa in coda."
        );
    }

    public static function forCommand(string $command): self
    {
        return new self(
            "Il comando {$command} richiede un contesto tenant. "
            .'Eseguirlo tramite: php artisan tenants:artisan "'.$command.'"'
        );
    }
}
