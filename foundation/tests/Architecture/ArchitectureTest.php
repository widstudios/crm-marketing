<?php

declare(strict_types=1);

arch('tipizzazione stretta in ogni file')
    ->expect('WidStudios\Foundation')
    ->toUseStrictTypes();

arch('nessun helper di debug residuo')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'print_r'])
    ->not->toBeUsed();

arch('i contratti sono interfacce')
    ->expect('WidStudios\Foundation\Contracts')
    ->toBeInterfaces();

arch('le eccezioni estendono la radice della Foundation')
    ->expect('WidStudios\Foundation\Exceptions')
    ->toExtend('WidStudios\Foundation\Exceptions\FoundationException');

arch('i bootstrapper implementano il contratto')
    ->expect('WidStudios\Foundation\Tenancy\Bootstrappers')
    ->toImplement('WidStudios\Foundation\Contracts\Tenancy\TenantBootstrapper')
    ->toBeFinal();

arch('i resolver implementano il contratto')
    ->expect('WidStudios\Foundation\Tenancy\Resolvers')
    ->toImplement('WidStudios\Foundation\Contracts\Tenancy\TenantResolver')
    ->toBeFinal();

arch('gli eventi sono readonly')
    ->expect('WidStudios\Foundation\Tenancy\Events')
    ->toBeReadonly();

arch('la Foundation non contiene logica di dominio verticale')
    ->expect('WidStudios\Foundation')
    ->not->toUse(['App\Domain', 'App\Models']);
