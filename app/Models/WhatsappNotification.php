<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappNotification extends Model
{
    use HasFactory;

    const UPDATED_AT = null; // Table doesn't have updated_at

    protected $fillable = [
        'ticket_id',
        'recipient_id',
        'target_phone',
        'event_type',
        'message_content',
        'status',
        'response_payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response_payload' => 'json',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
