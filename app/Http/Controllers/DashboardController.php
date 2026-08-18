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
                'active_tickets' => Ticket::where('department_id', $departmentId)
                    ->whereIn('status', ['open', 'in_progress'])->count(),
                'resolved_tickets' => Ticket::where('department_id', $departmentId)
                    ->where('status', 'resolved')->count(),
                'total_tickets' => Ticket::where('department_id', $departmentId)->count(),
            ];

            $recentTickets = Ticket::where('department_id', $departmentId)
                ->with('category:id,name')
                ->latest()
                ->take(5)
                ->get();
        } 
        elseif ($role === 'technician') {
            $stats = [
                'open_tickets' => Ticket::where('status', 'open')->count(),
                'my_progress' => Ticket::where('assigned_to', $user->id)
                    ->where('status', 'in_progress')->count(),
                'resolved_today' => Ticket::where('assigned_to', $user->id)
                    ->where('status', 'resolved')
                    ->whereDate('resolved_at', today())->count(),
            ];

            $recentTickets = Ticket::whereIn('status', ['open', 'in_progress'])
                ->where(function($query) use ($user) {
                    $query->whereNull('assigned_to')
                          ->orWhere('assigned_to', $user->id);
                })
                ->with(['department:id,name', 'category:id,name'])
                ->orderByRaw("FIELD(status, 'open', 'in_progress')")
                ->latest()
                ->take(5)
                ->get();
        } 
        elseif ($role === 'admin') {
            $stats = [
                'total_active' => Ticket::whereIn('status', ['open', 'in_progress'])->count(),
                'total_resolved' => Ticket::whereIn('status', ['resolved', 'closed'])->count(),
                'fiber_optic' => Ticket::where('network_type', 'fiber_optic')->count(),
                'lan' => Ticket::where('network_type', 'lan')->count(),
                'wifi' => Ticket::where('network_type', 'wifi')->count(),
                'total_departments' => Department::count(),
            ];

            $recentTickets = Ticket::with(['department:id,name', 'category:id,name', 'assignee:id,name'])
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
