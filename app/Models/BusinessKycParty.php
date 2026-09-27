<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessKycParty extends Model
{
    public const ROLE_DIRECTOR = 'director';

    public const ROLE_SHAREHOLDER = 'shareholder';

    public const ROLE_UBO = 'ubo';

    public const ROLE_CONTROLLER = 'controller';

    public const ROLE_SIGNATORY = 'signatory';

    public const PERSON_NATURAL = 'natural';

    public const PERSON_CORPORATE = 'corporate';

    /** @var list<string> */
    public const ROLES = [
        self::ROLE_DIRECTOR,
        self::ROLE_SHAREHOLDER,
        self::ROLE_UBO,
        self::ROLE_CONTROLLER,
        self::ROLE_SIGNATORY,
    ];

    protected $fillable = [
        'business_account_application_id',
        'parent_party_id',
        'role',
        'person_type',
        'legal_name',
        'dob',
        'nationality',
        'bvn',
        'nin',
        'email',
        'phone',
        'ownership_percent',
        'is_nominee',
        'identity_verified_at',
        'mevon_reference',
        'mevon_full_name',
        'id_document_path',
        'authority_document_path',
        'meta',
    ];

    protected $casts = [
        'dob' => 'date',
        'ownership_percent' => 'decimal:4',
        'is_nominee' => 'boolean',
        'identity_verified_at' => 'datetime',
        'meta' => 'array',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(BusinessAccountApplication::class, 'business_account_application_id');
    }

    public function parentParty(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_party_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_party_id');
    }

    public function isNigerianNatural(): bool
    {
        $nat = strtoupper(trim((string) $this->nationality));

        return $this->person_type === self::PERSON_NATURAL
            && ($nat === '' || $nat === 'NG' || $nat === 'NGA');
    }
}
