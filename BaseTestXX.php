<?php

namespace Backpack\CRUD\Tests;

use Backpack\CRUD\BackpackServiceProvider;
use Orchestra\Testbench\TestCase;

abstract class BaseTestXX extends TestCase
{
    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            TestsServiceProvider::class,
            BackpackServiceProvider::class,
            TestsServiceProvider::class,
            \Spatie\Translatable\TranslatableServiceProvider::class,
        ];
    }
}
