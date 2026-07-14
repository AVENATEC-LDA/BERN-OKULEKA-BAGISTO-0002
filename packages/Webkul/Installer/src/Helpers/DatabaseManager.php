<?php

namespace Webkul\Installer\Helpers;

use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PDO;
use Webkul\Installer\Database\Seeders\DatabaseSeeder as BagistoDatabaseSeeder;
use Webkul\Installer\Database\Seeders\ProductTableSeeder;
use Webkul\Product\Console\Commands\Indexer;

class DatabaseManager
{
    /**
     * Admin User ID for which the admin user will be created during installation.
     */
    const int USER_ID = 1;

    /**
     * Default admin name for which the admin user will be created during installation.
     */
    const string DEFAULT_ADMIN_NAME = 'Admin';

    /**
     * Default admin email for which the admin user will be created during installation.
     */
    const string DEFAULT_ADMIN_EMAIL = 'admin@example.com';

    /**
     * Default admin password for which the admin user will be created during installation.
     */
    const string DEFAULT_ADMIN_PASSWORD = 'admin123';

    /**
     * Check if the application is installed.
     */
    public function isInstalled(): bool
    {
        if (! file_exists(base_path('.env'))) {
            return false;
        }

        try {
            DB::connection()->getPDO();

            $isConnected = (bool) DB::connection()->getDatabaseName();

            if (! $isConnected) {
                return false;
            }

            $hasTable = Schema::hasTable('admins');

            if (! $hasTable) {
                return false;
            }

            $userCount = DB::table('admins')->count();

            if (! $userCount) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check database connection.
     */
    public function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Drop all the tables and migrate in the database.
     */
    public function ensureDatabaseExists(): bool
    {
        try {
            $connectionName = config('database.default', 'mysql');
            $connectionConfig = config("database.connections.{$connectionName}", []);
            $databaseName = $connectionConfig['database'] ?? null;

            if (blank($databaseName)) {
                throw new Exception('The database name is empty.');
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=mysql;charset=utf8mb4',
                $connectionConfig['host'] ?? '127.0.0.1',
                $connectionConfig['port'] ?? 3306,
            );

            $pdo = new PDO($dsn, $connectionConfig['username'] ?? null, $connectionConfig['password'] ?? null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $quotedDatabaseName = '`'.str_replace('`', '``', $databaseName).'`';

            $pdo->exec("CREATE DATABASE IF NOT EXISTS {$quotedDatabaseName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return true;
        } catch (Exception $e) {
            report($e);

            throw $e;
        }
    }

    /**
     * Drop all the tables and migrate in the database.
     */
    public function migrateFresh(): bool
    {
        try {
            $exitCode = Artisan::call('migrate:fresh', [
                '--force' => true,
                '--seed' => false,
            ]);

            $output = Artisan::output();

            if ($exitCode !== 0) {
                throw new Exception($output ?: "migrate:fresh failed with exit code {$exitCode}");
            }

            return true;
        } catch (Exception $e) {
            report($e);

            throw $e;
        }
    }

    /**
     * Seed the database.
     */
    public function seed($parameter): bool
    {
        try {
            app(BagistoDatabaseSeeder::class)->run($parameter);

            return true;
        } catch (Exception $e) {
            report($e);

            throw $e;
        }
    }

    /**
     * Generate fake product data.
     *
     * @return void|string
     */
    public function seedSampleProducts($parameters): bool
    {
        try {
            app(ProductTableSeeder::class)->run($parameters);

            Artisan::registerCommand(app(Indexer::class));

            Artisan::call('indexer:index', [
                '--mode' => ['full'],
                '--quiet' => true,
            ]);

            return true;
        } catch (Exception $e) {
            report($e);

            throw $e;
        }
    }

    /**
     * Create admin user.
     */
    public function createAdminUser(array $data = []): bool
    {
        try {
            DB::table('admins')->insert([
                'id' => self::USER_ID,
                'name' => $data['name'] ?? self::DEFAULT_ADMIN_NAME,
                'email' => $data['email'] ?? self::DEFAULT_ADMIN_EMAIL,
                'password' => bcrypt($data['password'] ?? self::DEFAULT_ADMIN_PASSWORD),
                'role_id' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (Exception $e) {
            report($e);

            throw $e;
        }
    }

    /**
     * Mark the application as installed by writing the installed marker.
     */
    public function markAsInstalled(): void
    {
        if (! file_exists(storage_path('installed'))) {
            File::put(storage_path('installed'), 'Bagisto is successfully installed.');
        }

        Event::dispatch('bagisto.installed');
    }
}
