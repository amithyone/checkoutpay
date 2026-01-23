<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessWebsite extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'website_url',
        'webhook_url',
        'is_approved',
        'notes',
        'approved_at',
        'approved_by',
        'charge_percentage',
        'charge_fixed',
        'charges_paid_by_customer',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'charge_percentage' => 'decimal:2',
        'charge_fixed' => 'decimal:2',
        'charges_paid_by_customer' => 'boolean',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the business that owns this website
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the admin who approved this website
     */
    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /**
     * Scope to get only approved websites
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope to get only pending websites
     */
    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }

    /**
     * Get payments generated from this website
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'business_website_id');
    }

    /**
     * Retrieve the model for route model binding.
     * For business routes, we allow any website to be resolved - the controller will check ownership.
     * Route model binding happens before authentication middleware, so we can't scope here.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // Route model binding happens BEFORE authentication middleware runs
        // So we can't check auth('business')->check() here
        // Instead, we'll let the controller handle the authorization check
        // This ensures the website can be resolved, and then the controller verifies ownership
        
        \Log::info('BusinessWebsite resolveRouteBinding called', [
            'value' => $value,
            'field' => $field,
            'request_path' => request()->path(),
            'route_name' => request()->route()?->getName(),
        ]);

        // Use parent method to resolve the website normally
        // The controller will verify ownership after authentication middleware runs
        return parent::resolveRouteBinding($value, $field);
    }
}
