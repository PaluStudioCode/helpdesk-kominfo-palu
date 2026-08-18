<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
