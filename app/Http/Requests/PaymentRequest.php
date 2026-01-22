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
            'name' => ['nullable', 'string', 'max:255'], // Accept 'name' from API (will be mapped to payer_name)
            'payer_name' => ['nullable', 'string', 'max:255'], // Accept 'payer_name' directly
            'bank' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['required', 'url', 'max:500'],
            'service' => ['nullable', 'string', 'max:255'], // Accept service field
            'transaction_id' => ['nullable', 'string', 'max:255', 'unique:payments,transaction_id'],
            'business_website_id' => ['nullable', 'integer', 'exists:business_websites,id'], // Allow explicit website ID
            'website_url' => ['nullable', 'url', 'max:500'], // Allow website URL for identification
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Require either 'name' or 'payer_name' to be provided and not empty
            $hasName = $this->has('name') && !empty(trim($this->input('name', '')));
            $hasPayerName = $this->has('payer_name') && !empty(trim($this->input('payer_name', '')));
            
            if (!$hasName && !$hasPayerName) {
                $validator->errors()->add('payer_name', 'The payer name is required to get an account number. Please provide either "name" or "payer_name".');
            }
        });
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
            'payer_name.required' => 'The payer name is required to get an account number.',
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
        // Map 'name' field to 'payer_name' if provided (API uses 'name', internal uses 'payer_name')
        if ($this->has('name') && $this->name) {
            $this->merge([
                'payer_name' => trim($this->name),
            ]);
        }
        
        // Normalize payer name if provided directly
        if ($this->has('payer_name') && $this->payer_name) {
            $this->merge([
                'payer_name' => trim($this->payer_name),
            ]);
        }
        
        // Normalize webhook URL to prevent double slashes
        if ($this->has('webhook_url') && $this->webhook_url) {
            $webhookUrl = preg_replace('#([^:])//+#', '$1/', $this->webhook_url); // Fix double slashes but preserve http:// or https://
            $this->merge([
                'webhook_url' => $webhookUrl,
            ]);
        }
    }
}
