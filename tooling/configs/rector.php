<?php

declare(strict_types=1);

/*
 * Rector — configurazione condivisa della Factory.
 *
 * Rector serve a due cose, e conviene tenerle distinte perche' hanno rischi
 * diversi:
 *
 *   1. AGGIORNAMENTI DI VERSIONE. Portare il codice a una versione nuova di PHP
 *      o del framework: e' lavoro meccanico, esteso, e farlo a mano introduce
 *      errori di distrazione proprio dove sono piu' difficili da vedere.
 *
 *   2. REGOLE DI QUALITA' RICORRENTI. Aggiungere tipi mancanti, promuovere le
 *      proprieta' nel costruttore, sostituire costrutti superati.
 *
 * Il secondo uso e' quello da tenere sotto controllo: una regola che modifica
 * migliaia di righe in un colpo rende impossibile la revisione, e una revisione
 * impossibile equivale a nessuna revisione. Si applica una regola per volta,
 * con un commit per regola.
 *
 * Rector NON gira in pipeline in modalita' di scrittura. Gira in --dry-run:
 * il codice lo cambia una persona, dopo aver guardato cosa cambia.
 */

use Rector\Config\RectorConfig;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeCallRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/../../app',
        __DIR__.'/../../database',
        __DIR__.'/../../routes',
        __DIR__.'/../../tests',
    ])
    ->withSkip([
        __DIR__.'/../../legacy',
        __DIR__.'/../../bootstrap/cache',
        __DIR__.'/../../storage',

        // Le migration restano nella storia per sempre: modernizzarle non
        // porta alcun beneficio e cambia file che nessuno rileggera' mai.
        __DIR__.'/../../database/migrations',
    ])
    ->withPhpSets(php84: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::TYPE_DECLARATION,
        SetList::DEAD_CODE,
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
        ReturnTypeFromStrictNativeCallRector::class,
        ExplicitNullableParamTypeRector::class,
    ])
    ->withSkip([
        // Rimuove i commenti insieme al codice morto: i commenti spiegano il
        // perche', e il perche' sopravvive al codice che lo motivava.
        \Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector::class => [
            __DIR__.'/../../app/Domain',
        ],
    ])
    ->withImportNames(removeUnusedImports: true)
    ->withParallel();
