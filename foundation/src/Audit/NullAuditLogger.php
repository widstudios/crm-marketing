<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Audit;

use WidStudios\Foundation\Contracts\Audit\AuditLogger;

/**
 * Implementazione predefinita, che non scrive nulla.
 *
 * E' il comportamento di un progetto senza il modulo 'audit'. La scelta di non
 * scrivere e' esplicita e visibile in configurazione: l'alternativa — un
 * contratto senza implementazione — produrrebbe un errore di risoluzione al
 * primo utilizzo, e in un punto lontano dalla causa.
 */
final class NullAuditLogger implements AuditLogger
{
    public function record(AuditEntry $entry): void
    {
        // Nessuna scrittura: il modulo 'audit' non e' attivo.
    }
}
