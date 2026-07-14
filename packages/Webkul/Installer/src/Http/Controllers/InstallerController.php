<?php

namespace Webkul\Installer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Installer\Helpers\DatabaseManager;
use Webkul\Installer\Helpers\EnvironmentManager;
use Webkul\Installer\Helpers\ServerRequirements;

class InstallerController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ServerRequirements $serverRequirements,
        protected EnvironmentManager $environmentManager,
        protected DatabaseManager $databaseManager,
    ) {}

    /**
     * Display the installer welcome page.
     *
     * @return View
     */
    public function index()
    {
        $phpVersion = $this->serverRequirements->checkPHPversion();

        $requirements = $this->serverRequirements->validate();

        if (request()->has('locale')) {
            return redirect()->route('installer.index');
        }

        return view('installer::installer.index', compact('requirements', 'phpVersion'));
    }

    /**
     * Run migration.
     *
     * @return JsonResponse
     */
    public function runMigration(Request $request)
    {
        try {
            $this->environmentManager->generateEnv($request->all());

            $this->environmentManager->loadEnvConfigs();

            $isDatabaseConnected = $this->databaseManager->checkDatabaseConnection();

            if (! $isDatabaseConnected) {
                $this->databaseManager->ensureDatabaseExists();

                $isDatabaseConnected = $this->databaseManager->checkDatabaseConnection();
            }

            if (! $isDatabaseConnected) {
                return response()->json([
                    'migrated' => false,
                    'message' => 'Unable to connect to the database. Verify the host, port, name, username, and password.',
                ], 500);
            }

            $this->databaseManager->migrateFresh();

            return response()->json(['migrated' => true]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'migrated' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run seeder.
     *
     * @return JsonResponse
     */
    public function runSeeder()
    {
        $selectedParameters = request('selectedParameters');

        $allParameters = request('allParameters');

        $appLocale = $allParameters['app_locale'] ?? null;

        $appCurrency = $allParameters['app_currency'] ?? null;

        $allowedLocales = array_unique(
            array_merge(
                [($appLocale ?? 'en')],
                $selectedParameters['allowed_locales']
            )
        );

        $allowedCurrencies = array_unique(
            array_merge(
                [($appCurrency ?? 'USD')],
                $selectedParameters['allowed_currencies']
            )
        );

        try {
            $this->environmentManager->loadEnvConfigs();

            if (! $this->databaseManager->checkDatabaseConnection()) {
                return response()->json([
                    'seeded' => false,
                    'message' => 'Unable to connect to the database before seeding. Check DB credentials and host.',
                ], 500);
            }

            $isSeeded = $this->databaseManager->seed([
                'default_locales' => $appLocale,
                'default_currency' => $appCurrency,
                'allowed_locales' => $allowedLocales,
                'allowed_currencies' => $allowedCurrencies,
                'skip_admin_creation' => true,
            ]);

            $this->environmentManager->storageLink();

            return $isSeeded
                ? response()->json(['seeded' => true])
                : response()->json(['seeded' => false], 500);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'seeded' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Seed sample products.
     *
     * @return JsonResponse
     */
    public function seedSampleProducts()
    {
        $defaultLocale = config('app.locale');

        $allowedLocales = array_merge([$defaultLocale], request()->input('selectedLocales'));

        $defaultCurrency = config('app.currency');

        $allowedCurrencies = array_merge([$defaultCurrency], request()->input('selectedCurrencies'));

        try {
            $this->environmentManager->loadEnvConfigs();

            if (! $this->databaseManager->checkDatabaseConnection()) {
                return response()->json([
                    'sample_products_seeded' => false,
                    'message' => 'Unable to connect to the database before sample product seeding.',
                ], 500);
            }

            $this->databaseManager->seedSampleProducts([
                'default_locale' => $defaultLocale,
                'allowed_locales' => $allowedLocales,
                'default_currency' => $defaultCurrency,
                'allowed_currencies' => $allowedCurrencies,
            ]);

            return response()->json(['sample_products_seeded' => true]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'sample_products_seeded' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create admin user.
     *
     * @return JsonResponse
     */
    public function createAdminUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $this->environmentManager->loadEnvConfigs();

        if (! $this->databaseManager->checkDatabaseConnection()) {
            return response()->json([
                'admin_user_created' => false,
                'message' => 'Unable to connect to the database before creating admin user.',
            ], 500);
        }

        $data = $request->only(['name', 'email', 'password']);

        try {
            $created = $this->databaseManager->createAdminUser($data);

            if (! $created) {
                throw new \Exception('Failed to create admin user.');
            }

            $this->databaseManager->markAsInstalled();

            return response()->json(['admin_user_created' => true]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'admin_user_created' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
