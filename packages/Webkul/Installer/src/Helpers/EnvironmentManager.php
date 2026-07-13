<?php

namespace Webkul\Installer\Helpers;

use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class EnvironmentManager
{
    /**
     * Generate `.env` file for installation.
     */
    public function generateEnv(array $data): bool|Exception
    {
        $envExamplePath = base_path('.env.example');

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            if (file_exists($envExamplePath)) {
                copy($envExamplePath, $envPath);
            } else {
                touch($envPath);
            }
        }

        return $this->updateEnvVariables($data);
    }

    /**
     * Get environment variable value from `.env` file.
     *
     * @param  mixed  $default
     */
    public function getEnvVariable(string $key, $default = null): string|bool
    {
        $lines = file(base_path('.env'));

        if ($lines === false) {
            return $default;
        }

        return $this->resolveEnvVariable($lines, $key, $default);
    }

    /**
     * Resolve an environment variable value from the given `.env` file lines.
     *
     * @param  array<int, string>  $lines
     * @param  mixed  $default
     */
    protected function resolveEnvVariable(array $lines, string $key, $default = null): string|bool
    {
        foreach ($lines as $line) {
            $line = trim($line);

            /**
             * Skip blank lines and comments.
             */
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            /**
             * Split on the first `=` only, so values that themselves contain
             * `=` (e.g. passwords or tokens) are preserved intact instead of
             * being truncated at the first `=`.
             */
            $rowValues = explode('=', $line, 2);

            if (count($rowValues) !== 2) {
                continue;
            }

            if (trim($rowValues[0]) === $key) {
                return trim(trim($rowValues[1]), '"');
            }
        }

        return $default;
    }

    /**
     * Resolve a value, falling back to the existing environment value when the incoming value is empty.
     */
    protected function resolveEnvValue(mixed $value, mixed $fallback = null): mixed
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return $value;
    }

    /**
     * Update a single environment variable in `.env` file.
     */
    public function updateEnvVariable(string $key, string $value, bool $addQuotes = false): void
    {
        $data = file_get_contents(base_path('.env'));

        // Check if $value contains spaces, and if so, add double quotes, or if $addQuotes is true.
        if ($addQuotes || preg_match('/\s/', $value)) {
            $value = '"'.$value.'"';
        }

        $data = preg_replace("/$key=(.*)/", "$key=$value", $data);

        file_put_contents(base_path('.env'), $data);
    }

    /**
     * Update multiple environment variables in `.env` file.
     */
    public function updateEnvVariables(array $data): bool
    {
        $envParams = [];

        if (isset($data['app_name'])) {
            $envParams['APP_NAME'] = $data['app_name'] ?? null;
            $envParams['APP_URL'] = $data['app_url'];
            $envParams['APP_CURRENCY'] = $data['app_currency'];
            $envParams['APP_LOCALE'] = $data['app_locale'];
            $envParams['APP_TIMEZONE'] = $data['app_timezone'];
        }

        if (isset($data['db_hostname']) || isset($data['db_name']) || isset($data['db_username']) || isset($data['db_password']) || isset($data['db_port']) || isset($data['db_connection'])) {
            $envParams['DB_HOST'] = $this->resolveEnvValue($data['db_hostname'] ?? null, $this->getEnvVariable('DB_HOST', 'mysql'));
            $envParams['DB_DATABASE'] = $this->resolveEnvValue($data['db_name'] ?? null, $this->getEnvVariable('DB_DATABASE', 'bagisto'));
            $envParams['DB_PREFIX'] = $this->resolveEnvValue($data['db_prefix'] ?? null, $this->getEnvVariable('DB_PREFIX', ''));
            $envParams['DB_USERNAME'] = $this->resolveEnvValue($data['db_username'] ?? null, $this->getEnvVariable('DB_USERNAME', 'bagisto'));
            $envParams['DB_PASSWORD'] = $this->resolveEnvValue($data['db_password'] ?? null, $this->getEnvVariable('DB_PASSWORD', 'bagisto'));
            $envParams['DB_CONNECTION'] = $this->resolveEnvValue($data['db_connection'] ?? null, $this->getEnvVariable('DB_CONNECTION', 'mysql'));
            $envParams['DB_PORT'] = (int) $this->resolveEnvValue($data['db_port'] ?? null, $this->getEnvVariable('DB_PORT', 3306));
        }

        try {
            foreach ($envParams as $key => $value) {
                $this->updateEnvVariable($key, (string) $value);
            }

            return true;
        } catch (Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Load temporary environment configurations and set up database connection for installation.
     */
    public function loadEnvConfigs(): void
    {
        /**
         * Setting application environment.
         */
        app()['env'] = $this->getEnvVariable('APP_ENV');

        /**
         * Setting application configuration.
         */
        config([
            'app.env' => $this->getEnvVariable('APP_ENV'),
            'app.name' => $this->getEnvVariable('APP_NAME'),
            'app.url' => $this->getEnvVariable('APP_URL'),
            'app.timezone' => $this->getEnvVariable('APP_TIMEZONE'),
            'app.locale' => $this->getEnvVariable('APP_LOCALE'),
            'app.currency' => $this->getEnvVariable('APP_CURRENCY'),
        ]);

        /**
         * Setting database configurations.
         */
        $databaseConnection = $this->getEnvVariable('DB_CONNECTION');

        DB::purge();

        config([
            "database.connections.{$databaseConnection}.host" => $this->getEnvVariable('DB_HOST'),
            "database.connections.{$databaseConnection}.port" => $this->getEnvVariable('DB_PORT'),
            "database.connections.{$databaseConnection}.database" => $this->getEnvVariable('DB_DATABASE'),
            "database.connections.{$databaseConnection}.username" => $this->getEnvVariable('DB_USERNAME'),
            "database.connections.{$databaseConnection}.password" => $this->getEnvVariable('DB_PASSWORD'),
            "database.connections.{$databaseConnection}.prefix" => $this->getEnvVariable('DB_PREFIX'),
        ]);

        DB::reconnect();
    }

    /**
     * Generate application key.
     */
    public function generateKey()
    {
        Artisan::call('key:generate');
    }

    /**
     * Storage link.
     */
    public function storageLink(): void
    {
        Artisan::call('storage:link');
    }

    /**
     * Optimize clear.
     */
    public function optimizeClear(): void
    {
        Artisan::call('optimize:clear');
    }
}
