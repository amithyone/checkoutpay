<?php

namespace App\Http\Requests;

use App\Services\RecaptchaService;
use App\Services\Security\RegistrationAbuseGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'business_id' => ['nullable', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:64'],
            'community' => ['required', 'in:slack,whatsapp,both'],
        ];

        if (app(RecaptchaService::class)->isEnabled()) {
            $rules['g-recaptcha-response'] = ['required', 'string'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $abuseGuard = app(RegistrationAbuseGuard::class);
            $fields = [
                'name' => $this->input('name'),
                'email' => $this->input('email'),
                'business_id' => $this->input('business_id'),
            ];
            if ($reason = $abuseGuard->blockReason((string) $this->ip(), $fields)) {
                $abuseGuard->logBlocked('developer_program_apply', (string) $this->ip(), $fields, $reason);
                $validator->errors()->add('email', 'Application could not be submitted. Contact support if you need help.');

                return;
            }

            $recaptcha = app(RecaptchaService::class);
            if (! $recaptcha->isEnabled()) {
                return;
            }

            if (! $recaptcha->verify((string) $this->input('g-recaptcha-response', ''), $this->ip())) {
                $validator->errors()->add(
                    'g-recaptcha-response',
                    'reCAPTCHA verification failed. Please try again.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'community.in' => 'Please choose how you want to join the developer community.',
            'g-recaptcha-response.required' => 'Please complete the reCAPTCHA verification.',
        ];
    }
}
