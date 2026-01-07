<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Services\GmailApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GmailAuthController extends Controller
{
    /**
     * Show authorization URL for Gmail API
     */
    public function authorize(EmailAccount $emailAccount)
    {
        if ($emailAccount->method !== 'gmail_api') {
            return back()->with('error', 'This email account is not configured for Gmail API.');
        }

        try {
            $gmailService = new GmailApiService($emailAccount);
            $authUrl = $gmailService->getAuthorizationUrl();
            
            return redirect($authUrl);
        } catch (\Exception $e) {
            Log::error('Error getting Gmail authorization URL', [
                'email_account_id' => $emailAccount->id,
                'error' => $e->getMessage(),
            ]);
            
            return back()->with('error', 'Failed to get authorization URL: ' . $e->getMessage());
        }
    }

    /**
     * Handle OAuth callback
     */
    public function callback(Request $request, EmailAccount $emailAccount)
    {
        try {
            $code = $request->get('code');
            
            if (!$code) {
                return redirect()->route('admin.email-accounts.index')
                    ->with('error', 'Authorization failed: No code received');
            }

            $gmailService = new GmailApiService($emailAccount);
            $gmailService->handleCallback($code);
            
            // Test connection
            $testResult = $gmailService->testConnection();
            
            if ($testResult['success']) {
                $emailAccount->update([
                    'gmail_authorized' => true,
                    'gmail_authorization_url' => null,
                ]);
                
                return redirect()->route('admin.email-accounts.index')
                    ->with('success', 'Gmail API authorized successfully! Email: ' . $testResult['email']);
            } else {
                return redirect()->route('admin.email-accounts.index')
                    ->with('error', 'Authorization succeeded but connection test failed: ' . $testResult['message']);
            }
        } catch (\Exception $e) {
            Log::error('Error handling Gmail OAuth callback', [
                'email_account_id' => $emailAccount->id,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('admin.email-accounts.index')
                ->with('error', 'Authorization failed: ' . $e->getMessage());
        }
    }
}
