<?php

namespace Tests\Unit\Vtu;

use App\Services\Vtu\VtuCatalogNormalizer;
use Tests\TestCase;

class VtuCatalogNormalizerTest extends TestCase
{
    public function test_normalizes_electricity_providers(): void
    {
        $payload = [
            [
                'code' => 'NG',
                'name' => 'Nigeria',
                'providers' => [
                    [
                        'code' => 'abuja-electric',
                        'name' => 'Abuja (AEDC)',
                        'plans' => [['code' => 'prepaid', 'name' => 'prepaid']],
                    ],
                ],
            ],
        ];

        $normalizer = new VtuCatalogNormalizer;
        $discos = $normalizer->electricityDiscos($payload);

        $this->assertCount(1, $discos);
        $this->assertSame('abuja-electric', $discos[0]['id']);
        $this->assertSame('Abuja (AEDC)', $discos[0]['label']);
    }

    public function test_normalizes_cable_plans_with_amount(): void
    {
        $payload = [
            [
                'providers' => [
                    [
                        'code' => 'gotv_vtu_v2',
                        'serviceId' => 'gotv',
                        'providerPlans' => [
                            ['code' => 'cwgotvsmallie', 'name' => 'Smallie (NGN 1900.00)', 'amount' => 1900],
                        ],
                    ],
                ],
            ],
        ];

        $normalizer = new VtuCatalogNormalizer;
        $plans = $normalizer->tvPlansForProvider($payload, 'gotv');

        $this->assertCount(1, $plans);
        $this->assertSame('cwgotvsmallie', $plans[0]['variation_id']);
        $this->assertSame(1900.0, $plans[0]['price']);
    }
}
