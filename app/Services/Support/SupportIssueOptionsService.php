<?php

namespace App\Services\Support;

final class SupportIssueOptionsService
{
    /**
     * @return list<array{key: string, label: string, hint: string, requires_payment: bool, quick: bool}>
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
            ];
        }

        return $rows;
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

    /**
     * @return array{label: string, subject_prefix: string, priority: string}|null
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
        ];
    }
}
