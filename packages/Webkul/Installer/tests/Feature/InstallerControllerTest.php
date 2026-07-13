<?php

use Illuminate\Http\Request;
use Mockery;
use Webkul\Installer\Helpers\DatabaseManager;
use Webkul\Installer\Helpers\EnvironmentManager;
use Webkul\Installer\Helpers\ServerRequirements;
use Webkul\Installer\Http\Controllers\InstallerController;

it('does not clear caches during seeding so the installation request is not interrupted', function () {
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    $databaseManager = Mockery::mock(DatabaseManager::class);
    $serverRequirements = Mockery::mock(ServerRequirements::class);

    $environmentManager->shouldNotReceive('updateEnvVariables');
    $environmentManager->shouldReceive('loadEnvConfigs')->once();
    $databaseManager->shouldReceive('seed')->once()->andReturn(true);
    $environmentManager->shouldReceive('storageLink')->once();

    $request = Request::create('/install/api/run-seeder', 'POST', [
        'selectedParameters' => [
            'allowed_locales' => ['en'],
        ],
        'allParameters' => [
            'app_locale' => 'en',
            'app_currency' => 'USD',
        ],
    ]);

    $this->app->instance('request', $request);

    $controller = new InstallerController($serverRequirements, $environmentManager, $databaseManager);

    $response = $controller->runSeeder();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toHaveKey('seeded', true);
});

it('creates the database before migration when the selected database is missing', function () {
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    $databaseManager = Mockery::mock(DatabaseManager::class);
    $serverRequirements = Mockery::mock(ServerRequirements::class);

    $environmentManager->shouldReceive('generateEnv')->once();
    $environmentManager->shouldReceive('loadEnvConfigs')->once();
    $databaseManager->shouldReceive('checkDatabaseConnection')->once()->andReturn(false);
    $databaseManager->shouldReceive('ensureDatabaseExists')->once()->andReturn(true);
    $databaseManager->shouldReceive('checkDatabaseConnection')->once()->andReturn(true);
    $databaseManager->shouldReceive('migrateFresh')->once()->andReturn(true);

    $request = Request::create('/install/api/run-migration', 'POST', [
        'db_hostname' => '127.0.0.1',
        'db_port' => '3306',
        'db_name' => 'bagisto',
        'db_username' => 'root',
        'db_password' => '',
    ]);

    $this->app->instance('request', $request);

    $controller = new InstallerController($serverRequirements, $environmentManager, $databaseManager);

    $response = $controller->runMigration($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toHaveKey('migrated', true);
});

it('returns the underlying seeding exception message when the seeder fails', function () {
    $environmentManager = Mockery::mock(EnvironmentManager::class);
    $databaseManager = Mockery::mock(DatabaseManager::class);
    $serverRequirements = Mockery::mock(ServerRequirements::class);

    $environmentManager->shouldReceive('loadEnvConfigs')->once();
    $databaseManager->shouldReceive('seed')->once()->andThrow(new RuntimeException('duplicate entry')); 

    $request = Request::create('/install/api/run-seeder', 'POST', [
        'selectedParameters' => [
            'allowed_locales' => ['en'],
        ],
        'allParameters' => [
            'app_locale' => 'en',
            'app_currency' => 'USD',
        ],
    ]);

    $this->app->instance('request', $request);

    $controller = new InstallerController($serverRequirements, $environmentManager, $databaseManager);

    $response = $controller->runSeeder();

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true)['message'])->toBe('duplicate entry');
});
