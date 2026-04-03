<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NigtaxProSavedQuery extends Model
{
    protected $table = 'nigtax_pro_saved_queries';

    protected $fillable = [
        'nigtax_pro_user_id',
        'mode',
        'snapshot',
        'statement_filename',
        'statement_pdf_path',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(NigtaxProUser::class, 'nigtax_pro_user_id');
    }
}
