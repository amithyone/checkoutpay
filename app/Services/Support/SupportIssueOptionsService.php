<?php

namespace App\Services\Support;

final class SupportIssueOptionsService
{
    public const QUEUE_PAYMENT = 'payment';

    public const QUEUE_WALLET = 'wallet';

    /**
     * @return list<array{key: string, label: string, hint: string, requires_payment: bool, quick: bool, queue: string}>
     */
    public function issueTypes(): array
    {
        $types = config('support.issue_types', []);
        $rows = [];

        foreach ($types as $key => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            $rows[] = [
                'key' => (string) $key,
                'label' => (string) ($meta['label'] ?? $key),
                'hint' => (string) ($meta['hint'] ?? ''),
                'requires_payment' => (bool) ($meta['requires_payment'] ?? false),
                'quick' => (bool) ($meta['quick'] ?? false),
                'queue' => $this->queueFor((string) $key),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function supportCategories(): array
    {
        $rows = config('support.support_categories', []);
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => (string) ($row['label'] ?? $key),
            ];
        }

        return $out;
    }

    public function isValidIssueType(?string $key): bool
    {
        if ($key === null || trim($key) === '') {
            return true;
        }

        return array_key_exists($key, config('support.issue_types', []));
    }

    public function requiresPayment(?string $key): bool
    {
        if ($key === null || trim($key) === '') {
            return false;
        }

        $types = config('support.issue_types', []);

        return (bool) ($types[$key]['requires_payment'] ?? false);
    }

    public function queueFor(?string $key): string
    {
        if ($key === null || trim($key) === '') {
            return self::QUEUE_PAYMENT;
        }

        $types = config('support.issue_types', []);
        $queue = (string) ($types[$key]['queue'] ?? self::QUEUE_PAYMENT);

        return $queue === self::QUEUE_WALLET ? self::QUEUE_WALLET : self::QUEUE_PAYMENT;
    }

    public function isWalletQueue(?string $key): bool
    {
        return $this->queueFor($key) === self::QUEUE_WALLET;
    }

    /**
     * @return list<string>
     */
    public function walletIssueTypeKeys(): array
    {
        $keys = [];
        foreach (config('support.issue_types', []) as $key => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            if ($this->queueFor((string) $key) === self::QUEUE_WALLET) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    /**
     * @return array{label: string, subject_prefix: string, priority: string, queue: string}|null
     */
    public function metaFor(?string $key): ?array
    {
        if ($key === null || trim($key) === '') {
            return null;
        }

        $types = config('support.issue_types', []);
        if (! isset($types[$key]) || ! is_array($types[$key])) {
            return null;
        }

        $row = $types[$key];

        return [
            'label' => (string) ($row['label'] ?? $key),
            'subject_prefix' => (string) ($row['subject_prefix'] ?? 'Support'),
            'priority' => (string) ($row['priority'] ?? 'medium'),
            'queue' => $this->queueFor($key),
        ];
    }
}
