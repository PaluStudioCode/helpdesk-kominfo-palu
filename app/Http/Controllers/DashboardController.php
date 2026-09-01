<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $role = $user->role;

        $stats = [];
        $recentTickets = [];

        if ($role === 'opd_user') {
            $departmentId = $user->department_id;

            $stats = [
                'in_process' => Ticket::where('department_id', $departmentId)
                    ->whereIn('status', ['pending_admin', 'in_progress', 'pending_approval'])
                    ->count(),
                'closed_tickets' => Ticket::where('department_id', $departmentId)
                    ->where('status', 'closed')
                    ->count(),
                'needs_fix' => Ticket::where('department_id', $departmentId)
                    ->where('status', 'cancelled')
                    ->whereRaw('TIMESTAMPDIFF(HOUR, COALESCE(cancelled_at, updated_at), NOW()) < 72')
                    ->count(),
                'total_reports' => Ticket::where('department_id', $departmentId)->count(),
            ];

            $recentTickets = Ticket::where('department_id', $departmentId)
                ->with(['category:id,name', 'technicians:id,name', 'assignee:id,name'])
                ->latest()
                ->take(5)
                ->get();
        } 
        elseif ($role === 'technician') {
            $myTicketsQuery = function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('technicians', fn($qt) => $qt->where('users.id', $user->id));
            };

            $stats = [
                'my_team_tickets' => Ticket::where('status', 'in_progress')
                    ->where($myTicketsQuery)
                    ->count(),
                'pending_approval' => Ticket::where('status', 'pending_approval')
                    ->where($myTicketsQuery)
                    ->count(),
                'resolved_this_month' => Ticket::where('status', 'closed')
                    ->where($myTicketsQuery)
                    ->whereMonth('closed_at', now()->month)
                    ->count(),
            ];

            $recentTickets = Ticket::whereIn('status', ['in_progress', 'pending_approval'])
                ->where($myTicketsQuery)
                ->with(['department:id,name', 'category:id,name', 'technicians:id,name', 'assignee:id,name'])
                ->orderByRaw("CASE WHEN status = 'in_progress' THEN 1 WHEN status = 'pending_approval' THEN 2 ELSE 3 END")
                ->latest()
                ->take(5)
                ->get();
        } 
        elseif ($role === 'admin') {
            $stats = [
                'pending_admin' => Ticket::where('status', 'pending_admin')->count(),
                'pending_approval' => Ticket::where('status', 'pending_approval')->count(),
                'in_progress' => Ticket::where('status', 'in_progress')->count(),
                'closed_tickets' => Ticket::where('status', 'closed')->count(),
                'avg_csat' => round((float) Ticket::whereNotNull('rating')->avg('rating'), 1) ?: 0.0,
                'fiber_optic' => Ticket::where('network_type', 'fiber_optic')->count(),
                'lan' => Ticket::where('network_type', 'lan')->count(),
                'wifi' => Ticket::where('network_type', 'wifi')->count(),
                'total_departments' => Department::count(),
            ];

            $recentTickets = Ticket::with(['department:id,name', 'category:id,name', 'technicians:id,name', 'assignee:id,name'])
                ->latest()
                ->take(5)
                ->get();
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentTickets' => $recentTickets,
        ]);
    }
}
