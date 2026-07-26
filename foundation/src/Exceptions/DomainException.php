<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Exceptions;

/**
 * Radice delle eccezioni di dominio dei progetti.
 *
 * Un'eccezione di dominio dice che una regola di business non e' soddisfatta:
 * il lotto e' scaduto, la quantita' supera la disponibilita', lo stato non
 * ammette quella transizione. Non e' un errore tecnico, ed e' l'unica famiglia
 * di eccezioni che ha senso mostrare a un utente.
 *
 * Le eccezioni di dominio si costruiscono con costruttori nominati, mai con
 * new DomainException('messaggio'): il costruttore nominato mette il messaggio
 * in un punto solo e rende la condizione ricercabile.
 *
 *     final class BatchExpired extends DomainException
 *     {
 *         public static function for(Batch $batch): self
 *         {
 *             return new self("Il lotto {$batch->number} e' scaduto il ...");
 *         }
 *     }
 */
abstract class DomainException extends FoundationException {}
