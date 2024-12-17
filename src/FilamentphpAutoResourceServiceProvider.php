<?php

namespace Martianatwork\FilamentphpAutoResource;

use Martianatwork\FilamentphpAutoResource\Commands\FilamentphpAutoResourceCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentphpAutoResourceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('filamentphp-auto-resource');
        //            ->hasConfigFile()
        //            ->hasViews()
        //            ->hasMigration('create_filamentphp_auto_resource_table')
        //            ->hasCommand(FilamentphpAutoResourceCommand::class)
    }
}
