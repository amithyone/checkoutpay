<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class SetupController extends Controller
{
    /**
     * Show setup page
     */
    public function index()
    {
        // Always show setup page for now (remove redirect check temporarily)
        // if ($this->isConfigured()) {
        //     return redirect('/admin');
        // }

        return view('setup.index');
    }

    /**
     * Test database connection
     */
    public function testDatabase(Request $request)
    {
        try {
            $request->validate([
                'host' => 'required|string',
                'port' => 'required|numeric',
                'database' => 'required|string',
                'username' => 'required|string',
                'password' => 'nullable|string',
            ]);

            // Test connection
            $connection = [
                'driver' => 'mysql',
                'host' => $request->host,
                'port' => $request->port,
                'database' => $request->database,
                'username' => $request->username,
                'password' => $request->password ?? '',
            ];

            config(['database.connections.test' => $connection]);

            DB::connection('test')->getPdo();

            return response()->json([
                'success' => true,
                'message' => 'Database connection successful!',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->errors()),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Save database configuration
     */
    public function saveDatabase(Request $request)
    {
        try {
            $request->validate([
                'host' => 'required|string',
                'port' => 'required|numeric',
                'database' => 'required|string',
                'username' => 'required|string',
                'password' => 'nullable|string',
            ]);

            // Test connection first
            $connection = [
                'driver' => 'mysql',
                'host' => $request->host,
                'port' => $request->port,
                'database' => $request->database,
                'username' => $request->username,
                'password' => $request->password ?? '',
            ];

            config(['database.connections.test' => $connection]);
            DB::connection('test')->getPdo();

            // Update .env file
            $this->updateEnvFile([
                'DB_HOST' => $request->host,
                'DB_PORT' => (string)$request->port,
                'DB_DATABASE' => $request->database,
                'DB_USERNAME' => $request->username,
                'DB_PASSWORD' => $request->password ?? '',
            ]);

            // Clear ALL caches and reload config
            try {
                // Clear config cache
                Artisan::call('config:clear');
                
                // Clear application cache
                Artisan::call('cache:clear');
                
                // Reload environment
                $app = app();
                $app->loadEnvironmentFrom('.env');
                
                // Update config directly
                config([
                    'database.connections.mysql.host' => $request->host,
                    'database.connections.mysql.port' => $request->port,
                    'database.connections.mysql.database' => $request->database,
                    'database.connections.mysql.username' => $request->username,
                    'database.connections.mysql.password' => $request->password ?? '',
                ]);
                
                // Test the actual connection with new config
                DB::purge('mysql');
                DB::connection('mysql')->getPdo();
                
            } catch (\Exception $e) {
                Log::warning('Config clear warning: ' . $e->getMessage());
                // Continue anyway - config might be cleared
            }

            return response()->json([
                'success' => true,
                'message' => 'Database configuration saved successfully!',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', array_map(function($errors) {
                    return implode(', ', $errors);
                }, $e->errors())),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Setup save error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Complete setup
     */
    public function complete(Request $request)
    {
        try {
            // Reload environment and config before running migrations
            $app = app();
            $app->loadEnvironmentFrom('.env');
            
            // Clear all caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            
            // Purge database connection to force reload
            DB::purge('mysql');
            
            // Test connection before migrations
            try {
                DB::connection('mysql')->getPdo();
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database connection failed: ' . $e->getMessage() . '. Please check your .env file.',
                ], 400);
            }
            
            // Run migrations
            Artisan::call('migrate', ['--force' => true]);

            // Run seeders
            Artisan::call('db:seed', [
                '--class' => 'AdminSeeder',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'AccountNumberSeeder',
                '--force' => true,
            ]);

            // Mark as configured
            $this->updateEnvFile([
                'APP_SETUP_COMPLETE' => 'true',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setup completed successfully!',
                'redirect' => '/admin',
            ]);
        } catch (\Exception $e) {
            Log::error('Setup complete error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Setup failed: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 400);
        }
    }

    /**
     * Update .env file
     */
    protected function updateEnvFile(array $data)
    {
        $envFile = base_path('.env');

        if (!File::exists($envFile)) {
            File::copy(base_path('.env.example'), $envFile);
        }

        $envContent = File::get($envFile);

        foreach ($data as $key => $value) {
            // For .env files, don't quote values unless they contain spaces
            // Laravel's env() function handles special characters without quotes
            $escapedValue = $value;
            
            // Only quote if value contains spaces and isn't already quoted
            if (preg_match('/\s/', $value) && !preg_match('/^["\'].*["\']$/', $value)) {
                // Escape quotes and wrap in double quotes
                $escapedValue = '"' . str_replace('"', '\\"', $value) . '"';
            }
            
            // Match the key with optional spaces around =
            $pattern = '/^' . preg_quote($key, '/') . '\s*=\s*.*/m';
            
            if (preg_match($pattern, $envContent)) {
                // Replace existing value
                $envContent = preg_replace($pattern, $key . '=' . $escapedValue, $envContent);
            } else {
                // Append new key-value pair
                $envContent .= "\n" . $key . '=' . $escapedValue;
            }
        }

        File::put($envFile, $envContent);
    }

    /**
     * Check if setup is complete
     */
    protected function isConfigured()
    {
        return env('APP_SETUP_COMPLETE', false) === 'true';
    }
}
