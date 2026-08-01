<?php

namespace App\Services\Admin;

final class AdminPagePermissionCatalog
{
    /**
     * @return list<array{key: string, label: string, group: string}>
     */
    public function forStaffForm(): array
    {
        $items = [];
        foreach (config('admin_pages.pages', []) as $key => $def) {
            if (! is_array($def)) {
                continue;
            }
            $items[] = [
                'key' => $key,
                'label' => (string) ($def['label'] ?? $key),
                'group' => (string) ($def['group'] ?? 'General'),
            ];
        }

        usort($items, fn (array $a, array $b) => [$a['group'], $a['label']] <=> [$b['group'], $b['label']]);

        return $items;
    }
}
