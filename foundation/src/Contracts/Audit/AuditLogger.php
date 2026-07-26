<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Contracts\Audit;

use WidStudios\Foundation\Audit\AuditEntry;

/**
 * Registrazione delle mutazioni su dati sensibili.
 *
 * La Foundation definisce il contratto; l'implementazione che scrive davvero
 * arriva dal modulo 'audit'. Questa separazione permette a un progetto senza
 * requisiti di tracciamento di non portarsi dietro le tabelle relative, senza
 * che il codice applicativo cambi.
 *
 * Cio' che viene registrato: chi, cosa, quando, da dove.
 * Cio' che non viene mai registrato: il contenuto sensibile della modifica
 * (rules/security.md R27).
 */
interface AuditLogger
{
    public function record(AuditEntry $entry): void;
}
