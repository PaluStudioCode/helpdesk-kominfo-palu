<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    use HasFactory;

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
        // Virtual mutator attributes for mass-assignment & testing
        'assigned_at',
        'resolved_at',
        'closed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
        ];
    }

    protected $appends = [
        'hold_reason_category',
        'hold_reason_note',
        'hold_started_at',
        'total_hold_duration_minutes',
        'category',
        'infrastructure_type',
        'assigned_at',
        'resolved_at',
        'closed_at',
        'cancelled_at',
        'rating',
        'feedback_comment',
        'rated_at',
        'affected_device',
        'actual_repair_location',
        'inspection_result',
        'root_cause',
        'action_taken',
        'materials_used',
        'test_result',
        'test_parameters',
        'resolution_note',
    ];

    // =========================================================================
    // ELOQUENT RELATIONSHIPS
    // =========================================================================

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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

    public function reads(): HasMany
    {
        return $this->hasMany(TicketRead::class);
    }

    // =========================================================================
    // STATUS HELPER METHODS
    // =========================================================================

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

        $cancelledCarbon = $cancelledTime instanceof Carbon ? $cancelledTime : Carbon::parse($cancelledTime);

        $diffHours = (now()->getTimestamp() - $cancelledCarbon->getTimestamp()) / 3600;
        return $diffHours >= 0 && $diffHours < 72;
    }

    // =========================================================================
    // NORMALIZED RELATIONAL ACCESSORS
    // =========================================================================

    public function getResolutionNoteAttribute(): ?string
    {
        return $this->resolution?->resolution_note;
    }

    public function getAffectedDeviceAttribute(): ?string
    {
        return $this->resolution?->affected_device;
    }

    public function getActualRepairLocationAttribute(): ?string
    {
        return $this->resolution?->actual_repair_location;
    }

    public function getInspectionResultAttribute(): ?string
    {
        return $this->resolution?->inspection_result;
    }

    public function getRootCauseAttribute(): ?string
    {
        return $this->resolution?->root_cause;
    }

    public function getActionTakenAttribute(): ?string
    {
        return $this->resolution?->action_taken;
    }

    public function getMaterialsUsedAttribute(): ?string
    {
        return $this->resolution?->materials_used;
    }

    public function getTestResultAttribute(): ?string
    {
        return $this->resolution?->test_result;
    }

    public function getTestParametersAttribute(): ?string
    {
        return $this->resolution?->test_parameters;
    }

    public function getCategoryIdAttribute(): ?int
    {
        return $this->resolution?->category_id;
    }

    public function getCategoryAttribute(): ?TicketCategory
    {
        return $this->resolution?->category;
    }

    public function getInfrastructureTypeAttribute(): ?string
    {
        return $this->resolution?->category?->infrastructure_type;
    }

    public function getRatingAttribute(): ?int
    {
        return $this->feedback?->rating;
    }

    public function getFeedbackCommentAttribute(): ?string
    {
        return $this->feedback?->feedback_comment;
    }

    public function getRatedAtAttribute(): ?Carbon
    {
        $time = $this->feedback?->rated_at ?? $this->feedback?->created_at;
        return $time ? Carbon::parse($time) : null;
    }

    // =========================================================================
    // VIRTUAL TIMELINE ATTRIBUTES (BACKWARD COMPATIBILITY & TESTING)
    // =========================================================================

    protected ?Carbon $virtualCancelledAt = null;
    protected ?Carbon $virtualClosedAt = null;
    protected ?Carbon $virtualResolvedAt = null;
    protected ?Carbon $virtualAssignedAt = null;

    public function setCancelledAtAttribute($value): void
    {
        $this->virtualCancelledAt = $value ? Carbon::parse($value) : null;
    }

    public function setClosedAtAttribute($value): void
    {
        $this->virtualClosedAt = $value ? Carbon::parse($value) : null;
    }

    public function setResolvedAtAttribute($value): void
    {
        $this->virtualResolvedAt = $value ? Carbon::parse($value) : null;
    }

    public function setAssignedAtAttribute($value): void
    {
        $this->virtualAssignedAt = $value ? Carbon::parse($value) : null;
    }

    public function getResolvedAtAttribute($value = null): ?Carbon
    {
        if ($this->virtualResolvedAt) {
            return $this->virtualResolvedAt;
        }
        if ($value) {
            return Carbon::parse($value);
        }
        return $this->resolution?->created_at;
    }

    public function getClosedAtAttribute($value = null): ?Carbon
    {
        if ($this->virtualClosedAt) {
            return $this->virtualClosedAt;
        }
        if ($value) {
            return Carbon::parse($value);
        }
        if ($this->relationLoaded('statusHistories')) {
            $history = $this->statusHistories->where('new_status', 'closed')->sortByDesc('created_at')->first();
            return $history ? Carbon::parse($history->created_at) : null;
        }
        $time = $this->statusHistories()->where('new_status', 'closed')->latest()->value('created_at');
        return $time ? Carbon::parse($time) : null;
    }

    public function getAssignedAtAttribute($value = null): ?Carbon
    {
        if ($this->virtualAssignedAt) {
            return $this->virtualAssignedAt;
        }
        if ($value) {
            return Carbon::parse($value);
        }
        if ($this->relationLoaded('statusHistories')) {
            $history = $this->statusHistories->where('new_status', 'in_progress')->sortBy('created_at')->first();
            return $history ? Carbon::parse($history->created_at) : null;
        }
        $time = $this->statusHistories()->where('new_status', 'in_progress')->oldest()->value('created_at');
        return $time ? Carbon::parse($time) : null;
    }

    public function getCancelledAtAttribute($value = null): ?Carbon
    {
        if ($this->virtualCancelledAt) {
            return $this->virtualCancelledAt;
        }
        if ($value) {
            return Carbon::parse($value);
        }
        if ($this->relationLoaded('statusHistories')) {
            $history = $this->statusHistories->where('new_status', 'cancelled')->sortByDesc('created_at')->first();
            return $history ? Carbon::parse($history->created_at) : null;
        }
        $time = $this->statusHistories()->where('new_status', 'cancelled')->latest()->value('created_at');
        return $time ? Carbon::parse($time) : null;
    }

    // =========================================================================
    // HOLD & SLA ACCESSORS
    // =========================================================================

    public function getHoldReasonCategoryAttribute(): ?string
    {
        return $this->latestHold?->reason_category;
    }

    public function getHoldReasonNoteAttribute(): ?string
    {
        return $this->latestHold?->reason_note;
    }

    public function getHoldStartedAtAttribute()
    {
        if ($this->relationLoaded('latestHold') && $this->latestHold && !$this->latestHold->ended_at) {
            return $this->latestHold->started_at;
        }
        if ($this->relationLoaded('holds')) {
            return $this->holds->whereNull('ended_at')->sortByDesc('started_at')->first()?->started_at;
        }
        return $this->holds()->whereNull('ended_at')->latest()->value('started_at');
    }

    public function getTotalHoldDurationMinutesAttribute(): int
    {
        if ($this->relationLoaded('holds')) {
            return (int) $this->holds->sum('duration_minutes');
        }
        return (int) $this->holds()->sum('duration_minutes');
    }
}
