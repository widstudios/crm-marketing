<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Concerns;

use WidStudios\Foundation\Audit\AuditEntry;
use WidStudios\Foundation\Contracts\Audit\AuditLogger;

/**
 * Registrazione delle mutazioni sensibili da dentro un'Action.
 *
 * La scrittura dell'audit sta nell'Action e non in un observer del model, per
 * una ragione precisa: l'observer sa che una riga e' cambiata, ma non sa
 * perche'. "Il campo status e' passato da A a B" non e' una voce di audit
 * utile; "l'utente X ha annullato la spedizione Y" lo e'.
 *
 *     $this->audit('movement.registered', $movement->getKey(), [
 *         'batch_id' => $movement->batch_id,
 *         'type' => $movement->type->value,
 *     ]);
 */
trait RecordsAudit
{
    /**
     * @param  array<string, scalar|null>  $context  mai contenuti sensibili: solo identificativi e valori di stato
     */
    protected function audit(string $event, int|string|null $subjectId = null, array $context = []): void
    {
        if (config('foundation.audit.enabled') !== true) {
            return;
        }

        app(AuditLogger::class)->record(new AuditEntry(
            event: $event,
            subjectId: $subjectId,
            context: $context,
        ));
    }
}
