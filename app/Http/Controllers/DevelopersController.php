<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeveloperProgramApplicationRequest;
use App\Mail\DeveloperProgramApplicationMail;
use App\Models\DeveloperProgramApplication;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class DevelopersController extends Controller
{
    public function index(): View
    {
        return view('developers.index');
    }

    public function program(): View
    {
        return view('developers.program', [
            'developerProgramFeeSharePercent' => Setting::get('developer_program_fee_share_percent'),
            'developerProgramFeeShareBaseDescription' => Setting::get('developer_program_fee_share_base_description')
                ?: 'CheckoutPay’s transaction fee revenue on qualifying attributed volume',
        ]);
    }

    public function apply(): View
    {
        return view('developers.program-apply');
    }

    public function applyStore(DeveloperProgramApplicationRequest $request): RedirectResponse
    {
        $application = DeveloperProgramApplication::create([
            'name' => $request->validated('name'),
            'business_id' => $request->validated('business_id') ?: null,
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'whatsapp' => $request->validated('whatsapp'),
            'community_preference' => $request->validated('community'),
            'status' => DeveloperProgramApplication::STATUS_PENDING,
        ]);

        $to = Setting::get('contact_email');
        if (is_string($to) && $to !== '') {
            try {
                Mail::to($to)->send(new DeveloperProgramApplicationMail($application));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('developers.program.apply.thanks')
            ->with('developer_program_community', $application->community_preference);
    }

    public function applyThanks(): View|RedirectResponse
    {
        if (! session()->has('developer_program_community')) {
            return redirect()->route('developers.program.apply');
        }

        return view('developers.program-apply-thanks', [
            'community' => session('developer_program_community'),
            'slackUrl' => config('developer_program.slack_invite_url'),
            'whatsappUrl' => config('developer_program.whatsapp_community_url'),
        ]);
    }
}
