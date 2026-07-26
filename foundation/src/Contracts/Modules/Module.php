<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Contracts\Modules;

/**
 * Un modulo installabile.
 *
 * Un modulo e' indipendente per definizione: disattivarlo deve lasciare il
 * software funzionante, salvo per le funzionalita' che il modulo forniva. Le
 * dipendenze dichiarate in dependsOn() sono l'unica eccezione ammessa, e sono
 * verificate all'avvio.
 */
interface Module
{
    /**
     * Identificativo stabile, in snake_case: 'audit', 'documents', 'cms'.
     */
    public function name(): string;

    public function version(): string;

    /**
     * Sintesi di cosa fa il modulo, in italiano, in una riga.
     */
    public function description(): string;

    /**
     * Nomi dei moduli senza i quali questo non puo' funzionare.
     *
     * @return list<string>
     */
    public function dependsOn(): array;

    /**
     * Permessi introdotti dal modulo, nella forma <risorsa>.<azione>.
     *
     * Il seeder di sistema li crea e li assegna al ruolo amministratore: senza
     * questo elenco, la funzionalita' resta invisibile a tutti dopo il deploy.
     *
     * @return list<string>
     */
    public function permissions(): array;

    /**
     * Percorsi delle migration tenant introdotte dal modulo.
     *
     * @return list<string>
     */
    public function tenantMigrationPaths(): array;

    /**
     * Percorsi delle migration di piattaforma introdotte dal modulo.
     *
     * @return list<string>
     */
    public function landlordMigrationPaths(): array;
}
