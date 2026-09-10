<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketResolution extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'category_id',
        'affected_device',
        'actual_repair_location',
        'inspection_result',
        'root_cause',
        'action_taken',
        'materials_used',
        'test_result',
        'test_parameters',
        'resolution_note',
        'resolved_by',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
