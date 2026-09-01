<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $role = $user->role;

        $stats = [];
        $monthlyReports = [];
        $monthlySummary = null;
        $availableYears = [];
        $selectedYear = null;

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
        } 
        elseif ($role === 'admin') {
            // List available years from tickets
            $availableYears = Ticket::selectRaw('DISTINCT YEAR(created_at) as yr')
                ->whereNotNull('created_at')
                ->orderBy('yr', 'desc')
                ->pluck('yr')
                ->map(fn($y) => (string) $y)
                ->toArray();

            if (empty($availableYears)) {
                $availableYears = [(string) date('Y')];
            }

            // Year selection: user query param or latest year
            $selectedYear = $request->query('year', $availableYears[0]);
            if (!in_array($selectedYear, $availableYears) && $selectedYear !== 'all') {
                $selectedYear = $availableYears[0];
            }

            $statsQuery = Ticket::query();
            if ($selectedYear !== 'all') {
                $statsQuery->whereYear('created_at', $selectedYear);
            }

            $stats = [
                'total_tickets' => (clone $statsQuery)->count(),
                'pending_admin' => (clone $statsQuery)->where('status', 'pending_admin')->count(),
                'in_progress' => (clone $statsQuery)->where('status', 'in_progress')->count(),
                'pending_approval' => (clone $statsQuery)->where('status', 'pending_approval')->count(),
                'closed_tickets' => (clone $statsQuery)->where('status', 'closed')->count(),
                'rejected_tickets' => (clone $statsQuery)->where('status', 'cancelled')->count(),
            ];

            // Chart 1: Status Distribution
            $statusDistribution = [
                'labels' => [
                    'Selesai',
                    'Dalam Penanganan',
                    'Menunggu Verifikasi',
                    'Menunggu Approval',
                    'Ditolak',
                ],
                'data' => [
                    $stats['closed_tickets'],
                    $stats['in_progress'],
                    $stats['pending_admin'],
                    $stats['pending_approval'],
                    $stats['rejected_tickets'],
                ],
                'colors' => [
                    '#10b981', // Emerald
                    '#f59e0b', // Amber
                    '#3b82f6', // Blue
                    '#8b5cf6', // Purple
                    '#f43f5e', // Rose
                ],
            ];

            // Chart 2: Network Type Distribution
            $fiberCount = (clone $statsQuery)->where('network_type', 'fiber_optic')->count();
            $lanCount = (clone $statsQuery)->where('network_type', 'lan')->count();
            $wifiCount = (clone $statsQuery)->where('network_type', 'wifi')->count();

            $networkTypeDistribution = [
                'labels' => ['FO', 'LAN', 'WiFi'],
                'data' => [$fiberCount, $lanCount, $wifiCount],
                'colors' => ['#0284c7', '#6366f1', '#0d9488'], // Sky, Indigo, Teal
            ];

            // Chart 3: Priority Distribution
            $lowCount = (clone $statsQuery)->where('priority', 'low')->count();
            $mediumCount = (clone $statsQuery)->where('priority', 'medium')->count();
            $highCount = (clone $statsQuery)->where('priority', 'high')->count();
            $emergencyCount = (clone $statsQuery)->where('priority', 'emergency')->count();

            $priorityDistribution = [
                'labels' => ['Low', 'Med', 'High', 'Emerg'],
                'data' => [$lowCount, $mediumCount, $highCount, $emergencyCount],
                'colors' => ['#64748b', '#3b82f6', '#f59e0b', '#ef4444'], // Slate, Blue, Amber, Red
            ];

            $monthNamesIndo = [
                '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
                '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
                '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
            ];

            $formatDuration = function ($minutes) {
                if (!$minutes || $minutes <= 0) {
                    return '-';
                }
                $totalMinutes = (int) round($minutes);
                $hours = intdiv($totalMinutes, 60);
                $mins = $totalMinutes % 60;
                
                if ($hours > 0 && $mins > 0) {
                    return "{$hours} jam {$mins} menit";
                } elseif ($hours > 0) {
                    return "{$hours} jam";
                }
                return "{$mins} menit";
            };

            $query = Ticket::selectRaw("
                DATE_FORMAT(created_at, '%Y-%m') as period,
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status IN ('in_progress', 'pending_approval') THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                AVG(CASE WHEN status = 'closed' AND closed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, closed_at) ELSE NULL END) as avg_resolution_minutes
            ");

            if ($selectedYear !== 'all') {
                $query->whereYear('created_at', $selectedYear);
            }

            $rawReports = $query->groupBy('period')
                ->orderBy('period', 'asc')
                ->get();

            $monthlyReports = $rawReports->map(function ($row) use ($monthNamesIndo, $formatDuration) {
                [$year, $m] = explode('-', $row->period);
                $monthName = ($monthNamesIndo[$m] ?? $m) . ' ' . $year;
                $total = (int) $row->total_tickets;
                $closed = (int) $row->closed;
                $completionRate = $total > 0 ? round(($closed / $total) * 100, 2) : 0;

                return [
                    'period' => $row->period,
                    'month_name' => $monthName,
                    'total_tickets' => $total,
                    'in_progress' => (int) $row->in_progress,
                    'closed' => $closed,
                    'cancelled' => (int) $row->cancelled,
                    'avg_resolution_time' => $formatDuration($row->avg_resolution_minutes),
                    'completion_rate' => $completionRate,
                ];
            })->values()->toArray();

            // Total Summary calculation
            $totalTickets = array_sum(array_column($monthlyReports, 'total_tickets'));
            $totalInProgress = array_sum(array_column($monthlyReports, 'in_progress'));
            $totalClosed = array_sum(array_column($monthlyReports, 'closed'));
            $totalCancelled = array_sum(array_column($monthlyReports, 'cancelled'));
            $totalCompletionRate = $totalTickets > 0 ? round(($totalClosed / $totalTickets) * 100, 2) : 0;

            $overallAvgQuery = Ticket::where('status', 'closed')->whereNotNull('closed_at');
            if ($selectedYear !== 'all') {
                $overallAvgQuery->whereYear('created_at', $selectedYear);
            }
            $overallAvgMinutes = $overallAvgQuery->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, closed_at)) as avg_min')->value('avg_min');

            $monthlySummary = [
                'month_name' => 'TOTAL',
                'total_tickets' => $totalTickets,
                'in_progress' => $totalInProgress,
                'closed' => $totalClosed,
                'cancelled' => $totalCancelled,
                'avg_resolution_time' => $formatDuration($overallAvgMinutes),
                'completion_rate' => $totalCompletionRate,
            ];

            // Chart 4: Ticket Trend (Line Chart)
            $shortMonths = [
                '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
                '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
                '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
            ];

            $trendLabels = [];
            $trendCreated = [];
            $trendClosed = [];

            if ($selectedYear !== 'all') {
                $indexedReports = $rawReports->keyBy('period');
                foreach ($shortMonths as $mNum => $mLabel) {
                    $periodKey = "{$selectedYear}-{$mNum}";
                    $trendLabels[] = $mLabel;
                    $trendCreated[] = isset($indexedReports[$periodKey]) ? (int) $indexedReports[$periodKey]->total_tickets : 0;
                    $trendClosed[] = isset($indexedReports[$periodKey]) ? (int) $indexedReports[$periodKey]->closed : 0;
                }
            } else {
                foreach ($monthlyReports as $rep) {
                    [$y, $m] = explode('-', $rep['period']);
                    $shortYear = substr($y, -2);
                    $trendLabels[] = ($shortMonths[$m] ?? $m) . " '" . $shortYear;
                    $trendCreated[] = $rep['total_tickets'];
                    $trendClosed[] = $rep['closed'];
                }
            }

            $ticketTrend = [
                'labels' => $trendLabels,
                'created' => $trendCreated,
                'closed' => $trendClosed,
            ];
        }

        // Recent Tickets (Top 5)
        $recentTicketsQuery = Ticket::with(['department:id,name,code', 'category:id,name', 'reporter:id,name']);

        if ($role === 'opd_user') {
            $recentTicketsQuery->where('department_id', $user->department_id);
        } elseif ($role === 'technician') {
            $myTicketsQuery = function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('technicians', fn($qt) => $qt->where('users.id', $user->id));
            };
            $recentTicketsQuery->where($myTicketsQuery);
        }

        $recentTickets = $recentTicketsQuery->latest()
            ->limit(5)
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'title' => $ticket->title,
                    'department_name' => $ticket->department ? $ticket->department->name : '-',
                    'department_code' => $ticket->department ? $ticket->department->code : '-',
                    'reporter_name' => $ticket->reporter ? $ticket->reporter->name : '-',
                    'category_name' => $ticket->category ? $ticket->category->name : '-',
                    'network_type' => $ticket->network_type,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at ? $ticket->created_at->format('d M Y, H:i') : '-',
                    'created_at_diff' => $ticket->created_at ? $ticket->created_at->diffForHumans() : '-',
                ];
            });

        // Recent Activities (Top 6 latest status/action updates)
        $recentActivitiesQuery = TicketStatusHistory::with([
            'ticket:id,ticket_number,title,department_id',
            'ticket.department:id,name,code',
            'changer:id,name,role',
        ]);

        if ($role === 'opd_user') {
            $recentActivitiesQuery->whereHas('ticket', function ($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        } elseif ($role === 'technician') {
            $recentActivitiesQuery->whereHas('ticket', function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('technicians', fn($qt) => $qt->where('users.id', $user->id));
            });
        }

        $recentActivities = $recentActivitiesQuery->latest('id')
            ->limit(6)
            ->get()
            ->map(function ($history) {
                return [
                    'id' => $history->id,
                    'ticket_id' => $history->ticket_id,
                    'ticket_number' => $history->ticket ? $history->ticket->ticket_number : '-',
                    'ticket_title' => $history->ticket ? $history->ticket->title : '-',
                    'department_name' => $history->ticket && $history->ticket->department ? $history->ticket->department->name : '-',
                    'user_name' => $history->changer ? $history->changer->name : 'Sistem',
                    'user_role' => $history->changer ? $history->changer->role : 'system',
                    'previous_status' => $history->previous_status,
                    'new_status' => $history->new_status,
                    'comment' => $history->comment,
                    'created_at' => $history->created_at ? $history->created_at->format('d M Y, H:i') : '-',
                    'created_at_diff' => $history->created_at ? $history->created_at->diffForHumans() : '-',
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'monthlyReports' => $monthlyReports,
            'monthlySummary' => $monthlySummary,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'statusDistribution' => $statusDistribution ?? null,
            'networkTypeDistribution' => $networkTypeDistribution ?? null,
            'priorityDistribution' => $priorityDistribution ?? null,
            'ticketTrend' => $ticketTrend ?? null,
            'recentTickets' => $recentTickets,
            'recentActivities' => $recentActivities,
        ]);
    }
}
