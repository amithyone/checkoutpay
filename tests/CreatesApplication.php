<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $app->make('config')->set('database.default', 'sqlite');
        $app->make('config')->set('database.connections.sqlite.database', ':memory:');
        $app->make('db')->purge('sqlite');
        $app->make('db')->setDefaultConnection('sqlite');

        if ($app->make('db')->connection()->getDriverName() === 'mysql') {
            throw new RuntimeException(
                'Tests must not use MySQL (RefreshDatabase would wipe real data). '
                .'Use sqlite :memory: via phpunit.xml / .env.testing.'
            );
        }

        return $app;
    }
}
