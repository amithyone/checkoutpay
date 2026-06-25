<?php

namespace Tests\Unit;

use App\Services\MevonPay\MevonPayTransportErrorClassifier;
use Illuminate\Http\Client\ConnectionException;
use Tests\TestCase;

class MevonPayTransportErrorClassifierTest extends TestCase
{
    public function test_connection_exception_is_ambiguous(): void
    {
        $this->assertTrue(
            MevonPayTransportErrorClassifier::isAmbiguousTransportFailure(new ConnectionException('Connection refused'))
        );
    }

    /**
     * @dataProvider ambiguousMessagesProvider
     */
    public function test_ambiguous_messages(string $message): void
    {
        $this->assertTrue(MevonPayTransportErrorClassifier::isAmbiguousTransportFailure(null, $message));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function ambiguousMessagesProvider(): array
    {
        return [
            ['cURL error 28: Operation timed out after 20000 milliseconds'],
            ['Maximum execution time of 30 seconds exceeded'],
            ['time limit exceeded'],
            ['Could not resolve host: mevonpay.com.ng'],
        ];
    }

    public function test_confirmed_provider_error_is_not_ambiguous(): void
    {
        $this->assertFalse(
            MevonPayTransportErrorClassifier::isAmbiguousTransportFailure(null, 'Insufficient balance at source account')
        );
    }
}
