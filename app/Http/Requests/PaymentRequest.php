<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['required', 'url', 'max:500'],
            'transaction_id' => ['nullable', 'string', 'max:255', 'unique:payments,transaction_id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'The payment amount is required.',
            'amount.numeric' => 'The payment amount must be a valid number.',
            'amount.min' => 'The payment amount must be at least 0.01.',
            'webhook_url.required' => 'The webhook URL is required.',
            'webhook_url.url' => 'The webhook URL must be a valid URL.',
            'transaction_id.unique' => 'This transaction ID already exists.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize payer name if provided
        if ($this->has('payer_name') && $this->payer_name) {
            $this->merge([
                'payer_name' => trim($this->payer_name),
            ]);
        }
    }
}
