<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'department_id',
        'reporter_id',
        'assigned_to',
        'title',
        'location_details',
        'description',
        'priority',
        'status',
        'due_at',
    ];

    protected $appends = [
        'category',
        'category_id',
        'infrastructure_type',
        'network_type',
        'rating',
        'feedback_comment',
        'rated_at',
        'hold_reason_category',
        'hold_reason_note',
        'hold_started_at',
        'affected_device',
        'actual_repair_location',
        'inspection_result',
        'root_cause',
        'action_taken',
        'materials_used',
        'test_result',
        'test_parameters',
        'resolution_note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
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

    public function resolution(): HasOne
    {
        return $this->hasOne(TicketResolution::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(TicketFeedback::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(TicketHold::class);
    }

    public function latestHold(): HasOne
    {
        return $this->hasOne(TicketHold::class)->latestOfMany();
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

    // Dynamic Accessors for Relational Data & Backward Compatibility
    public function getCategoryAttribute()
    {
        return $this->resolution?->category;
    }

    public function getCategoryIdAttribute()
    {
        return $this->resolution?->category_id;
    }

    public function getInfrastructureTypeAttribute()
    {
        return $this->resolution?->category?->infrastructure_type;
    }

    public function getNetworkTypeAttribute()
    {
        return $this->resolution?->category?->infrastructure_type;
    }

    public function getRatingAttribute()
    {
        return $this->feedback?->rating;
    }

    public function getFeedbackCommentAttribute()
    {
        return $this->feedback?->feedback_comment;
    }

    public function getRatedAtAttribute()
    {
        return $this->feedback?->rated_at ?? $this->feedback?->created_at;
    }

    public function getHoldReasonCategoryAttribute()
    {
        return $this->latestHold?->reason_category;
    }

    public function getHoldReasonNoteAttribute()
    {
        return $this->latestHold?->reason_note;
    }

    public function getHoldStartedAtAttribute()
    {
        return $this->isOnHold() ? $this->latestHold?->started_at : null;
    }

    public function getTotalHoldDurationMinutesAttribute()
    {
        return (int) $this->holds()->sum('duration_minutes');
    }

    public function getAffectedDeviceAttribute()
    {
        return $this->resolution?->affected_device;
    }

    public function getActualRepairLocationAttribute()
    {
        return $this->resolution?->actual_repair_location;
    }

    public function getInspectionResultAttribute()
    {
        return $this->resolution?->inspection_result;
    }

    public function getRootCauseAttribute()
    {
        return $this->resolution?->root_cause;
    }

    public function getActionTakenAttribute()
    {
        return $this->resolution?->action_taken;
    }

    public function getMaterialsUsedAttribute()
    {
        return $this->resolution?->materials_used;
    }

    public function getTestResultAttribute()
    {
        return $this->resolution?->test_result;
    }

    public function getTestParametersAttribute()
    {
        return $this->resolution?->test_parameters;
    }

    public function getResolutionNoteAttribute()
    {
        return $this->resolution?->resolution_note;
    }

    public function getResolvedAtAttribute()
    {
        return $this->resolution?->resolved_at ?? $this->resolution?->created_at;
    }

    public function getAssignedAtAttribute()
    {
        return $this->statusHistories->firstWhere('new_status', 'in_progress')?->created_at;
    }

    public function getCancelledAtAttribute()
    {
        return $this->statusHistories->firstWhere('new_status', 'cancelled')?->created_at;
    }

    public function getClosedAtAttribute()
    {
        return $this->statusHistories->firstWhere('new_status', 'closed')?->created_at;
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

    public function isOnHold(): bool
    {
        return $this->status === 'on_hold';
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
}
