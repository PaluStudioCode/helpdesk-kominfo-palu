<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFeedback extends Model
{
    use HasFactory;

    protected $table = 'ticket_feedbacks';

    protected $fillable = [
        'ticket_id',
        'rating',
        'feedback_comment',
        'rated_by',
        'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'rated_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }
}
