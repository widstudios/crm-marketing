<?php

return [
    'app' => [
        'name' => 'CRM Marketing',
        'version' => '1.0.0',
        'env' => getenv('APP_ENV') ?: 'production',
        'debug' => getenv('APP_DEBUG') ?: false,
    ],
    
    'database' => [
        'driver' => 'sqlite',
        'path' => __DIR__ . '/../storage/database/crm.sqlite3',
    ],
    
    'modules' => [
        'contacts' => ['enabled' => true],
        'campaigns' => ['enabled' => true],
        'email' => ['enabled' => true],
        'analytics' => ['enabled' => true],
        'automation' => ['enabled' => true],
    ],
    
    'api' => [
        'prefix' => '/api/v1',
        'pagination' => 50,
    ],
];