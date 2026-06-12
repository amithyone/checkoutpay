<?php

namespace Tests\Unit\Support;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicAppUrlTest extends TestCase
{
    public function test_localhost_app_url_uses_request_host_for_generated_links(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->get('https://check-outpay.com/products');

        $this->assertSame(
            'https://check-outpay.com/products',
            URL::route('products.index')
        );
    }
}
