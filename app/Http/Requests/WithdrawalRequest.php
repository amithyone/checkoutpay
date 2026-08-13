<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:100'],
            'account_number' => ['required', 'string', 'size:10'],
            'account_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'bank_narration' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Withdrawal amount is required.',
            'amount.numeric' => 'Withdrawal amount must be a valid number.',
            'amount.min' => 'Minimum withdrawal amount is ₦100.',
            'account_number.required' => 'Account number is required.',
            'account_number.size' => 'Account number must be 10 digits.',
            'account_name.required' => 'Account name is required.',
            'bank_name.required' => 'Bank name is required.',
        ];
    }
}
