<?php

namespace App\Services\Whatsapp;

/**
 * Request-scoped buffer: while active, {@see EvolutionWhatsAppClient} records outbound text
 * instead of calling Evolution (consumer app in-app "WhatsApp parity" chat).
 */
final class WalletConversationCapture
{
    /** @var list<string>|null */
    private static ?array $buffer = null;

    public static function start(): void
    {
        self::$buffer = [];
    }

    public static function isActive(): bool
    {
        return self::$buffer !== null;
    }

    public static function append(string $text): void
    {
        if (self::$buffer === null) {
            return;
        }
        $t = trim($text);
        if ($t === '') {
            return;
        }
        self::$buffer[] = $t;
    }

    /**
     * @return list<string>
     */
    public static function drainAndStop(): array
    {
        $out = self::$buffer ?? [];
        self::$buffer = null;

        return $out;
    }
}
