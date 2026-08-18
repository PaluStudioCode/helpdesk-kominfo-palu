<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Log user or system operational activity.
     */
    public static function log(string $action, ?Model $subject = null, ?array $metadata = null, ?int $userId = null): ActivityLog
    {
        $resolvedUserId = $userId ?? auth()->id();

        return ActivityLog::create([
            'user_id' => $resolvedUserId,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->getKey() : null,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
