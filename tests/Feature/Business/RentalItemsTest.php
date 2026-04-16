<?php

namespace Tests\Feature\Business;

use App\Models\Business;
use App\Models\RentalCategory;
use App\Models\RentalItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalItemsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function business_can_create_rental_item_when_is_featured_is_unchecked(): void
    {
        $business = Business::create([
            'name' => 'Biz A',
            'email' => 'biza@test.com',
            'is_active' => true,
        ]);

        $category = RentalCategory::create([
            'name' => 'Cameras',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($business, 'business');

        $response = $this->post('/dashboard/rentals/items', [
            'category_id' => $category->id,
            'name' => 'Canon R5',
            'description' => 'Test item',
            'daily_rate' => 1000,
            'quantity_available' => 1,
        ]);

        $response->assertRedirect('/dashboard/rentals/items');

        $this->assertDatabaseHas('rental_items', [
            'business_id' => $business->id,
            'category_id' => $category->id,
            'name' => 'Canon R5',
        ]);
    }

    /** @test */
    public function business_can_clone_any_uploaded_item_and_only_change_description_during_clone(): void
    {
        $owner = Business::create([
            'name' => 'Owner Biz',
            'email' => 'owner@test.com',
            'is_active' => true,
        ]);

        $cloner = Business::create([
            'name' => 'Cloner Biz',
            'email' => 'cloner@test.com',
            'is_active' => true,
        ]);

        $category = RentalCategory::create([
            'name' => 'Lenses',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $source = RentalItem::create([
            'business_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Sigma 24-70',
            'description' => 'Original description',
            'city' => 'Abuja',
            'daily_rate' => 2500,
            'quantity_available' => 2,
            'is_active' => true,
            'is_available' => true,
            'is_featured' => false,
        ]);

        $this->actingAs($cloner, 'business');

        $response = $this->post("/dashboard/rentals/items/{$source->id}/clone", [
            'description' => 'My cloned description',
        ]);

        $newItem = RentalItem::where('business_id', $cloner->id)->latest('id')->first();
        $this->assertNotNull($newItem);

        $response->assertRedirect("/dashboard/rentals/items/{$newItem->id}/edit");

        $this->assertDatabaseHas('rental_items', [
            'id' => $newItem->id,
            'business_id' => $cloner->id,
            'category_id' => $source->category_id,
            'name' => $source->name,
            'description' => 'My cloned description',
        ]);

        $source->refresh();
        $this->assertSame('Original description', $source->description);
        $this->assertSame((float) $source->daily_rate, (float) $newItem->daily_rate);
    }
}

