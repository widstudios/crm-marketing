<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Contracts\Tenancy;

use WidStudios\Foundation\Tenancy\TenantStatus;

/**
 * Il tenant come lo vede la Foundation.
 *
 * La Foundation non conosce il model del progetto: conosce questo contratto.
 * Un progetto e' libero di aggiungere colonne, relazioni e comportamenti, purche'
 * il model implementi questi metodi.
 *
 * Fa parte del contratto pubblico: aggiungere un metodo e' MAJOR.
 */
interface Tenant
{
    /**
     * Identificativo tecnico nel database di piattaforma.
     */
    public function getKey(): int|string;

    /**
     * Identificativo stabile e leggibile, usato in chiavi di cache, nomi di
     * database, percorsi di storage e payload dei job.
     *
     * Non cambia mai per tutta la vita del tenant: cambiarlo significherebbe
     * perdere cache, file e riferimenti nei job gia' in coda.
     */
    public function getSlug(): string;

    /**
     * Nome del database dedicato a questo tenant.
     */
    public function getDatabaseName(): string;

    /**
     * @return list<string> domini da cui il tenant e' raggiungibile
     */
    public function getDomains(): array;

    public function getStatus(): TenantStatus;
}
