<?php

namespace App\Services;

use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GmailApiService
{
    protected $client;
    protected $service;
    protected $credentialsPath;
    protected $tokenPath;
    protected $emailAccount;

    public function __construct($emailAccount = null)
    {
        $this->emailAccount = $emailAccount;
        
        if ($emailAccount) {
            $this->credentialsPath = $emailAccount->gmail_credentials_path 
                ? storage_path('app/' . $emailAccount->gmail_credentials_path)
                : storage_path('app/gmail-credentials.json');
            $this->tokenPath = $emailAccount->gmail_token_path
                ? storage_path('app/' . $emailAccount->gmail_token_path)
                : storage_path('app/gmail-token-' . $emailAccount->id . '.json');
        } else {
            $this->credentialsPath = storage_path('app/gmail-credentials.json');
            $this->tokenPath = storage_path('app/gmail-token.json');
        }
        
        $this->initializeClient();
    }

    /**
     * Initialize Google Client
     */
    protected function initializeClient(): void
    {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Email Payment Gateway');
        $this->client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
        $this->client->setAuthConfig($this->credentialsPath);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        if (file_exists($this->tokenPath)) {
            $accessToken = json_decode(file_get_contents($this->tokenPath), true);
            $this->client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($this->client->isAccessTokenExpired()) {
            // Refresh the token if possible, otherwise fetch a new one.
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $this->client->createAuthUrl();
                
                // Update email account with authorization URL if model exists
                if ($this->emailAccount) {
                    $this->emailAccount->update([
                        'gmail_authorization_url' => $authUrl,
                        'gmail_authorized' => false,
                    ]);
                }
                
                throw new \Exception("Please visit this URL to authorize the application: {$authUrl}");
            }
            
            // Save the token for future use
            if (!file_exists(dirname($this->tokenPath))) {
                mkdir(dirname($this->tokenPath), 0700, true);
            }
            file_put_contents($this->tokenPath, json_encode($this->client->getAccessToken()));
            
            // Update email account authorization status
            if ($this->emailAccount) {
                $this->emailAccount->update([
                    'gmail_authorized' => true,
                    'gmail_authorization_url' => null,
                ]);
            }
        }

        $this->service = new Google_Service_Gmail($this->client);
    }

    /**
     * Get unread messages from Gmail
     */
    public function getUnreadMessages($maxResults = 50): array
    {
        try {
            $userId = 'me';
            $optParams = [
                'maxResults' => $maxResults,
                'q' => 'is:unread',
            ];

            $messages = $this->service->users_messages->listUsersMessages($userId, $optParams);
            $result = [];

            foreach ($messages->getMessages() as $message) {
                $msg = $this->service->users_messages->get($userId, $message->getId());
                $result[] = $this->parseMessage($msg);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Gmail API Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get messages since a specific date
     */
    public function getMessagesSince(\DateTime $since, $maxResults = 50): array
    {
        try {
            $userId = 'me';
            $sinceFormatted = $since->format('Y/m/d');
            $optParams = [
                'maxResults' => $maxResults,
                'q' => "is:unread after:{$sinceFormatted}",
            ];

            $messages = $this->service->users_messages->listUsersMessages($userId, $optParams);
            $result = [];

            foreach ($messages->getMessages() as $message) {
                $msg = $this->service->users_messages->get($userId, $message->getId());
                $result[] = $this->parseMessage($msg);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Gmail API Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse Gmail message to standard format
     */
    protected function parseMessage(Google_Service_Gmail_Message $message): array
    {
        $payload = $message->getPayload();
        $headers = $payload->getHeaders();
        
        $emailData = [
            'id' => $message->getId(),
            'thread_id' => $message->getThreadId(),
            'subject' => $this->getHeader($headers, 'Subject'),
            'from' => $this->getHeader($headers, 'From'),
            'to' => $this->getHeader($headers, 'To'),
            'date' => $this->getHeader($headers, 'Date'),
            'text' => $this->getMessageBody($payload, 'text/plain'),
            'html' => $this->getMessageBody($payload, 'text/html'),
        ];

        return $emailData;
    }

    /**
     * Get header value
     */
    protected function getHeader($headers, $name): string
    {
        foreach ($headers as $header) {
            if ($header->getName() === $name) {
                return $header->getValue();
            }
        }
        return '';
    }

    /**
     * Get message body
     */
    protected function getMessageBody($payload, $mimeType): string
    {
        $body = $payload->getBody();
        if ($body && $body->getData()) {
            return base64_decode(str_replace(['-', '_'], ['+', '/'], $body->getData()));
        }

        // Check parts for multipart messages
        $parts = $payload->getParts();
        if ($parts) {
            foreach ($parts as $part) {
                if ($part->getMimeType() === $mimeType) {
                    $body = $part->getBody();
                    if ($body && $body->getData()) {
                        return base64_decode(str_replace(['-', '_'], ['+', '/'], $body->getData()));
                    }
                }
            }
        }

        return '';
    }

    /**
     * Mark message as read
     */
    public function markAsRead(string $messageId): bool
    {
        try {
            $userId = 'me';
            $modifyRequest = new \Google_Service_Gmail_ModifyMessageRequest();
            $modifyRequest->setRemoveLabelIds(['UNREAD']);
            
            $this->service->users_messages->modify($userId, $messageId, $modifyRequest);
            return true;
        } catch (\Exception $e) {
            Log::error('Gmail API Error marking as read: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get authorization URL
     */
    public function getAuthorizationUrl(): string
    {
        if (!file_exists($this->credentialsPath)) {
            throw new \Exception('Gmail credentials file not found. Please upload credentials JSON file first.');
        }

        $this->client = new Google_Client();
        $this->client->setApplicationName('Email Payment Gateway');
        $this->client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
        $this->client->setAuthConfig($this->credentialsPath);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        $this->client->setRedirectUri($this->getRedirectUri());

        return $this->client->createAuthUrl();
    }

    /**
     * Handle OAuth callback
     */
    public function handleCallback(string $code): void
    {
        if (!file_exists($this->credentialsPath)) {
            throw new \Exception('Gmail credentials file not found.');
        }

        $this->client = new Google_Client();
        $this->client->setApplicationName('Email Payment Gateway');
        $this->client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
        $this->client->setAuthConfig($this->credentialsPath);
        $this->client->setAccessType('offline');
        $this->client->setRedirectUri($this->getRedirectUri());

        $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);
        
        if (array_key_exists('error', $accessToken)) {
            throw new \Exception('Error fetching access token: ' . $accessToken['error']);
        }

        // Save token
        if (!file_exists(dirname($this->tokenPath))) {
            mkdir(dirname($this->tokenPath), 0700, true);
        }
        file_put_contents($this->tokenPath, json_encode($this->client->getAccessToken()));

        $this->client->setAccessToken($accessToken);
        $this->service = new Google_Service_Gmail($this->client);
    }

    /**
     * Get OAuth redirect URI
     */
    protected function getRedirectUri(): string
    {
        $baseUrl = config('app.url');
        $emailAccountId = $this->emailAccount ? $this->emailAccount->id : 'default';
        return $baseUrl . '/admin/email-accounts/' . $emailAccountId . '/gmail/callback';
    }

    /**
     * Test connection
     */
    public function testConnection(): array
    {
        try {
            $userId = 'me';
            $profile = $this->service->users->getProfile($userId);
            
            return [
                'success' => true,
                'message' => 'Successfully connected to Gmail API',
                'email' => $profile->getEmailAddress(),
                'messages_total' => $profile->getMessagesTotal(),
                'threads_total' => $profile->getThreadsTotal(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to connect: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }
}
