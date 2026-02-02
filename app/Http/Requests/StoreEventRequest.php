<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::guard('business')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_type' => 'required|in:online,offline',
            'venue' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
            'timezone' => 'nullable|string|max:50',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'max_attendees' => 'nullable|integer|min:1',
            'max_tickets_per_customer' => 'nullable|integer|min:1',
            'allow_refunds' => 'nullable|boolean',
            'refund_policy' => 'nullable|string|max:1000',
            'ticket_types' => 'required|array|min:1',
            'ticket_types.*.name' => 'required|string|max:255',
            'ticket_types.*.description' => 'nullable|string',
            'ticket_types.*.price' => 'required|numeric|min:0',
            'ticket_types.*.quantity_available' => 'required|integer|min:1',
            'ticket_types.*.sales_start_date' => 'nullable|date',
            'ticket_types.*.sales_end_date' => 'nullable|date|after:ticket_types.*.sales_start_date',
        ];

        // Address is required for offline events
        if ($this->input('event_type') === 'offline') {
            $rules['address'] = 'required|string|max:500';
        }

        return $rules;
    }
}
