<?php

declare(strict_types=1);

/*
 * Configurazione della Foundation.
 *
 * Ogni chiave di questo file fa parte del contratto pubblico: rimuoverla o
 * cambiarne il significato e' una modifica MAJOR (governance/versioning.md).
 *
 * Nessun accesso a env() fuori da questo file: il codice legge sempre da
 * config('foundation....'), altrimenti la cache di configurazione lo rompe.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Tenancy
    |--------------------------------------------------------------------------
    */

    'tenancy' => [

        // Connessione al database di piattaforma. Non contiene mai dati di dominio.
        'landlord_connection' => env('FOUNDATION_LANDLORD_CONNECTION', 'landlord'),

        // Connessione riconfigurata a ogni cambio di tenant.
        'tenant_connection' => env('FOUNDATION_TENANT_CONNECTION', 'tenant'),

        // Modello di connessione da cui la connessione tenant eredita host, porta e credenziali.
        'tenant_connection_template' => env('FOUNDATION_TENANT_TEMPLATE', 'mysql'),

        // Prefisso del nome del database di ogni tenant: <prefisso><slug>.
        'database_prefix' => env('FOUNDATION_TENANT_DB_PREFIX', 'tenant_'),

        /*
         * Resolver applicati in ordine: il primo che restituisce un tenant vince.
         * HeaderResolver rifiuta di risolvere fuori dagli ambienti dichiarati in
         * 'header_resolver_environments': e' una comodita' di sviluppo, non un
         * meccanismo di produzione (rules/security.md R7).
         */
        'resolvers' => [
            WidStudios\Foundation\Tenancy\Resolvers\DomainResolver::class,
            WidStudios\Foundation\Tenancy\Resolvers\HeaderResolver::class,
        ],

        'header_name' => 'X-Tenant',

        'header_resolver_environments' => ['local', 'testing'],

        // Domini che non appartengono mai a un tenant: sono il pannello di piattaforma.
        'central_domains' => [
            env('FOUNDATION_CENTRAL_DOMAIN', 'admin.localhost'),
        ],

        /*
         * Bootstrapper eseguiti, in ordine, quando il contesto tenant si apre;
         * la chiusura li percorre in ordine inverso.
         */
        'bootstrappers' => [
            WidStudios\Foundation\Tenancy\Bootstrappers\DatabaseBootstrapper::class,
            WidStudios\Foundation\Tenancy\Bootstrappers\CacheBootstrapper::class,
            WidStudios\Foundation\Tenancy\Bootstrappers\FilesystemBootstrapper::class,
            WidStudios\Foundation\Tenancy\Bootstrappers\QueueBootstrapper::class,
        ],

        // Numero di tenant elaborati per lotto dai comandi tenants:*.
        'chunk_size' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Ogni chiave prodotta da TenantCacheKey e' composta cosi':
    |
    |   <prefix><separator><ambito><separator><chiave>
    |
    | dove l'ambito e' lo slug del tenant, oppure 'landlord'.
    */

    'cache' => [
        'prefix' => env('FOUNDATION_CACHE_PREFIX', 'ws'),
        'separator' => ':',
        'landlord_scope' => 'landlord',
        'default_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Ogni tenant ha un disco privato. Nessun file di cliente su disco pubblico
    | (rules/security.md R32).
    */

    'storage' => [
        'disk' => env('FOUNDATION_TENANT_DISK', 'tenant'),
        'root' => env('FOUNDATION_TENANT_STORAGE_ROOT', storage_path('tenants')),
        'visibility' => 'private',
        'signed_url_ttl' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Code
    |--------------------------------------------------------------------------
    */

    'queue' => [
        // Chiave con cui lo slug del tenant viaggia nel payload del job.
        'payload_key' => 'tenant',

        'queues' => [
            'high' => 'high',
            'provisioning' => 'provisioning',
            'default' => 'default',
            'notifications' => 'notifications',
            'documents' => 'documents',
            'integrations' => 'integrations',
            'bulk' => 'bulk',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Moduli
    |--------------------------------------------------------------------------
    */

    'modules' => [
        // Moduli attivi. Un modulo assente qui non registra nulla.
        'enabled' => [],

        // Percorso in cui il ModuleManager cerca i moduli locali al progetto.
        'path' => base_path('app/Modules'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    |
    | La Foundation definisce il contratto; l'implementazione arriva dal modulo
    | 'audit'. In assenza del modulo il logger e' quello nullo, e la scrittura
    | non avviene: e' una scelta esplicita, non un fallimento silenzioso.
    */

    'audit' => [
        'enabled' => true,
        'logger' => WidStudios\Foundation\Audit\NullAuditLogger::class,
    ],
];
