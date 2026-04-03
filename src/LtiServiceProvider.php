<?php

namespace RefBytes\Lti;

use RefBytes\Lti\Services\JwksService;
use RefBytes\Lti\Services\LaunchValidationService;
use RefBytes\Lti\Services\OidcLoginService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LtiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-lti')
            ->hasConfigFile()
            ->hasMigrations([
                'create_lti_platforms_table',
                'create_lti_launches_table',
            ])
            ->hasRoute('lti');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(JwksService::class);
        $this->app->singleton(OidcLoginService::class);
        $this->app->singleton(LaunchValidationService::class);
    }
}
