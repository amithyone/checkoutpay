<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Business;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class EventService
{
    /**
     * Create a new event
     */
    public function createEvent(Business $business, array $data): Event
    {
        // Handle image uploads
        if (isset($data['event_image']) && $data['event_image']->isValid()) {
            $data['event_image'] = $this->uploadImage($data['event_image'], 'events');
        }

        if (isset($data['event_banner']) && $data['event_banner']->isValid()) {
            $data['event_banner'] = $this->uploadImage($data['event_banner'], 'events/banners');
        }

        $data['business_id'] = $business->id;
        
        return Event::create($data);
    }

    /**
     * Update an event
     */
    public function updateEvent(Event $event, array $data): Event
    {
        // Handle image uploads
        if (isset($data['event_image']) && $data['event_image']->isValid()) {
            // Delete old image
            if ($event->event_image) {
                Storage::disk('public')->delete($event->event_image);
            }
            $data['event_image'] = $this->uploadImage($data['event_image'], 'events');
        }

        if (isset($data['event_banner']) && $data['event_banner']->isValid()) {
            // Delete old banner
            if ($event->event_banner) {
                Storage::disk('public')->delete($event->event_banner);
            }
            $data['event_banner'] = $this->uploadImage($data['event_banner'], 'events/banners');
        }

        $event->update($data);
        return $event->fresh();
    }

    /**
     * Delete an event
     */
    public function deleteEvent(Event $event): bool
    {
        // Delete images
        if ($event->event_image) {
            Storage::disk('public')->delete($event->event_image);
        }
        if ($event->event_banner) {
            Storage::disk('public')->delete($event->event_banner);
        }

        return $event->delete();
    }

    /**
     * Upload image
     */
    protected function uploadImage($file, string $path): string
    {
        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $file->storeAs($path, $filename, 'public');
        return $path . '/' . $filename;
    }

    /**
     * Publish an event
     */
    public function publishEvent(Event $event): Event
    {
        $event->update(['status' => 'published']);
        return $event->fresh();
    }

    /**
     * Cancel an event
     */
    public function cancelEvent(Event $event): Event
    {
        $event->update(['status' => 'cancelled']);
        return $event->fresh();
    }
}
