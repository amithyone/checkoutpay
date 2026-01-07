<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestEmailController extends Controller
{
    /**
     * Standalone Gmail IMAP connection test
     * Access via: /test-email?email=fastifysales@gmail.com&password=your_app_password
     */
    public function test(Request $request)
    {
        // Get credentials from request
        $email = $request->input('email', 'fastifysales@gmail.com');
        $password = $request->input('password', '');
        $host = $request->input('host', 'imap.gmail.com');
        $port = $request->input('port', 993);
        $encryption = $request->input('encryption', 'ssl');
        $folder = $request->input('folder', 'INBOX');

        // Check if IMAP extension is available
        if (!function_exists('imap_open')) {
            return response()->json([
                'success' => false,
                'message' => 'PHP IMAP extension is not installed on the server',
                'error_type' => 'extension_missing'
            ], 500);
        }

        // Build connection string
        $encryptionFlag = $encryption === 'ssl' ? 'ssl' : ($encryption === 'tls' ? 'tls' : 'notls');
        $connectionString = "{{$host}:{$port}/{$encryptionFlag}/novalidate-cert}{$folder}";

        // Attempt connection
        $connection = @imap_open($connectionString, $email, $password, OP_HALFOPEN);

        if ($connection) {
            // Get mailbox info
            $mailboxInfo = @imap_status($connection, "{{$host}:{$port}/{$encryptionFlag}/novalidate-cert}{$folder}", SA_ALL);
            
            $result = [
                'success' => true,
                'message' => '✅ CONNECTION SUCCESSFUL! Your credentials are CORRECT!',
                'credentials_correct' => true,
                'mailbox_info' => [
                    'messages' => $mailboxInfo->messages ?? 0,
                    'recent' => $mailboxInfo->recent ?? 0,
                    'unseen' => $mailboxInfo->unseen ?? 0,
                ],
                'settings_used' => [
                    'host' => $host,
                    'port' => $port,
                    'encryption' => $encryption,
                    'email' => $email,
                ],
                'note' => 'If you see this success message but still get firewall errors in admin panel, the issue is server firewall blocking outbound connections.'
            ];

            // Test reading messages
            $messages = @imap_search($connection, 'ALL');
            if ($messages) {
                $result['mailbox_info']['total_messages'] = count($messages);
            }

            imap_close($connection);
            
            return response()->json($result);
        } else {
            $error = imap_last_error();
            imap_errors(); // Clear error buffer
            
            $errorMessage = $error ?: 'Unknown error';
            
            // Determine error type
            $errorType = 'unknown';
            $diagnosis = '';
            
            if (stripos($errorMessage, 'authentication') !== false || 
                stripos($errorMessage, 'login') !== false ||
                stripos($errorMessage, 'invalid') !== false) {
                $errorType = 'authentication';
                $diagnosis = 'Authentication failed. Check: 1) Email address is correct, 2) App Password is correct (16 characters), 3) IMAP is enabled in Gmail, 4) 2-Step Verification is enabled';
            } elseif (stripos($errorMessage, 'unreachable') !== false || 
                      stripos($errorMessage, 'Network') !== false ||
                      stripos($errorMessage, 'Connection refused') !== false) {
                $errorType = 'network';
                $diagnosis = 'Network/Firewall issue. The server cannot reach Gmail. Contact hosting provider to allow outbound IMAP connections on port 993.';
            } elseif (stripos($errorMessage, 'certificate') !== false || 
                     stripos($errorMessage, 'SSL') !== false) {
                $errorType = 'ssl';
                $diagnosis = 'SSL/Certificate issue. Try with validate_cert=false (already included).';
            }

            return response()->json([
                'success' => false,
                'message' => '❌ CONNECTION FAILED',
                'credentials_correct' => false,
                'error' => $errorMessage,
                'error_type' => $errorType,
                'diagnosis' => $diagnosis,
                'settings_used' => [
                    'host' => $host,
                    'port' => $port,
                    'encryption' => $encryption,
                    'email' => $email,
                ],
                'help' => [
                    'check_app_password' => 'Make sure you\'re using Gmail App Password, not regular password',
                    'check_imap_enabled' => 'Enable IMAP in Gmail: https://mail.google.com/mail/u/0/#settings/general',
                    'check_2fa' => 'Enable 2-Step Verification: https://myaccount.google.com/security',
                    'get_app_password' => 'Generate App Password: https://myaccount.google.com/apppasswords'
                ]
            ], 400);
        }
    }
}
