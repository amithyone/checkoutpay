<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\TicketType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a business for events
        $business = Business::first();
        
        if (!$business) {
            $this->command->warn('No business found. Please create a business first.');
            return;
        }

        // Sample events with rich data
        $events = [
            [
                'title' => 'Neon Nights Festival',
                'description' => 'The ultimate electronic music experience featuring world-class DJs and stunning visual productions. Join us for an unforgettable night of music, lights, and energy.',
                'venue' => 'Madison Square Garden',
                'event_type' => 'offline',
                'address' => '4 Pennsylvania Plaza, New York, NY 10001',
                'start_date' => now()->addMonths(2)->setTime(19, 0),
                'end_date' => now()->addMonths(2)->setTime(23, 0),
                'timezone' => 'America/New_York',
                'max_attendees' => 20000,
                'max_tickets_per_customer' => 10,
                'allow_refunds' => true,
                'refund_policy' => 'Full refund available up to 7 days before the event.',
                'commission_percentage' => 5,
                'status' => 'published',
                'ticket_types' => [
                    [
                        'name' => 'General Admission',
                        'description' => 'Standard entry to the event',
                        'price' => 7900, // ₦79.00
                        'quantity_available' => 15000,
                        'features' => [
                            'Entry to main stage',
                            'Access to food vendors',
                            'Festival wristband',
                            'General parking access'
                        ],
                        'is_popular' => false,
                    ],
                    [
                        'name' => 'VIP Experience',
                        'description' => 'Premium experience with exclusive perks',
                        'price' => 14900, // ₦149.00
                        'quantity_available' => 3000,
                        'features' => [
                            'Priority entry & fast lane',
                            'VIP viewing area',
                            'Complimentary drink',
                            'Exclusive merchandise',
                            'VIP parking'
                        ],
                        'is_popular' => true,
                    ],
                    [
                        'name' => 'Backstage Pass',
                        'description' => 'Ultimate access for true fans',
                        'price' => 29900, // ₦299.00
                        'quantity_available' => 500,
                        'features' => [
                            'All VIP benefits',
                            'Backstage tour',
                            'Meet & greet opportunity',
                            'Signed poster',
                            'After-party access'
                        ],
                        'is_popular' => false,
                    ],
                ],
                'speakers' => [
                    [
                        'name' => 'DJ Neon',
                        'topic' => 'Electronic Dance Music',
                        'bio' => 'Award-winning DJ with over 10 years of experience in electronic music.',
                    ],
                    [
                        'name' => 'Sarah Lights',
                        'topic' => 'Visual Production',
                        'bio' => 'Renowned visual artist specializing in immersive light shows.',
                    ],
                    [
                        'name' => 'Mike Bass',
                        'topic' => 'Bass Music',
                        'bio' => 'International DJ known for powerful bass drops and energetic sets.',
                    ],
                ],
            ],
            [
                'title' => 'Tech Innovation Summit 2026',
                'description' => 'Join industry leaders and innovators for a day of cutting-edge technology discussions, networking, and breakthrough presentations.',
                'venue' => 'Convention Center',
                'event_type' => 'offline',
                'address' => '123 Innovation Drive, San Francisco, CA 94105',
                'start_date' => now()->addMonths(3)->setTime(9, 0),
                'end_date' => now()->addMonths(3)->setTime(17, 0),
                'timezone' => 'America/Los_Angeles',
                'max_attendees' => 5000,
                'max_tickets_per_customer' => 5,
                'allow_refunds' => true,
                'refund_policy' => 'Full refund available up to 14 days before the event.',
                'commission_percentage' => 3,
                'status' => 'published',
                'ticket_types' => [
                    [
                        'name' => 'Early Bird',
                        'description' => 'Limited time offer',
                        'price' => 15000, // ₦150.00
                        'quantity_available' => 1000,
                        'features' => [
                            'Access to all sessions',
                            'Conference materials',
                            'Networking lunch',
                            'Digital certificate'
                        ],
                        'is_popular' => true,
                    ],
                    [
                        'name' => 'Standard',
                        'description' => 'Full conference access',
                        'price' => 20000, // ₦200.00
                        'quantity_available' => 3000,
                        'features' => [
                            'Access to all sessions',
                            'Conference materials',
                            'Networking lunch',
                            'Digital certificate',
                            'Access to workshops'
                        ],
                        'is_popular' => false,
                    ],
                    [
                        'name' => 'Premium',
                        'description' => 'VIP experience with exclusive access',
                        'price' => 35000, // ₦350.00
                        'quantity_available' => 1000,
                        'features' => [
                            'All standard benefits',
                            'VIP networking dinner',
                            'One-on-one sessions',
                            'Premium swag bag',
                            'Priority seating'
                        ],
                        'is_popular' => false,
                    ],
                ],
                'speakers' => [
                    [
                        'name' => 'Dr. Jane Smith',
                        'topic' => 'AI & Machine Learning',
                        'bio' => 'Leading researcher in artificial intelligence with 15+ years of experience.',
                    ],
                    [
                        'name' => 'John Tech',
                        'topic' => 'Blockchain Technology',
                        'bio' => 'Blockchain expert and founder of multiple tech startups.',
                    ],
                ],
            ],
            [
                'title' => 'Online Web Development Masterclass',
                'description' => 'Learn modern web development techniques from industry experts in this comprehensive online course.',
                'venue' => 'Online',
                'event_type' => 'online',
                'address' => 'https://zoom.us/j/webdev-masterclass',
                'start_date' => now()->addWeeks(2)->setTime(10, 0),
                'end_date' => now()->addWeeks(2)->setTime(16, 0),
                'timezone' => 'Africa/Lagos',
                'max_attendees' => 1000,
                'max_tickets_per_customer' => 3,
                'allow_refunds' => true,
                'refund_policy' => 'Full refund available up to 24 hours before the event.',
                'commission_percentage' => 2,
                'status' => 'published',
                'ticket_types' => [
                    [
                        'name' => 'Basic Access',
                        'description' => 'Live session access',
                        'price' => 5000, // ₦50.00
                        'quantity_available' => 500,
                        'features' => [
                            'Live session access',
                            'Q&A participation',
                            'Digital materials'
                        ],
                        'is_popular' => false,
                    ],
                    [
                        'name' => 'Premium Access',
                        'description' => 'Full course with recordings',
                        'price' => 10000, // ₦100.00
                        'quantity_available' => 400,
                        'features' => [
                            'Live session access',
                            'Recorded sessions',
                            'Q&A participation',
                            'Digital materials',
                            'Certificate of completion'
                        ],
                        'is_popular' => true,
                    ],
                    [
                        'name' => 'Free Preview',
                        'description' => 'Limited free access',
                        'price' => 0, // FREE
                        'quantity_available' => 100,
                        'features' => [
                            'First hour free',
                            'Basic materials'
                        ],
                        'is_popular' => false,
                    ],
                ],
                'speakers' => [
                    [
                        'name' => 'Alex Developer',
                        'topic' => 'React & Next.js',
                        'bio' => 'Senior full-stack developer with expertise in modern JavaScript frameworks.',
                    ],
                    [
                        'name' => 'Maria Code',
                        'topic' => 'Backend Development',
                        'bio' => 'Backend architect specializing in scalable server solutions.',
                    ],
                ],
            ],
        ];

        foreach ($events as $eventData) {
            $ticketTypes = $eventData['ticket_types'];
            $speakers = $eventData['speakers'] ?? [];
            unset($eventData['ticket_types'], $eventData['speakers']);

            // Create event
            $event = Event::create(array_merge($eventData, [
                'business_id' => $business->id,
                'slug' => Str::slug($eventData['title']),
            ]));

            // Create ticket types
            foreach ($ticketTypes as $ticketTypeData) {
                $features = $ticketTypeData['features'] ?? [];
                $isPopular = $ticketTypeData['is_popular'] ?? false;
                unset($ticketTypeData['features'], $ticketTypeData['is_popular']);

                $ticketType = TicketType::create(array_merge($ticketTypeData, [
                    'event_id' => $event->id,
                    'is_active' => true,
                ]));

                // Store features as JSON in description or create a separate field
                // For now, we'll append features to description
                if (!empty($features)) {
                    $featuresText = "\n\nIncludes:\n" . implode("\n", array_map(fn($f) => "• " . $f, $features));
                    $ticketType->update([
                        'description' => ($ticketType->description ?? '') . $featuresText
                    ]);
                }
            }

            // Create speakers
            foreach ($speakers as $index => $speakerData) {
                EventSpeaker::create(array_merge($speakerData, [
                    'event_id' => $event->id,
                    'display_order' => $index,
                ]));
            }

            $this->command->info("Created event: {$event->title}");
        }

        $this->command->info('Events seeded successfully!');
    }
}
