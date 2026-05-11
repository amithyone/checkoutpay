<?php

namespace Database\Seeders;

use App\Models\RentalItem;
use Illuminate\Database\Seeder;

/**
 * Fills rental_items.brand from the start of name (common AV / gear / auto brands).
 * Only updates rows where brand is empty — safe to re-run; will not overwrite manual brands.
 */
class RentalItemBrandFromNameSeeder extends Seeder
{
    /**
     * Prefix (start of name) => canonical brand label stored in DB.
     * Longer prefixes must sort first (handled in run()).
     *
     * @var array<string, string>
     */
    private const PREFIX_TO_BRAND = [
        'Audio-Technica' => 'Audio-Technica',
        'Blackmagic' => 'Blackmagic',
        'Mercedes-Benz' => 'Mercedes-Benz',
        'Mercedes' => 'Mercedes-Benz',
        'Fujifilm' => 'Fujifilm',
        'Panasonic' => 'Panasonic',
        'Olympus' => 'Olympus',
        'Tamron' => 'Tamron',
        'Tokina' => 'Tokina',
        'Sigma' => 'Sigma',
        'Sennheiser' => 'Sennheiser',
        'Microsoft' => 'Microsoft',
        'Surface' => 'Microsoft',
        'MacBook' => 'Apple',
        'iPhone' => 'Apple',
        'iPad' => 'Apple',
        'iMac' => 'Apple',
        'Apple' => 'Apple',
        'Samsung' => 'Samsung',
        'Galaxy' => 'Samsung',
        'Lenovo' => 'Lenovo',
        'Volkswagen' => 'Volkswagen',
        'GoPro' => 'GoPro',
        'Nikon' => 'Nikon',
        'Canon' => 'Canon',
        'Sony' => 'Sony',
        'DJI' => 'DJI',
        'Godox' => 'Godox',
        'Aputure' => 'Aputure',
        'Nanlite' => 'Nanlite',
        'Shure' => 'Shure',
        'Rode' => 'Rode',
        'JBL' => 'JBL',
        'Bose' => 'Bose',
        'Yamaha' => 'Yamaha',
        'Pioneer' => 'Pioneer',
        'Denon' => 'Denon',
        'Dell' => 'Dell',
        'Asus' => 'Asus',
        'Bosch' => 'Bosch',
        'Makita' => 'Makita',
        'DeWalt' => 'DeWalt',
        'Milwaukee' => 'Milwaukee',
        'Ryobi' => 'Ryobi',
        'Toyota' => 'Toyota',
        'Honda' => 'Honda',
        'Hyundai' => 'Hyundai',
        'Kia' => 'Kia',
        'Ford' => 'Ford',
        'BMW' => 'BMW',
        'Audi' => 'Audi',
        'Volvo' => 'Volvo',
        'Peugeot' => 'Peugeot',
        'Nissan' => 'Nissan',
        'LG' => 'LG',
        'HP' => 'HP',
    ];

    public function run(): void
    {
        $rules = self::PREFIX_TO_BRAND;
        uksort($rules, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $updated = 0;
        $skipped = 0;

        RentalItem::query()
            ->orderBy('id')
            ->chunkById(200, function ($items) use ($rules, &$updated, &$skipped): void {
                foreach ($items as $item) {
                    $existing = trim((string) ($item->brand ?? ''));
                    if ($existing !== '') {
                        $skipped++;

                        continue;
                    }

                    $brand = $this->inferBrand((string) $item->name, $rules);
                    if ($brand === null) {
                        continue;
                    }

                    $item->forceFill(['brand' => $brand])->saveQuietly();
                    $updated++;
                }
            });

        $this->command->info("RentalItemBrandFromNameSeeder: set brand on {$updated} item(s); skipped {$skipped} (already had brand).");
    }

    /**
     * @param  array<string, string>  $rules  longest prefix first
     */
    private function inferBrand(string $name, array $rules): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        foreach ($rules as $prefix => $brand) {
            $q = preg_quote($prefix, '/');
            if (preg_match("/^{$q}($|\s|-)/ui", $name)) {
                return $brand;
            }
        }

        return null;
    }
}
