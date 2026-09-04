<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'department_id',
        'reporter_id',
        'assigned_to',
        'category_id',
        'infrastructure_type',
        'network_type', // backward compatibility alias
        'affected_device',
        'actual_repair_location',
        'title',
        'location_details',
        'description',
        'priority',
        'status',
        'resolution_note',
        'inspection_result',
        'root_cause',
        'action_taken',
        'materials_used',
        'test_result',
        'test_parameters',
        'assigned_at',
        'cancelled_at',
        'due_at',
        'resolved_at',
        'closed_at',
        'rating',
        'feedback_comment',
        'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'rated_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_technicians')->withTimestamps();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id')->withTrashed();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function resolutionProofs(): HasMany
    {
        return $this->hasMany(TicketAttachment::class)->where('attachment_type', 'resolution_proof');
    }

    public function issueProofs(): HasMany
    {
        return $this->hasMany(TicketAttachment::class)->where('attachment_type', 'issue_proof');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class);
    }

    public function whatsappNotifications(): HasMany
    {
        return $this->hasMany(WhatsappNotification::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(TicketRead::class);
    }

    // Status Helper Methods
    public function isPendingAdmin(): bool
    {
        return $this->status === 'pending_admin';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeResubmitted(): bool
    {
        if (!$this->isCancelled()) {
            return false;
        }

        $cancelledTime = $this->cancelled_at ?? $this->updated_at;
        if (!$cancelledTime) {
            return false;
        }

        $diffHours = (now()->getTimestamp() - $cancelledTime->getTimestamp()) / 3600;
        return $diffHours >= 0 && $diffHours < 72;
    }

    public function getNetworkTypeAttribute()
    {
        return $this->attributes['infrastructure_type'] ?? null;
    }

    public function setNetworkTypeAttribute($value)
    {
        $this->attributes['infrastructure_type'] = $value;
    }
}
