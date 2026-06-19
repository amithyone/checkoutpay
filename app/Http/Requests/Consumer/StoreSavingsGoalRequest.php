<?php

namespace App\Http\Requests\Consumer;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavingsGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'target_amount' => 'required|numeric|min:100',
            'save_type' => 'sometimes|string|in:flexible,strict',
            'target_date' => 'sometimes|nullable|date|after_or_equal:today',
            'duration_days' => 'sometimes|nullable|integer|min:1|max:3650',
            'collection_mode' => 'sometimes|string|in:manual,per_incoming,balance_threshold',
            'auto_save_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'balance_threshold' => 'sometimes|nullable|numeric|min:0',
            'ledger_scope' => 'sometimes|string|in:personal,business,both',
            'auto_save_enabled' => 'sometimes|boolean',
        ];
    }
}
