<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        $request->validate([
            'host' => 'required|string',
            'port' => 'required|numeric',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        try {
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
        $request->validate([
            'host' => 'required|string',
            'port' => 'required|numeric',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        try {
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
                'DB_PORT' => $request->port,
                'DB_DATABASE' => $request->database,
                'DB_USERNAME' => $request->username,
                'DB_PASSWORD' => $request->password ?? '',
            ]);

            // Clear config cache
            Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Database configuration saved successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Complete setup
     */
    public function complete(Request $request)
    {
        try {
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
            return response()->json([
                'success' => false,
                'message' => 'Setup failed: ' . $e->getMessage(),
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
            $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $key . '=' . $value, $envContent);
            } else {
                $envContent .= "\n" . $key . '=' . $value;
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
