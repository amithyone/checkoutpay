<?php

namespace Tests\Unit\Broadcast;

use App\Services\Broadcast\BroadcastWireExpand;
use Tests\TestCase;

class BroadcastWireExpandTest extends TestCase
{
    public function test_expands_compact_presence_wire_inside_envelope(): void
    {
        $expand = new BroadcastWireExpand;
        $result = $expand->normalizeForVerify([
            'p' => [
                'v' => 2.1,
                'sid' => '550e8400-e29b-41d4-a716-446655440000',
                'tid' => 'CP-1RK8Z',
                'ts' => 1738123456789,
                'msk' => '***4863',
            ],
            'alg' => 'ed25519',
            'sig' => 'abc',
        ]);

        $this->assertSame('ed25519', $result['signature_alg']);
        $this->assertSame('abc', $result['signature']);
        $this->assertSame(2.1, $result['payload']['protocol_version']);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $result['payload']['session_uuid_v4']);
        $this->assertSame('CP-1RK8Z', $result['payload']['terminal_id']);
        $this->assertSame(0, $result['payload']['transaction_details']['total_amount_ngn']);
        $this->assertSame('***4863', $result['payload']['account_info_public_display']['masked_account_suffix']);
    }
}
