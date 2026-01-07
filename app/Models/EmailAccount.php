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
            $cm = new \Webklex\PHPIMAP\ClientManager([
                'default' => 'test',
                'accounts' => [
                    'test' => [
                        'host' => $this->host,
                        'port' => $this->port,
                        'encryption' => $this->encryption,
                        'validate_cert' => $this->validate_cert,
                        'username' => $this->email,
                        'password' => $this->password,
                        'protocol' => 'imap',
                    ],
                ],
            ]);

            $client = $cm->account('test');
            $client->connect();
            $folder = $client->getFolder($this->folder);
            $client->disconnect();

            return [
                'success' => true,
                'message' => 'Connection successful!',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
