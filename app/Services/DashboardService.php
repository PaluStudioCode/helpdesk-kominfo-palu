<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get aggregated dashboard payload based on user role and filters.
     */
    public function getDashboardData(Request $request, User $user): array
    {
        $role = $user->role;

        $stats = [];
        $monthlyReports = [];
        $monthlySummary = null;
        $availableYears = [];
        $filterType = 'year_month';
        $selectedYear = (string) date('Y');
        $selectedMonth = 'all';
        $selectedPreset = null;
        $filterStart = null;
        $filterEnd = null;
        $statusDistribution = null;
        $infrastructureDistribution = null;
        $networkTypeDistribution = null;
        $priorityDistribution = null;
        $ticketTrend = null;
        $activeTasks = [];
        $recentFeedbacks = [];
        $technicianResolutionChart = null;

        if ($role === 'opd_user') {
            $stats = $this->getOpdStats((int) $user->department_id);
        } elseif ($role === 'technician') {
            $techData = $this->getTechnicianData($request, $user);
            $stats = $techData['stats'];
            $activeTasks = $techData['activeTasks'];
            $recentFeedbacks = $techData['recentFeedbacks'];
            $technicianResolutionChart = $techData['technicianResolutionChart'];
        } elseif ($role === 'admin') {
            $adminData = $this->getAdminData($request);
            $stats = $adminData['stats'];
            $availableYears = $adminData['availableYears'];
            $filterType = $adminData['filterType'];
            $selectedYear = $adminData['selectedYear'];
            $selectedMonth = $adminData['selectedMonth'];
            $selectedPreset = $adminData['selectedPreset'];
            $filterStart = $adminData['filterStart'];
            $filterEnd = $adminData['filterEnd'];
            $statusDistribution = $adminData['statusDistribution'];
            $infrastructureDistribution = $adminData['infrastructureDistribution'] ?? $adminData['networkTypeDistribution'];
            $networkTypeDistribution = $infrastructureDistribution;
            $priorityDistribution = $adminData['priorityDistribution'];
            $monthlyReports = $adminData['monthlyReports'];
            $monthlySummary = $adminData['monthlySummary'];
            $ticketTrend = $adminData['ticketTrend'];
        }

        $recentTickets = $this->getRecentTickets($user);
        $recentActivities = $this->getRecentActivities($user);

        return [
            'stats' => $stats,
            'monthlyReports' => $monthlyReports,
            'monthlySummary' => $monthlySummary,
            'availableYears' => $availableYears,
            'filterType' => $filterType,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'selectedPreset' => $selectedPreset,
            'startDate' => $filterStart ? $filterStart->format('Y-m-d') : null,
            'endDate' => $filterEnd ? $filterEnd->format('Y-m-d') : null,
            'statusDistribution' => $statusDistribution,
            'infrastructureDistribution' => $infrastructureDistribution,
            'networkTypeDistribution' => $networkTypeDistribution,
            'priorityDistribution' => $priorityDistribution,
            'ticketTrend' => $ticketTrend,
            'recentTickets' => $recentTickets,
            'recentActivities' => $recentActivities,
            'activeTasks' => $activeTasks,
            'recentFeedbacks' => $recentFeedbacks,
            'technicianResolutionChart' => $technicianResolutionChart,
        ];
    }

    /**
     * OPD User Dashboard Statistics
     */
    protected function getOpdStats(int $departmentId): array
    {
        return [
            'in_process' => Ticket::where('department_id', $departmentId)
                ->whereIn('status', ['pending_admin', 'in_progress', 'pending_approval'])
                ->count(),
            'closed_tickets' => Ticket::where('department_id', $departmentId)
                ->where('status', 'closed')
                ->count(),
            'pending_rating' => Ticket::where('department_id', $departmentId)
                ->where('status', 'closed')
                ->whereNull('rating')
                ->count(),
            'total_reports' => Ticket::where('department_id', $departmentId)->count(),
        ];
    }

    /**
     * Technician Dashboard Metrics and Tasks
     */
    protected function getTechnicianData(Request $request, User $user): array
    {
        $myTicketsQuery = function ($q) use ($user) {
            $q->where('assigned_to', $user->id)
              ->orWhereHas('technicians', fn ($qt) => $qt->where('users.id', $user->id));
        };

        $ratedTicketsQuery = Ticket::whereNotNull('rating')->where($myTicketsQuery);
        $ratingCount = (clone $ratedTicketsQuery)->count();
        $avgRatingVal = (clone $ratedTicketsQuery)->avg('rating');
        $avgRating = $ratingCount > 0 ? round($avgRatingVal, 1) : 0;

        $stats = [
            'closed_tickets' => Ticket::where('status', 'closed')->where($myTicketsQuery)->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->where($myTicketsQuery)->count(),
            'pending_approval' => Ticket::where('status', 'pending_approval')->where($myTicketsQuery)->count(),
            'avg_rating' => $avgRating,
            'rating_count' => $ratingCount,
        ];

        // 1. Active In-Progress Tasks
        $activeTasks = Ticket::with(['department:id,name,code', 'category:id,name'])
            ->where('status', 'in_progress')
            ->where($myTicketsQuery)
            ->orderByRaw("FIELD(priority, 'emergency', 'high', 'medium', 'low') ASC")
            ->orderBy('due_at', 'asc')
            ->limit(6)
            ->get()
            ->map(function ($t) {
                $dueTime = $t->due_at ? Carbon::parse($t->due_at) : null;
                $isOverdue = $dueTime ? now()->gt($dueTime) : false;
                
                $dueHuman = '-';
                if ($dueTime) {
                    $dueHuman = $isOverdue
                        ? 'Lewat ' . $dueTime->diffForHumans(null, true)
                        : 'Sisa ' . now()->diffForHumans($dueTime, true);
                }

                return [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number,
                    'title' => $t->title,
                    'department_name' => $t->department ? $t->department->name : '-',
                    'department_code' => $t->department ? $t->department->code : '-',
                    'location_details' => $t->location_details ?? '-',
                    'category_name' => $t->category ? $t->category->name : '-',
                    'infrastructure_type' => $t->infrastructure_type,
                    'network_type' => $t->infrastructure_type,
                    'priority' => $t->priority,
                    'due_at' => $t->due_at ? $t->due_at->format('d M Y, H:i') : null,
                    'due_human' => $dueHuman,
                    'is_overdue' => $isOverdue,
                ];
            })
            ->values()
            ->toArray();

        // 2. Recent Feedback Ratings
        $recentFeedbacks = Ticket::with(['department:id,name,code', 'reporter:id,name'])
            ->whereNotNull('rating')
            ->where($myTicketsQuery)
            ->latest('rated_at')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number,
                    'department_name' => $t->department ? $t->department->name : '-',
                    'reporter_name' => $t->reporter ? $t->reporter->name : 'Pelapor OPD',
                    'rating' => (int) $t->rating,
                    'feedback_comment' => $t->feedback_comment,
                    'rated_at' => $t->rated_at ? $t->rated_at->format('d M Y, H:i') : ($t->updated_at ? $t->updated_at->format('d M Y, H:i') : '-'),
                    'rated_at_diff' => $t->rated_at ? $t->rated_at->diffForHumans() : ($t->updated_at ? $t->updated_at->diffForHumans() : '-'),
                ];
            })
            ->values()
            ->toArray();

        // 3. Average Monthly Resolution Time (Hours)
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $targetYear = (int) $request->query('year', date('Y'));
        
        $hasTicketsThisYear = Ticket::whereYear('closed_at', $targetYear)->where($myTicketsQuery)->exists();
        if (!$hasTicketsThisYear) {
            $latestYear = Ticket::whereNotNull('closed_at')->where($myTicketsQuery)->orderBy('closed_at', 'desc')->value(DB::raw('YEAR(closed_at)'));
            if ($latestYear) {
                $targetYear = (int) $latestYear;
            }
        }

        $resolutionTimeData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyClosedQuery = Ticket::where('status', 'closed')
                ->whereYear('closed_at', $targetYear)
                ->whereMonth('closed_at', $m)
                ->where($myTicketsQuery);

            $closedCount = (clone $monthlyClosedQuery)->count();
            
            if ($closedCount > 0) {
                $avgMinutes = (clone $monthlyClosedQuery)
                    ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, closed_at)) as avg_min')
                    ->value('avg_min');
                $avgHours = $avgMinutes ? round($avgMinutes / 60, 1) : 0;
            } else {
                $avgHours = 0;
            }

            $resolutionTimeData[] = [
                'avg_hours' => $avgHours,
                'closed_count' => $closedCount,
            ];
        }

        $technicianResolutionChart = [
            'labels' => $months,
            'data' => array_column($resolutionTimeData, 'avg_hours'),
            'counts' => array_column($resolutionTimeData, 'closed_count'),
            'year' => (string) $targetYear,
        ];

        return [
            'stats' => $stats,
            'activeTasks' => $activeTasks,
            'recentFeedbacks' => $recentFeedbacks,
            'technicianResolutionChart' => $technicianResolutionChart,
        ];
    }

    /**
     * Admin Executive Dashboard Analytics
     */
    protected function getAdminData(Request $request): array
    {
        $availableYears = Ticket::selectRaw('DISTINCT YEAR(created_at) as yr')
            ->whereNotNull('created_at')
            ->orderBy('yr', 'desc')
            ->pluck('yr')
            ->map(fn ($y) => (string) $y)
            ->toArray();

        if (empty($availableYears)) {
            $availableYears = [(string) date('Y')];
        }

        $filterType = $request->query('filter_type', 'year_month');
        $selectedYear = $request->query('year', $availableYears[0]);
        if (!in_array($selectedYear, $availableYears) && $selectedYear !== 'all') {
            $selectedYear = $availableYears[0];
        }

        $selectedMonth = $request->query('month', 'all');
        $selectedPreset = $request->query('preset', null);
        $startDateParam = $request->query('start_date', null);
        $endDateParam = $request->query('end_date', null);

        $filterStart = null;
        $filterEnd = null;

        if ($filterType === 'range') {
            if ($selectedPreset === '7d') {
                $filterStart = now()->subDays(6)->startOfDay();
                $filterEnd = now()->endOfDay();
            } elseif ($selectedPreset === '30d') {
                $filterStart = now()->subDays(29)->startOfDay();
                $filterEnd = now()->endOfDay();
            } elseif ($selectedPreset === 'this_month') {
                $filterStart = now()->startOfMonth();
                $filterEnd = now()->endOfMonth();
            } elseif ($startDateParam && $endDateParam) {
                try {
                    $filterStart = Carbon::parse($startDateParam)->startOfDay();
                    $filterEnd = Carbon::parse($endDateParam)->endOfDay();
                    $selectedPreset = 'custom';
                } catch (\Exception $e) {
                    $filterStart = now()->subDays(29)->startOfDay();
                    $filterEnd = now()->endOfDay();
                    $selectedPreset = '30d';
                }
            } else {
                $filterStart = now()->subDays(29)->startOfDay();
                $filterEnd = now()->endOfDay();
                $selectedPreset = '30d';
            }
        } else {
            $filterType = 'year_month';
            if ($selectedYear !== 'all') {
                if ($selectedMonth !== 'all' && is_numeric($selectedMonth) && (int) $selectedMonth >= 1 && (int) $selectedMonth <= 12) {
                    $selectedMonth = str_pad($selectedMonth, 2, '0', STR_PAD_LEFT);
                    $filterStart = Carbon::create((int) $selectedYear, (int) $selectedMonth, 1)->startOfMonth();
                    $filterEnd = Carbon::create((int) $selectedYear, (int) $selectedMonth, 1)->endOfMonth();
                } else {
                    $selectedMonth = 'all';
                    $filterStart = Carbon::create((int) $selectedYear, 1, 1)->startOfYear();
                    $filterEnd = Carbon::create((int) $selectedYear, 12, 31)->endOfYear();
                }
            } else {
                $selectedMonth = 'all';
                $filterStart = null;
                $filterEnd = null;
            }
        }

        $statsQuery = Ticket::query();
        if ($filterStart && $filterEnd) {
            $statsQuery->whereBetween('created_at', [$filterStart, $filterEnd]);
        }

        $stats = [
            'total_tickets' => (clone $statsQuery)->count(),
            'pending_admin' => (clone $statsQuery)->where('status', 'pending_admin')->count(),
            'in_progress' => (clone $statsQuery)->where('status', 'in_progress')->count(),
            'pending_approval' => (clone $statsQuery)->where('status', 'pending_approval')->count(),
            'closed_tickets' => (clone $statsQuery)->where('status', 'closed')->count(),
            'rejected_tickets' => (clone $statsQuery)->where('status', 'cancelled')->count(),
        ];

        $statusDistribution = [
            'labels' => ['Selesai', 'Dalam Penanganan', 'Menunggu Verifikasi', 'Menunggu Approval', 'Ditolak'],
            'data' => [
                $stats['closed_tickets'],
                $stats['in_progress'],
                $stats['pending_admin'],
                $stats['pending_approval'],
                $stats['rejected_tickets'],
            ],
            'colors' => ['#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#f43f5e'],
        ];

        $fiberCount = (clone $statsQuery)->where('infrastructure_type', 'Fiber optic')->count();
        $deviceCount = (clone $statsQuery)->where('infrastructure_type', 'Perangkat/Akses')->count();
        $powerCount = (clone $statsQuery)->where('infrastructure_type', 'Power/poe')->count();
        $converterCount = (clone $statsQuery)->where('infrastructure_type', 'Converter')->count();
        $serviceCount = (clone $statsQuery)->where('infrastructure_type', 'Layanan/jaringan')->count();

        $infrastructureDistribution = [
            'labels' => ['Fiber optic', 'Perangkat/Akses', 'Power/poe', 'Converter', 'Layanan/jaringan'],
            'data' => [$fiberCount, $deviceCount, $powerCount, $converterCount, $serviceCount],
            'colors' => ['#0284c7', '#6366f1', '#f59e0b', '#ec4899', '#10b981'],
        ];
        $networkTypeDistribution = $infrastructureDistribution;

        $lowCount = (clone $statsQuery)->where('priority', 'low')->count();
        $mediumCount = (clone $statsQuery)->where('priority', 'medium')->count();
        $highCount = (clone $statsQuery)->where('priority', 'high')->count();
        $emergencyCount = (clone $statsQuery)->where('priority', 'emergency')->count();

        $priorityDistribution = [
            'labels' => ['Low', 'Med', 'High', 'Emerg'],
            'data' => [$lowCount, $mediumCount, $highCount, $emergencyCount],
            'colors' => ['#64748b', '#3b82f6', '#f59e0b', '#ef4444'],
        ];

        $monthNamesIndo = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
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

        $tableQuery = Ticket::selectRaw("
            DATE_FORMAT(created_at, '%Y-%m') as period,
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status IN ('pending_admin', 'in_progress', 'pending_approval') THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            AVG(CASE WHEN status = 'closed' AND closed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, closed_at) ELSE NULL END) as avg_resolution_minutes
        ");

        if ($filterStart && $filterEnd) {
            $tableQuery->whereBetween('created_at', [$filterStart, $filterEnd]);
        }

        $rawReports = $tableQuery->groupBy('period')
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

        $totalTickets = array_sum(array_column($monthlyReports, 'total_tickets'));
        $totalInProgress = array_sum(array_column($monthlyReports, 'in_progress'));
        $totalClosed = array_sum(array_column($monthlyReports, 'closed'));
        $totalCancelled = array_sum(array_column($monthlyReports, 'cancelled'));
        $totalCompletionRate = $totalTickets > 0 ? round(($totalClosed / $totalTickets) * 100, 2) : 0;

        $overallAvgQuery = Ticket::where('status', 'closed')->whereNotNull('closed_at');
        if ($filterStart && $filterEnd) {
            $overallAvgQuery->whereBetween('created_at', [$filterStart, $filterEnd]);
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

        $shortMonths = [
            '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
            '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
            '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des',
        ];

        $trendLabels = [];
        $trendCreated = [];
        $trendClosed = [];

        $trendQuery = Ticket::selectRaw("
            DATE_FORMAT(created_at, '%Y-%m') as period,
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
        ");

        if ($selectedYear !== 'all') {
            $trendQuery->whereYear('created_at', $selectedYear);
        }

        $rawTrendReports = $trendQuery->groupBy('period')
            ->orderBy('period', 'asc')
            ->get();

        if ($selectedYear !== 'all') {
            $indexedTrend = $rawTrendReports->keyBy('period');
            foreach ($shortMonths as $mNum => $mLabel) {
                $periodKey = "{$selectedYear}-{$mNum}";
                $trendLabels[] = $mLabel;
                $trendCreated[] = isset($indexedTrend[$periodKey]) ? (int) $indexedTrend[$periodKey]->total_tickets : 0;
                $trendClosed[] = isset($indexedTrend[$periodKey]) ? (int) $indexedTrend[$periodKey]->closed : 0;
            }
        } else {
            foreach ($rawTrendReports as $rep) {
                [$y, $m] = explode('-', $rep->period);
                $shortYear = substr($y, -2);
                $trendLabels[] = ($shortMonths[$m] ?? $m) . " '" . $shortYear;
                $trendCreated[] = (int) $rep->total_tickets;
                $trendClosed[] = (int) $rep->closed;
            }
        }

        $ticketTrend = [
            'labels' => $trendLabels,
            'created' => $trendCreated,
            'closed' => $trendClosed,
        ];

        return [
            'stats' => $stats,
            'availableYears' => $availableYears,
            'filterType' => $filterType,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'selectedPreset' => $selectedPreset,
            'filterStart' => $filterStart,
            'filterEnd' => $filterEnd,
            'statusDistribution' => $statusDistribution,
            'infrastructureDistribution' => $infrastructureDistribution,
            'networkTypeDistribution' => $networkTypeDistribution,
            'priorityDistribution' => $priorityDistribution,
            'monthlyReports' => $monthlyReports,
            'monthlySummary' => $monthlySummary,
            'ticketTrend' => $ticketTrend,
        ];
    }

    /**
     * Recent Tickets Query
     */
    protected function getRecentTickets(User $user)
    {
        $role = $user->role;
        $query = Ticket::with([
            'department:id,name,code',
            'category:id,name',
            'reporter:id,name',
            'assignee:id,name',
            'technicians:id,name',
        ]);

        if ($role === 'opd_user') {
            $query->where('department_id', $user->department_id);
        } elseif ($role === 'technician') {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('technicians', fn ($qt) => $qt->where('users.id', $user->id));
            });
        }

        return $query->latest()
            ->limit(6)
            ->get()
            ->map(function ($ticket) {
                $technicianNames = [];
                if ($ticket->assignee) {
                    $technicianNames[] = $ticket->assignee->name;
                }
                if ($ticket->technicians) {
                    foreach ($ticket->technicians as $tech) {
                        if (!in_array($tech->name, $technicianNames)) {
                            $technicianNames[] = $tech->name;
                        }
                    }
                }

                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'title' => $ticket->title,
                    'department_name' => $ticket->department ? $ticket->department->name : '-',
                    'department_code' => $ticket->department ? $ticket->department->code : '-',
                    'reporter_name' => $ticket->reporter ? $ticket->reporter->name : '-',
                    'category_name' => $ticket->category ? $ticket->category->name : '-',
                    'technicians_label' => !empty($technicianNames) ? implode(', ', $technicianNames) : 'Belum ditugaskan',
                    'infrastructure_type' => $ticket->infrastructure_type,
                    'network_type' => $ticket->infrastructure_type,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at ? $ticket->created_at->format('d M Y, H:i') : '-',
                    'created_at_diff' => $ticket->created_at ? $ticket->created_at->diffForHumans() : '-',
                ];
            });
    }

    /**
     * Recent Status Histories Activity Log
     */
    protected function getRecentActivities(User $user)
    {
        $role = $user->role;
        $query = TicketStatusHistory::with([
            'ticket:id,ticket_number,title,department_id',
            'ticket.department:id,name,code',
            'changer:id,name,role',
        ]);

        if ($role === 'opd_user') {
            $query->whereHas('ticket', fn ($q) => $q->where('department_id', $user->department_id));
        } elseif ($role === 'technician') {
            $query->whereHas('ticket', function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('technicians', fn ($qt) => $qt->where('users.id', $user->id));
            });
        }

        return $query->latest('id')
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
    }
}
