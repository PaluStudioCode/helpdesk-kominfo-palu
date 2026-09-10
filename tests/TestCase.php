<?php

namespace Tests;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Helper to create an Admin user.
     */
    protected function createAdmin(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Admin Testing',
            'email' => 'admin_' . uniqid() . '@palukota.go.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone_number' => '081234567890',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Helper to create a Technician user.
     */
    protected function createTechnician(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Teknisi Testing',
            'email' => 'teknisi_' . uniqid() . '@palukota.go.id',
            'password' => Hash::make('password'),
            'role' => 'technician',
            'phone_number' => '081234567891',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Helper to create an OPD Department.
     */
    protected function createDepartment(array $attributes = []): Department
    {
        $code = 'D' . rand(1000, 9999) . rand(100, 999);
        return Department::create(array_merge([
            'code' => substr($code, 0, 10),
            'name' => 'Dinas Testing ' . uniqid(),
            'address' => 'Jl. Balai Kota No. 1 Palu',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Helper to create an OPD User.
     */
    protected function createOpdUser(?Department $department = null, array $attributes = []): User
    {
        $dept = $department ?? $this->createDepartment();

        return User::create(array_merge([
            'name' => 'Operator OPD Testing',
            'email' => 'opd_' . uniqid() . '@palukota.go.id',
            'password' => Hash::make('password'),
            'role' => 'opd_user',
            'department_id' => $dept->id,
            'phone_number' => '081234567892',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Helper to create a Ticket Category.
     */
    protected function createCategory(array $attributes = []): TicketCategory
    {
        $infraType = $attributes['infrastructure_type'] ?? $attributes['network_type'] ?? 'Fiber optic';
        unset($attributes['network_type']);

        return TicketCategory::create(array_merge([
            'name' => 'Kategori Test ' . uniqid(),
            'infrastructure_type' => $infraType,
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Helper to create a Ticket.
     */
    protected function createTicket(array $attributes = []): Ticket
    {
        $department = $attributes['department_id'] ?? $this->createDepartment()->id;
        $reporter = $attributes['reporter_id'] ?? $this->createOpdUser(Department::find($department))->id;

        $categoryId = $attributes['category_id'] ?? null;
        $cancelledAt = $attributes['cancelled_at'] ?? null;
        $closedAt = $attributes['closed_at'] ?? null;
        $assignedAt = $attributes['assigned_at'] ?? null;
        $holdReasonCategory = $attributes['hold_reason_category'] ?? null;
        $holdReasonNote = $attributes['hold_reason_note'] ?? null;
        $holdStartedAt = $attributes['hold_started_at'] ?? null;

        $rating = $attributes['rating'] ?? null;
        $feedbackComment = $attributes['feedback_comment'] ?? null;
        $ratedAt = $attributes['rated_at'] ?? null;

        unset(
            $attributes['category_id'],
            $attributes['infrastructure_type'],
            $attributes['network_type'],
            $attributes['assigned_at'],
            $attributes['cancelled_at'],
            $attributes['closed_at'],
            $attributes['hold_reason_category'],
            $attributes['hold_reason_note'],
            $attributes['hold_started_at'],
            $attributes['total_hold_duration_minutes'],
            $attributes['rating'],
            $attributes['feedback_comment'],
            $attributes['rated_at']
        );

        $ticket = Ticket::create(array_merge([
            'ticket_number' => 'TKT-' . date('Ymd') . '-' . rand(1000, 9999) . '-' . substr(uniqid(), -4),
            'department_id' => $department,
            'reporter_id' => $reporter,
            'assigned_to' => null,
            'title' => 'Laporan Gangguan Testing',
            'location_details' => 'Ruang Server Lt 2',
            'description' => 'Deskripsi gangguan testing.',
            'priority' => null,
            'status' => 'pending_admin',
            'due_at' => null,
        ], $attributes));

        if ($categoryId) {
            $ticket->resolution()->create([
                'category_id' => $categoryId,
                'resolved_by' => $ticket->assigned_to ?? $reporter,
                'action_taken' => 'Tindakan perbaikan testing.',
            ]);
        }

        if ($rating) {
            $ticket->feedback()->create([
                'rating' => $rating,
                'feedback_comment' => $feedbackComment ?? 'Feedback testing.',
                'rated_by' => $reporter,
                'created_at' => $ratedAt ?? now(),
            ]);
        }

        if ($ticket->status === 'on_hold' || $holdReasonCategory || $holdStartedAt) {
            $ticket->holds()->create([
                'user_id' => $ticket->assigned_to ?? $reporter,
                'reason_category' => $holdReasonCategory ?? 'other',
                'reason_note' => $holdReasonNote ?? 'Penundaan pada sesi testing.',
                'started_at' => $holdStartedAt ?? now(),
                'ended_at' => null,
                'duration_minutes' => 0,
            ]);
        }

        if ($cancelledAt || $ticket->status === 'cancelled') {
            $ticket->statusHistories()->create([
                'changed_by' => $reporter,
                'previous_status' => 'pending_admin',
                'new_status' => 'cancelled',
                'comment' => 'Dibatalkan pada sesi testing.',
                'created_at' => $cancelledAt ?? now(),
            ]);
        }

        if ($closedAt || $ticket->status === 'closed') {
            $ticket->statusHistories()->create([
                'changed_by' => $reporter,
                'previous_status' => 'pending_approval',
                'new_status' => 'closed',
                'comment' => 'Ditutup pada sesi testing.',
                'created_at' => $closedAt ?? now(),
            ]);
        }

        return $ticket;
    }
}
