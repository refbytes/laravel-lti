<?php

namespace RefBytes\Lti;

use RefBytes\Lti\Commands\DeactivateToolKeyCommand;
use RefBytes\Lti\Commands\GenerateToolKeyCommand;
use RefBytes\Lti\Commands\ListToolKeysCommand;
use RefBytes\Lti\Services\DeepLinkingService;
use RefBytes\Lti\Services\JwksService;
use RefBytes\Lti\Services\LaunchValidationService;
use RefBytes\Lti\Services\OidcLoginService;
use RefBytes\Lti\Services\PlatformOAuth2Service;
use RefBytes\Lti\Services\ToolKeyService;
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
                'create_lti_tool_keys_table',
            ])
            ->hasViews()
            ->hasRoute('lti')
            ->hasCommands([
                GenerateToolKeyCommand::class,
                ListToolKeysCommand::class,
                DeactivateToolKeyCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(JwksService::class);
        $this->app->singleton(OidcLoginService::class);
        $this->app->singleton(LaunchValidationService::class);
        $this->app->singleton(ToolKeyService::class);
        $this->app->singleton(PlatformOAuth2Service::class);
        $this->app->singleton(DeepLinkingService::class);
    }
}
