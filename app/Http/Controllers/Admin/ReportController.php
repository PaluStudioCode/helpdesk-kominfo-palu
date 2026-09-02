<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SimpleExcelExport;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * Display reports filter and review table.
     */
    public function index(Request $request)
    {
        $query = $this->buildFilteredQuery($request);

        $tickets = $query->paginate(15)->withQueryString();

        $departments = Department::select('id', 'name', 'code')->orderBy('name')->get();
        $technicians = User::where('role', 'technician')->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/Reports/Index', [
            'tickets' => $tickets,
            'departments' => $departments,
            'technicians' => $technicians,
            'filters' => $request->only(['start_date', 'end_date', 'department_id', 'network_type', 'status', 'assigned_to']),
        ]);
    }

    /**
     * Export executive reports to PDF format.
     */
    public function exportPdf(Request $request)
    {
        $tickets = $this->buildFilteredQuery($request)->get();
        $totalTickets = $tickets->count();

        $resolvedTickets = $tickets->whereIn('status', ['resolved', 'closed'])->count();
        $inProgressTickets = $tickets->whereIn('status', ['in_progress', 'pending_approval', 'pending_admin'])->count();
        $cancelledTickets = $tickets->where('status', 'cancelled')->count();
        $resolutionRate = $totalTickets > 0 ? round(($resolvedTickets / $totalTickets) * 100, 1) : 0;

        // SLA, Durasi & CSAT
        $slaCompliantCount = 0;
        $completedTicketsWithSla = 0;
        $totalDurationMinutes = 0;
        $completedCount = 0;
        $csatSum = 0;
        $csatCount = 0;

        foreach ($tickets as $ticket) {
            $endTime = $ticket->resolved_at ?? $ticket->closed_at;
            $startPoint = $ticket->assigned_at ?? $ticket->created_at;

            if (in_array($ticket->status, ['resolved', 'closed']) && $endTime && $startPoint) {
                $completedCount++;
                $diffMins = max(0, Carbon::parse($startPoint)->diffInMinutes(Carbon::parse($endTime)));
                $totalDurationMinutes += $diffMins;

                if ($ticket->due_at) {
                    $completedTicketsWithSla++;
                    if (Carbon::parse($endTime)->lte(Carbon::parse($ticket->due_at))) {
                        $slaCompliantCount++;
                    }
                }
            }

            if ($ticket->rating) {
                $csatSum += $ticket->rating;
                $csatCount++;
            }
        }

        $slaPercentage = $completedTicketsWithSla > 0 ? round(($slaCompliantCount / $completedTicketsWithSla) * 100, 1) : 100;
        $avgMinutes = $completedCount > 0 ? round($totalDurationMinutes / $completedCount) : 0;
        $avgHours = floor($avgMinutes / 60);
        $avgRemainingMins = $avgMinutes % 60;
        $avgDurationText = $avgHours > 0 ? "{$avgHours}j {$avgRemainingMins}m" : "{$avgRemainingMins}m";
        $avgCsat = $csatCount > 0 ? round($csatSum / $csatCount, 1) : 0;

        // Formal Monochrome Network Distribution
        $networkStats = [
            'fiber_optic' => [
                'label' => 'Fiber Optic (FO)', 
                'count' => $tickets->where('network_type', 'fiber_optic')->count(), 
                'color' => '#1e293b'
            ],
            'lan' => [
                'label' => 'Jaringan LAN', 
                'count' => $tickets->where('network_type', 'lan')->count(), 
                'color' => '#475569'
            ],
            'wifi' => [
                'label' => 'WiFi / Nirkabel', 
                'count' => $tickets->where('network_type', 'wifi')->count(), 
                'color' => '#94a3b8'
            ],
        ];

        foreach ($networkStats as $k => $v) {
            $networkStats[$k]['percentage'] = $totalTickets > 0 ? round(($v['count'] / $totalTickets) * 100, 1) : 0;
        }

        // Department Breakdown (Rekapitulasi per OPD)
        $departmentBreakdown = $tickets->groupBy('department_id')->map(function ($deptTickets) {
            $deptName = $deptTickets->first()->department?->name ?? 'Tanpa OPD';
            $total = $deptTickets->count();
            $resolved = $deptTickets->whereIn('status', ['resolved', 'closed'])->count();
            
            $slaCount = 0;
            $slaBase = 0;
            foreach ($deptTickets as $t) {
                $endTime = $t->resolved_at ?? $t->closed_at;
                if (in_array($t->status, ['resolved', 'closed']) && $endTime && $t->due_at) {
                    $slaBase++;
                    if (Carbon::parse($endTime)->lte(Carbon::parse($t->due_at))) {
                        $slaCount++;
                    }
                }
            }
            $slaRate = $slaBase > 0 ? round(($slaCount / $slaBase) * 100) : 100;

            return [
                'name' => $deptName,
                'total' => $total,
                'resolved' => $resolved,
                'in_progress' => $total - $resolved,
                'sla_rate' => $slaRate,
            ];
        })->sortByDesc('total')->values();

        // Top 5 Issue Categories
        $topCategories = $tickets->groupBy('category_id')->filter(fn($grp) => $grp->first()->category_id !== null)->map(function ($catTickets) use ($totalTickets) {
            $categoryName = $catTickets->first()->category?->name ?? '-';
            $count = $catTickets->count();
            $percentage = $totalTickets > 0 ? round(($count / $totalTickets) * 100, 1) : 0;
            return [
                'name' => $categoryName,
                'count' => $count,
                'percentage' => $percentage,
            ];
        })->sortByDesc('count')->take(5)->values();

        $pdf = Pdf::loadView('reports.tickets-pdf', [
            'totalTickets' => $totalTickets,
            'resolvedTickets' => $resolvedTickets,
            'inProgressTickets' => $inProgressTickets,
            'cancelledTickets' => $cancelledTickets,
            'resolutionRate' => $resolutionRate,
            'slaPercentage' => $slaPercentage,
            'avgDurationText' => $avgDurationText,
            'avgCsat' => $avgCsat,
            'csatCount' => $csatCount,
            'networkStats' => $networkStats,
            'departmentBreakdown' => $departmentBreakdown,
            'topCategories' => $topCategories,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ])->setPaper('a4', 'portrait');

        $fileName = 'Laporan-Eksekutif-Helpdesk-' . date('Ymd-His') . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * Export reports to Excel format (.xlsx).
     */
    public function exportExcel(Request $request)
    {
        $tickets = $this->buildFilteredQuery($request)->get();
        $fileName = 'Laporan-Rekapitulasi-Helpdesk-' . date('Ymd-His') . '.xlsx';

        return SimpleExcelExport::download(
            $tickets, 
            $fileName,
            $request->input('start_date'),
            $request->input('end_date')
        );
    }

    /**
     * Build filtered Ticket query based on request parameters.
     */
    protected function buildFilteredQuery(Request $request)
    {
        $query = Ticket::with([
            'department:id,name', 
            'category:id,name', 
            'assignee:id,name', 
            'technicians:id,name',
            'reporter:id,name'
        ]);

        // Filter date range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        // Filter department
        if ($request->filled('department_id') && $request->input('department_id') !== 'all') {
            $query->where('department_id', $request->input('department_id'));
        }

        // Filter network type
        if ($request->filled('network_type') && $request->input('network_type') !== 'all') {
            $query->where('network_type', $request->input('network_type'));
        }

        // Filter status
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Filter technician (lead or in team pivot)
        if ($request->filled('assigned_to') && $request->input('assigned_to') !== 'all') {
            $techId = $request->input('assigned_to');
            $query->where(function($q) use ($techId) {
                $q->where('assigned_to', $techId)
                  ->orWhereHas('technicians', fn($qt) => $qt->where('users.id', $techId));
            });
        }

        return $query->latest('created_at');
    }
}
