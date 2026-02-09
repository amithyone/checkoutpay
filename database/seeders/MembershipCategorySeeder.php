<?php

namespace Database\Seeders;

use App\Models\MembershipCategory;
use Illuminate\Database\Seeder;

class MembershipCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Gym',
                'slug' => 'gym',
                'description' => 'Fitness centers, gyms, and workout facilities',
                'icon' => 'dumbbell',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Clubs',
                'slug' => 'clubs',
                'description' => 'Social clubs, sports clubs, and recreational organizations',
                'icon' => 'users',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Church',
                'slug' => 'church',
                'description' => 'Religious organizations and places of worship',
                'icon' => 'church',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Yoga & Pilates',
                'slug' => 'yoga-pilates',
                'description' => 'Yoga studios, Pilates classes, and mindfulness centers',
                'icon' => 'spa',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Swimming',
                'slug' => 'swimming',
                'description' => 'Swimming pools, aquatic centers, and swim clubs',
                'icon' => 'swimmer',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Martial Arts',
                'slug' => 'martial-arts',
                'description' => 'Karate, Taekwondo, Jiu-Jitsu, and other martial arts schools',
                'icon' => 'fist-raised',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Dance',
                'slug' => 'dance',
                'description' => 'Dance studios, ballet schools, and dance academies',
                'icon' => 'music',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Library',
                'slug' => 'library',
                'description' => 'Public libraries, private libraries, and reading clubs',
                'icon' => 'book',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Museum',
                'slug' => 'museum',
                'description' => 'Museums, galleries, and cultural centers',
                'icon' => 'landmark',
                'is_active' => true,
                'sort_order' => 9,
            ],
            [
                'name' => 'Golf Club',
                'slug' => 'golf-club',
                'description' => 'Golf clubs, country clubs, and golf courses',
                'icon' => 'golf-ball',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Tennis Club',
                'slug' => 'tennis-club',
                'description' => 'Tennis clubs, racquet sports, and court facilities',
                'icon' => 'table-tennis',
                'is_active' => true,
                'sort_order' => 11,
            ],
            [
                'name' => 'Co-working Space',
                'slug' => 'coworking-space',
                'description' => 'Co-working spaces, business centers, and shared offices',
                'icon' => 'briefcase',
                'is_active' => true,
                'sort_order' => 12,
            ],
            [
                'name' => 'Spa & Wellness',
                'slug' => 'spa-wellness',
                'description' => 'Spas, wellness centers, and relaxation facilities',
                'icon' => 'spa',
                'is_active' => true,
                'sort_order' => 13,
            ],
            [
                'name' => 'Music School',
                'slug' => 'music-school',
                'description' => 'Music schools, conservatories, and music academies',
                'icon' => 'guitar',
                'is_active' => true,
                'sort_order' => 14,
            ],
            [
                'name' => 'Art Studio',
                'slug' => 'art-studio',
                'description' => 'Art studios, painting classes, and creative workshops',
                'icon' => 'palette',
                'is_active' => true,
                'sort_order' => 15,
            ],
        ];

        foreach ($categories as $category) {
            MembershipCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
