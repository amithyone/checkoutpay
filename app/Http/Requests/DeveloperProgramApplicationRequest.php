<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeveloperProgramApplicationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'business_id' => ['nullable', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:64'],
            'community' => ['required', 'in:slack,whatsapp,both'],
        ];
    }

    public function messages(): array
    {
        return [
            'community.in' => 'Please choose how you want to join the developer community.',
        ];
    }
}
