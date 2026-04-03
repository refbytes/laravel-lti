<?php

namespace RefBytes\Lti\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use RefBytes\Lti\LtiServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'RefBytes\\Lti\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LtiServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->runMigrationStub('create_lti_platforms_table');
        $this->runMigrationStub('create_lti_launches_table');
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    private function runMigrationStub(string $name): void
    {
        $stub = __DIR__.'/../database/migrations/'.$name.'.php.stub';

        (include $stub)->up();
    }
}
