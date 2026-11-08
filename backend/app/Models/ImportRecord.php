<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRecord extends Model
{
    protected $table = 'import_history';

    protected $fillable = [
        'file_name',
        'uploaded_by',
        'user_id',
        'status',
        'storage_path',
        'total_rows',
        'successful_rows',
        'duplicate_rows',
        'invalid_rows',
        'skipped_rows',
        'summary',
    ];

    protected $casts = [
        'summary' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
