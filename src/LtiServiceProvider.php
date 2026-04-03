<?php

namespace RefBytes\Lti;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use RefBytes\Lti\Commands\LtiCommand;

class LtiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-lti')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_lti_table')
            ->hasCommand(LtiCommand::class);
    }
}
