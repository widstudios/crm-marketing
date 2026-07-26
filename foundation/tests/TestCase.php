<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use WidStudios\Foundation\FoundationServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [FoundationServiceProvider::class];
    }
}
