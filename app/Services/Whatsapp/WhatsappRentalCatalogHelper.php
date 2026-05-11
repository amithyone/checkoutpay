<?php

namespace App\Services\Whatsapp;

use App\Models\RentalItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shared category → optional brand → paginated item lists for WhatsApp rentals browse.
 */
final class WhatsappRentalCatalogHelper
{
    public const ITEMS_PER_PAGE = 8;

    public const BRAND_KEY_ALL = '__all__';

    public const BRAND_KEY_OTHER = '__other__';

    public static function baseItemQuery(int $categoryId): Builder
    {
        return RentalItem::query()
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('is_available', true);
    }

    /**
     * @return list<string>
     */
    public static function distinctBrandsInCategory(int $categoryId): array
    {
        $seen = [];
        foreach (self::baseItemQuery($categoryId)->orderBy('brand')->get(['brand']) as $row) {
            $b = trim((string) ($row->brand ?? ''));
            if ($b === '') {
                continue;
            }
            $seen[$b] = true;
        }

        $list = array_keys($seen);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    public static function hasUnbrandedInCategory(int $categoryId): bool
    {
        return self::baseItemQuery($categoryId)
            ->where(function ($q) {
                $q->whereNull('brand')
                    ->orWhere('brand', '')
                    ->orWhereRaw("LENGTH(TRIM(COALESCE(brand, ''))) = 0");
            })
            ->exists();
    }

    public static function shouldOfferBrandPicker(int $categoryId): bool
    {
        $brands = self::distinctBrandsInCategory($categoryId);
        $n = count($brands);
        if ($n >= 2) {
            return true;
        }

        return $n === 1 && self::hasUnbrandedInCategory($categoryId);
    }

    /**
     * @return list{array{key: string, label: string}}
     */
    public static function brandMenuRows(int $categoryId): array
    {
        $rows = [];
        foreach (self::distinctBrandsInCategory($categoryId) as $b) {
            $rows[] = ['key' => $b, 'label' => $b];
        }
        if (self::hasUnbrandedInCategory($categoryId)) {
            $rows[] = ['key' => self::BRAND_KEY_OTHER, 'label' => 'Other (no brand set)'];
        }
        $rows[] = ['key' => self::BRAND_KEY_ALL, 'label' => 'All items in category'];

        return $rows;
    }

    public static function applyBrandKeyFilter(Builder $q, string $brandKey): Builder
    {
        if ($brandKey === self::BRAND_KEY_ALL) {
            return $q;
        }
        if ($brandKey === self::BRAND_KEY_OTHER) {
            return $q->where(function ($sub) {
                $sub->whereNull('brand')
                    ->orWhere('brand', '')
                    ->orWhereRaw("LENGTH(TRIM(COALESCE(brand, ''))) = 0");
            });
        }

        return $q->whereRaw('TRIM(brand) = ?', [trim($brandKey)]);
    }

    /**
     * @return array{items: Collection<int, RentalItem>, total: int, page: int, total_pages: int, per_page: int}
     */
    public static function paginatedItems(int $categoryId, string $brandKey, int $page): array
    {
        $perPage = self::ITEMS_PER_PAGE;
        $base = self::baseItemQuery($categoryId);
        self::applyBrandKeyFilter($base, $brandKey);
        $total = (clone $base)->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(0, min($page, $totalPages - 1));
        $items = (clone $base)
            ->orderBy('name')
            ->offset($page * $perPage)
            ->limit($perPage)
            ->get(['id', 'name', 'daily_rate', 'currency', 'brand']);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
        ];
    }
}
