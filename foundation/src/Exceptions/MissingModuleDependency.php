<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Exceptions;

/**
 * Un modulo attivo dipende da un modulo che non lo e'.
 *
 * Si scopre all'avvio e non a runtime, di proposito: una dipendenza mancante
 * scoperta al primo utilizzo si manifesta come un errore incomprensibile in un
 * punto lontano dalla causa.
 */
final class MissingModuleDependency extends FoundationException
{
    public static function for(string $module, string $dependency): self
    {
        return new self(
            "Il modulo '{$module}' richiede '{$dependency}', che non risulta attivo. "
            ."Aggiungerlo a config('foundation.modules.enabled') o disattivare '{$module}'."
        );
    }

    public static function circular(string $module): self
    {
        return new self(
            "Dipendenza circolare tra i moduli, rilevata a partire da '{$module}'. "
            .'Le dipendenze tra moduli devono formare un grafo aciclico.'
        );
    }
}
