<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class SetupRoutesBlockedTest extends TestCase
{
    public function test_setup_routes_return_404_when_complete(): void
    {
        config(['checkout.setup_complete' => true]);

        $this->get('/setup')->assertNotFound();
        $this->post('/setup/save-database', [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'x',
            'username' => 'x',
            'password' => 'x',
        ])->assertNotFound();
        $this->post('/setup/test-database', [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'x',
            'username' => 'x',
        ])->assertNotFound();
        $this->post('/setup/complete')->assertNotFound();
    }
}
