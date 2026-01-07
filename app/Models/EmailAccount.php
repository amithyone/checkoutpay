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

            // Auto-fix common port/encryption mismatches
            $port = (int)$this->port;
            $encryption = $this->encryption;
            
            // Gmail port 993 requires SSL, not TLS
            if ($port === 993 && $encryption === 'tls') {
                $encryption = 'ssl';
                \Illuminate\Support\Facades\Log::info('Auto-corrected TLS to SSL for port 993', [
                    'email' => $this->email,
                ]);
            }
            // Port 587 typically uses TLS
            if ($port === 587 && $encryption === 'ssl') {
                $encryption = 'tls';
            }
            
            // Warn if using POP settings
            if (strpos(strtolower($this->host), 'pop') !== false) {
                return [
                    'success' => false,
                    'message' => 'POP is not supported. Please use IMAP. Change host to "imap.gmail.com" and port to 993.',
                ];
            }
            
            $cm = new \Webklex\PHPIMAP\ClientManager([
                'default' => 'test_' . $this->id,
                'accounts' => [
                    'test_' . $this->id => [
                        'host' => $this->host,
                        'port' => $port,
                        'encryption' => $encryption,
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
                'encryption' => $encryption,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $errorMessage = $e->getMessage();
            
            // Provide helpful error messages
            if (strpos($errorMessage, 'authentication') !== false || strpos($errorMessage, 'password') !== false || strpos($errorMessage, 'login') !== false) {
                return [
                    'success' => false,
                    'message' => 'Authentication failed. Please check your email and password. For Gmail, make sure you\'re using an App Password (16 characters), not your regular password. Also verify IMAP is enabled in Gmail settings.',
                ];
            }
            
            if (strpos($errorMessage, 'unreachable') !== false || strpos($errorMessage, 'Network is unreachable') !== false) {
                return [
                    'success' => false,
                    'message' => 'Network is unreachable. The server cannot connect to Gmail. This is a firewall/network issue. Contact your hosting provider to allow outbound IMAP connections on port 993. Error: ' . $errorMessage,
                ];
            }
            
            if (strpos($errorMessage, 'connection') !== false || strpos($errorMessage, 'timeout') !== false || strpos($errorMessage, 'refused') !== false) {
                $detailedMessage = 'Connection failed to ' . $this->host . ':' . $this->port . '. ';
                $detailedMessage .= 'Possible issues: ';
                $detailedMessage .= '1) Server firewall blocking outbound connections, ';
                $detailedMessage .= '2) PHP IMAP extension not installed, ';
                $detailedMessage .= '3) Network connectivity issue. ';
                $detailedMessage .= 'Check server logs for details. Error: ' . $errorMessage;
                
                return [
                    'success' => false,
                    'message' => $detailedMessage,
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $errorMessage . '. Check server logs for more details.',
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Email connection test error', [
                'email' => $this->email,
                'host' => $this->host,
                'port' => $this->port,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $errorMessage = $e->getMessage();
            
            // Check for PHP extension issues
            if (strpos($errorMessage, 'imap') !== false || strpos($errorMessage, 'extension') !== false) {
                return [
                    'success' => false,
                    'message' => 'PHP IMAP extension may not be installed. Contact your server administrator to install php-imap extension.',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Error: ' . $errorMessage . '. Check server logs for details.',
            ];
        }
    }
}
