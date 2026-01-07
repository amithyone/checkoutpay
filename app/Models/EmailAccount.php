<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class EmailAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'host',
        'port',
        'encryption',
        'validate_cert',
        'password',
        'folder',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'port' => 'integer',
        'validate_cert' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get encrypted password
     */
    public function getPasswordAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value; // Return as-is if decryption fails
        }
    }

    /**
     * Set encrypted password
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    /**
     * Get businesses using this email account
     */
    public function businesses()
    {
        return $this->hasMany(Business::class);
    }

    /**
     * Test email connection
     */
    public function testConnection(): array
    {
        try {
            // Get decrypted password
            $password = $this->getPasswordAttribute($this->attributes['password'] ?? '');
            
            if (empty($password)) {
                return [
                    'success' => false,
                    'message' => 'Password is empty. Please enter a valid password.',
                ];
            }

            // Validate required fields
            if (empty($this->host) || empty($this->email)) {
                return [
                    'success' => false,
                    'message' => 'Host and email are required.',
                ];
            }

            $cm = new \Webklex\PHPIMAP\ClientManager([
                'default' => 'test_' . $this->id,
                'accounts' => [
                    'test_' . $this->id => [
                        'host' => $this->host,
                        'port' => (int)$this->port,
                        'encryption' => $this->encryption,
                        'validate_cert' => (bool)$this->validate_cert,
                        'username' => $this->email,
                        'password' => $password,
                        'protocol' => 'imap',
                    ],
                ],
            ]);

            $client = $cm->account('test_' . $this->id);
            $client->connect();
            
            // Try to access the folder
            $folder = $client->getFolder($this->folder ?? 'INBOX');
            
            // Test folder access
            $folder->query()->limit(1)->get();
            
            $client->disconnect();

            return [
                'success' => true,
                'message' => 'Connection successful! Successfully connected to ' . $this->email,
            ];
        } catch (\Webklex\PHPIMAP\Exceptions\ConnectionFailedException $e) {
            \Illuminate\Support\Facades\Log::error('Email connection failed', [
                'email' => $this->email,
                'host' => $this->host,
                'port' => $this->port,
                'error' => $e->getMessage(),
            ]);
            
            $errorMessage = $e->getMessage();
            
            // Provide helpful error messages
            if (strpos($errorMessage, 'authentication') !== false || strpos($errorMessage, 'password') !== false) {
                return [
                    'success' => false,
                    'message' => 'Authentication failed. Please check your email and password. For Gmail, make sure you\'re using an App Password, not your regular password.',
                ];
            }
            
            if (strpos($errorMessage, 'connection') !== false || strpos($errorMessage, 'timeout') !== false) {
                return [
                    'success' => false,
                    'message' => 'Connection failed. Please check your host (' . $this->host . ') and port (' . $this->port . ') settings.',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $errorMessage,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Email connection test error', [
                'email' => $this->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }
}
