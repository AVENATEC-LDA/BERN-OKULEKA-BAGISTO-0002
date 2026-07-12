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
