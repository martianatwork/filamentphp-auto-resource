<?php

namespace Martianatwork\FilamentphpAutoResource\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Martianatwork\FilamentphpAutoResource\FilamentphpAutoResourceServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Martianatwork\\FilamentphpAutoResource\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            FilamentphpAutoResourceServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_filamentphp-auto-resource_table.php.stub';
        $migration->up();
        */
    }
}
