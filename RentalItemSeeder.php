<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RentalItem;
use App\Models\RentalCategory;
use App\Models\Business;
use Illuminate\Support\Str;

class RentalItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing businesses and categories
        $businesses = Business::where('is_active', true)->get();
        $categories = RentalCategory::where('is_active', true)->get();
        $cities = config('cities.major_cities', []);

        if ($businesses->isEmpty()) {
            $this->command->warn('No active businesses found. Please create a business first.');
            return;
        }

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Please run RentalCategorySeeder first.');
            return;
        }

        // Demo rental items
        $items = [
            [
                'name' => 'Canon EOS R5 Professional Camera',
                'category' => 'Camera',
                'description' => 'Professional mirrorless camera with 45MP full-frame sensor, 8K video recording, and advanced autofocus system. Perfect for professional photography and videography.',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'address' => 'Victoria Island, Lagos',
                'daily_rate' => 25000,
                'weekly_rate' => 150000,
                'monthly_rate' => 500000,
                'quantity_available' => 3,
                'specifications' => [
                    'sensor' => '45MP Full-Frame CMOS',
                    'video' => '8K RAW Video',
                    'iso_range' => '100-51200',
                    'autofocus' => 'Dual Pixel AF',
                    'battery_life' => '320 shots',
                ],
                'is_featured' => true,
            ],
            [
                'name' => 'Sony A7III Mirrorless Camera',
                'category' => 'Camera',
                'description' => 'Full-frame mirrorless camera with 24MP sensor, 4K video, and excellent low-light performance. Great for both photography and video.',
                'city' => 'Abuja',
                'state' => 'FCT',
                'address' => 'Wuse 2, Abuja',
                'daily_rate' => 18000,
                'weekly_rate' => 100000,
                'monthly_rate' => 350000,
                'quantity_available' => 5,
                'specifications' => [
                    'sensor' => '24MP Full-Frame',
                    'video' => '4K UHD',
                    'iso_range' => '100-51200',
                    'autofocus' => '693 Phase Detection Points',
                    'battery_life' => '610 shots',
                ],
                'is_featured' => true,
            ],
            [
                'name' => 'Godox SL-60W LED Video Light',
                'category' => 'Lighting',
                'description' => 'Professional LED video light with 60W output, adjustable color temperature (3200K-5600K), and dimmer control. Perfect for studio and on-location shoots.',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'address' => 'Ikeja, Lagos',
                'daily_rate' => 5000,
                'weekly_rate' => 28000,
                'monthly_rate' => 100000,
                'quantity_available' => 8,
                'specifications' => [
                    'power' => '60W',
                    'color_temp' => '3200K-5600K',
                    'cri' => '95+',
                    'dimming' => '0-100%',
                    'power_supply' => 'AC/DC',
                ],
            ],
            [
                'name' => '2-Bedroom Luxury Apartment',
                'category' => 'Apartments',
                'description' => 'Fully furnished 2-bedroom apartment in prime location. Includes WiFi, air conditioning, fully equipped kitchen, and modern amenities. Perfect for short-term stays.',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'address' => 'Lekki Phase 1, Lagos',
                'daily_rate' => 15000,
                'weekly_rate' => 90000,
                'monthly_rate' => 300000,
                'quantity_available' => 2,
                'specifications' => [
                    'bedrooms' => '2',
                    'bathrooms' => '2',
                    'furnished' => 'Yes',
                    'wifi' => 'Included',
                    'parking' => 'Available',
                ],
                'is_featured' => true,
            ],
            [
                'name' => 'Toyota Camry 2022',
                'category' => 'Cars',
                'description' => 'Reliable and comfortable sedan perfect for city driving and long trips. Includes insurance, fuel, and 24/7 roadside assistance.',
                'city' => 'Abuja',
                'state' => 'FCT',
                'address' => 'Maitama, Abuja',
                'daily_rate' => 12000,
                'weekly_rate' => 70000,
                'monthly_rate' => 250000,
                'quantity_available' => 4,
                'specifications' => [
                    'seats' => '5',
                    'transmission' => 'Automatic',
                    'fuel' => 'Petrol',
                    'insurance' => 'Included',
                    'mileage' => 'Unlimited',
                ],
            ],
            [
                'name' => 'Shure SM58 Dynamic Microphone',
                'category' => 'Audio Equipment',
                'description' => 'Industry-standard vocal microphone with excellent sound quality and durability. Perfect for live performances, recording, and events.',
                'city' => 'Port Harcourt',
                'state' => 'Rivers',
                'address' => 'GRA Phase 2, Port Harcourt',
                'daily_rate' => 3000,
                'weekly_rate' => 15000,
                'monthly_rate' => 50000,
                'quantity_available' => 10,
                'specifications' => [
                    'type' => 'Dynamic',
                    'polar_pattern' => 'Cardioid',
                    'frequency_response' => '50Hz-15kHz',
                    'connector' => 'XLR',
                    'accessories' => 'Stand adapter included',
                ],
            ],
            [
                'name' => 'DJI Mavic Air 2 Drone',
                'category' => 'Video Equipment',
                'description' => 'Professional drone with 4K video, 48MP photos, and intelligent flight modes. Perfect for aerial photography and videography.',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'address' => 'Ikoyi, Lagos',
                'daily_rate' => 20000,
                'weekly_rate' => 110000,
                'monthly_rate' => 400000,
                'quantity_available' => 2,
                'specifications' => [
                    'camera' => '48MP / 4K Video',
                    'flight_time' => '34 minutes',
                    'range' => '10km',
                    'obstacle_sensing' => 'Yes',
                    'batteries' => '2 included',
                ],
                'is_featured' => true,
            ],
            [
                'name' => 'Event Tent 10x10m',
                'category' => 'Event Equipment',
                'description' => 'Large event tent perfect for outdoor parties, weddings, and corporate events. Includes sidewalls and setup service.',
                'city' => 'Ibadan',
                'state' => 'Oyo',
                'address' => 'Bodija, Ibadan',
                'daily_rate' => 8000,
                'weekly_rate' => 45000,
                'monthly_rate' => 150000,
                'quantity_available' => 6,
                'specifications' => [
                    'size' => '10x10 meters',
                    'capacity' => '80-100 people',
                    'sidewalls' => 'Included',
                    'setup' => 'Included',
                    'material' => 'Waterproof',
                ],
            ],
            [
                'name' => 'MacBook Pro 16-inch M1',
                'category' => 'Electronics',
                'description' => 'Powerful laptop perfect for video editing, graphic design, and professional work. Includes charger and carrying case.',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'address' => 'Yaba, Lagos',
                'daily_rate' => 10000,
                'weekly_rate' => 55000,
                'monthly_rate' => 200000,
                'quantity_available' => 3,
                'specifications' => [
                    'processor' => 'Apple M1',
                    'ram' => '16GB',
                    'storage' => '512GB SSD',
                    'display' => '16-inch Retina',
                    'battery' => 'Up to 21 hours',
                ],
            ],
            [
                'name' => '3-Seater Leather Sofa Set',
                'category' => 'Furniture',
                'description' => 'Elegant leather sofa set perfect for events, offices, or temporary home furnishing. Includes delivery and setup.',
                'city' => 'Kano',
                'state' => 'Kano',
                'address' => 'Nassarawa GRA, Kano',
                'daily_rate' => 6000,
                'weekly_rate' => 32000,
                'monthly_rate' => 120000,
                'quantity_available' => 4,
                'specifications' => [
                    'pieces' => '3-seater + 2 armchairs',
                    'material' => 'Genuine Leather',
                    'color' => 'Brown',
                    'delivery' => 'Included',
                    'condition' => 'Excellent',
                ],
            ],
            [
                'name' => 'Bosch Professional Drill Set',
                'category' => 'Tools & Equipment',
                'description' => 'Professional power drill set with multiple drill bits and accessories. Perfect for construction and DIY projects.',
                'city' => 'Aba',
                'state' => 'Abia',
                'address' => 'Ariaria, Aba',
                'daily_rate' => 4000,
                'weekly_rate' => 20000,
                'monthly_rate' => 70000,
                'quantity_available' => 7,
                'specifications' => [
                    'power' => '18V',
                    'battery' => '2 batteries included',
                    'bits' => '50-piece set',
                    'warranty' => '1 year',
                    'case' => 'Included',
                ],
            ],
            [
                'name' => 'Canon EF 24-70mm f/2.8L Lens',
                'category' => 'Camera',
                'description' => 'Professional zoom lens with constant f/2.8 aperture. Perfect for portraits, events, and general photography. Sharp and versatile.',
                'city' => 'Enugu',
                'state' => 'Enugu',
                'address' => 'GRA, Enugu',
                'daily_rate' => 12000,
                'weekly_rate' => 65000,
                'monthly_rate' => 220000,
                'quantity_available' => 2,
                'specifications' => [
                    'focal_length' => '24-70mm',
                    'aperture' => 'f/2.8',
                    'mount' => 'Canon EF',
                    'image_stabilization' => 'Yes',
                    'filter_size' => '82mm',
                ],
                'is_featured' => true,
            ],
        ];

        $created = 0;
        foreach ($items as $itemData) {
            // Find category by name
            $category = $categories->firstWhere('name', $itemData['category']);
            if (!$category) {
                continue;
            }

            // Randomly assign to a business
            $business = $businesses->random();

            // Randomly select city if not in predefined list
            $city = $itemData['city'];
            if (!in_array($city, $cities)) {
                $city = $cities[array_rand($cities)] ?? 'Lagos';
            }

            RentalItem::create([
                'business_id' => $business->id,
                'category_id' => $category->id,
                'name' => $itemData['name'],
                'description' => $itemData['description'],
                'city' => $city,
                'state' => $itemData['state'] ?? null,
                'address' => $itemData['address'] ?? null,
                'daily_rate' => $itemData['daily_rate'],
                'weekly_rate' => $itemData['weekly_rate'] ?? null,
                'monthly_rate' => $itemData['monthly_rate'] ?? null,
                'currency' => 'NGN',
                'quantity_available' => $itemData['quantity_available'],
                'is_available' => true,
                'is_active' => true,
                'is_featured' => $itemData['is_featured'] ?? false,
                'specifications' => $itemData['specifications'] ?? null,
                'terms_and_conditions' => 'Items must be returned in the same condition. Security deposit may be required. Late returns subject to additional charges.',
            ]);

            $created++;
        }

        $this->command->info("Successfully seeded {$created} demo rental items!");
    }
}
