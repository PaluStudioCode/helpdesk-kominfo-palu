<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStatusHistory extends Model
{
    use HasFactory;

    const UPDATED_AT = null; // Table doesn't have updated_at

    protected $fillable = [
        'ticket_id',
        'changed_by',
        'previous_status',
        'new_status',
        'comment',
        'created_at',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
