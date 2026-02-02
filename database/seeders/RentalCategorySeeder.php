<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RentalCategory;
use Illuminate\Support\Str;

class RentalCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Camera',
                'description' => 'Professional cameras, DSLRs, mirrorless cameras, and camera equipment for photography and videography.',
                'icon' => 'fas fa-camera',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Lighting',
                'description' => 'Studio lighting equipment, LED panels, softboxes, and lighting accessories for professional photography and videography.',
                'icon' => 'fas fa-lightbulb',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Apartments',
                'description' => 'Furnished apartments, studios, and residential spaces available for short-term and long-term rentals.',
                'icon' => 'fas fa-building',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Cars',
                'description' => 'Vehicle rentals including sedans, SUVs, luxury cars, and commercial vehicles for daily, weekly, or monthly use.',
                'icon' => 'fas fa-car',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Audio Equipment',
                'description' => 'Microphones, speakers, sound systems, and audio recording equipment for events and productions.',
                'icon' => 'fas fa-microphone',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Video Equipment',
                'description' => 'Video cameras, gimbals, drones, and video production equipment for filmmaking and content creation.',
                'icon' => 'fas fa-video',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Event Equipment',
                'description' => 'Tents, tables, chairs, stages, and event setup equipment for parties, weddings, and corporate events.',
                'icon' => 'fas fa-calendar-alt',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Furniture',
                'description' => 'Office furniture, home furniture, and decorative items for temporary or event use.',
                'icon' => 'fas fa-couch',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Electronics',
                'description' => 'Laptops, tablets, projectors, screens, and other electronic devices for rent.',
                'icon' => 'fas fa-laptop',
                'is_active' => true,
                'sort_order' => 9,
            ],
            [
                'name' => 'Tools & Equipment',
                'description' => 'Power tools, construction equipment, and machinery for DIY projects and professional work.',
                'icon' => 'fas fa-tools',
                'is_active' => true,
                'sort_order' => 10,
            ],
        ];

        foreach ($categories as $category) {
            RentalCategory::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                $category
            );
        }

        $this->command->info('Rental categories seeded successfully!');
    }
}
