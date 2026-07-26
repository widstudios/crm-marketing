<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Exceptions;

use RuntimeException;

/**
 * Radice di tutte le eccezioni della Foundation.
 *
 * Averne una permette a un progetto di intercettare "un problema della
 * Foundation" senza catturare Throwable, che nasconderebbe anche i propri
 * difetti.
 */
abstract class FoundationException extends RuntimeException {}
