<?php

namespace Tests\Unit\Whatsapp;

use App\Services\Whatsapp\WhatsappBankTransferReceiptDetails;
use PHPUnit\Framework\TestCase;

class WhatsappBankTransferReceiptDetailsTest extends TestCase
{
    public function test_resolve_session_id_prefers_result_then_raw_then_sent(): void
    {
        $this->assertSame(
            'from-result',
            WhatsappBankTransferReceiptDetails::resolveSessionId(['session_id' => 'from-result'], 'sent')
        );
        $this->assertSame(
            'from-raw',
            WhatsappBankTransferReceiptDetails::resolveSessionId(['raw' => ['sessionId' => 'from-raw']], 'sent')
        );
        $this->assertSame(
            'sent',
            WhatsappBankTransferReceiptDetails::resolveSessionId([], 'sent')
        );
    }

    public function test_merge_into_meta_stores_session_and_message(): void
    {
        $meta = WhatsappBankTransferReceiptDetails::mergeIntoMeta([], [
            'session_id' => 'WAW123',
            'response_message' => 'Transfer successful',
        ]);

        $this->assertSame('WAW123', $meta['payout_session_id']);
        $this->assertSame('Transfer successful', $meta['payout_response_message']);
    }

    public function test_whatsapp_block_and_plain_lines_always_include_labels(): void
    {
        $block = WhatsappBankTransferReceiptDetails::whatsappBlock(null, null);
        $this->assertStringContainsString('*Session ID:*', $block);
        $this->assertStringContainsString('*Status:*', $block);

        $lines = WhatsappBankTransferReceiptDetails::plainLines('abc', 'ok');
        $this->assertSame('Session ID: abc', $lines[0]);
        $this->assertSame('Status: ok', $lines[1]);
    }

    public function test_web_receipt_rows_use_display_placeholders(): void
    {
        $rows = WhatsappBankTransferReceiptDetails::webReceiptRows([
            'session_id' => '',
            'response_message' => 'Pending',
            'reference' => 'REF-1',
        ]);

        $this->assertSame('—', $rows['Session ID']);
        $this->assertSame('Pending', $rows['Status']);
        $this->assertSame('REF-1', $rows['Reference']);
    }
}
