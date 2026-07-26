<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Exceptions;

/**
 * Ordinamento richiesto su una colonna non ammessa.
 *
 * Il messaggio elenca le colonne consentite: sono gia' pubbliche per chi usa
 * l'elenco, e senza l'elenco l'errore non e' correggibile da chi lo riceve.
 */
final class InvalidSortColumn extends FoundationException
{
    /**
     * @param  list<string>  $allowed
     */
    public static function for(string $column, array $allowed): self
    {
        return new self(sprintf(
            "Ordinamento non consentito su '%s'. Colonne ammesse: %s.",
            $column,
            $allowed === [] ? 'nessuna' : implode(', ', $allowed),
        ));
    }
}
