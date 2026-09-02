<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $filterStart = null;
        $filterEnd = null;
        $activeTasks = [];
        $recentFeedbacks = [];
        $technicianResolutionChart = null;

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

            // 1. Antrean Tugas Aktif Lapangan (In Progress)
            $activeTasks = Ticket::with(['department:id,name,code', 'category:id,name'])
                ->where('status', 'in_progress')
                ->where($myTicketsQuery)
                ->orderByRaw("FIELD(priority, 'emergency', 'high', 'medium', 'low') ASC")
                ->orderBy('due_at', 'asc')
                ->limit(6)
                ->get()
                ->map(function ($t) {
                    $dueTime = $t->due_at ? \Carbon\Carbon::parse($t->due_at) : null;
                    $isOverdue = $dueTime ? now()->gt($dueTime) : false;
                    
                    $dueHuman = '-';
                    if ($dueTime) {
                        if ($isOverdue) {
                            $dueHuman = 'Lewat ' . $dueTime->diffForHumans(null, true);
                        } else {
                            $dueHuman = 'Sisa ' . now()->diffForHumans($dueTime, true);
                        }
                    }

                    return [
                        'id' => $t->id,
                        'ticket_number' => $t->ticket_number,
                        'title' => $t->title,
                        'department_name' => $t->department ? $t->department->name : '-',
                        'department_code' => $t->department ? $t->department->code : '-',
                        'location_details' => $t->location_details ?? '-',
                        'category_name' => $t->category ? $t->category->name : '-',
                        'network_type' => $t->network_type,
                        'priority' => $t->priority,
                        'due_at' => $t->due_at ? $t->due_at->format('d M Y, H:i') : null,
                        'due_human' => $dueHuman,
                        'is_overdue' => $isOverdue,
                    ];
                })
                ->values()
                ->toArray();

            // 2. Ulasan & Masukan Kepuasan OPD Terbaru
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

            // 3. Rata-rata Waktu Penyelesaian Bulanan (Jam)
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

            // Filter Type: 'year_month' (default) or 'range'
            $filterType = $request->query('filter_type', 'year_month');
            $selectedYear = $request->query('year', $availableYears[0]);
            if (!in_array($selectedYear, $availableYears) && $selectedYear !== 'all') {
                $selectedYear = $availableYears[0];
            }

            $selectedMonth = $request->query('month', 'all'); // 'all', '01'..'12'
            $selectedPreset = $request->query('preset', null); // '7d', '30d', 'this_month', 'custom'
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
                        $filterStart = \Carbon\Carbon::parse($startDateParam)->startOfDay();
                        $filterEnd = \Carbon\Carbon::parse($endDateParam)->endOfDay();
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
                // filterType === 'year_month'
                $filterType = 'year_month';
                if ($selectedYear !== 'all') {
                    if ($selectedMonth !== 'all' && is_numeric($selectedMonth) && (int)$selectedMonth >= 1 && (int)$selectedMonth <= 12) {
                        $selectedMonth = str_pad($selectedMonth, 2, '0', STR_PAD_LEFT);
                        $filterStart = \Carbon\Carbon::create((int)$selectedYear, (int)$selectedMonth, 1)->startOfMonth();
                        $filterEnd = \Carbon\Carbon::create((int)$selectedYear, (int)$selectedMonth, 1)->endOfMonth();
                    } else {
                        $selectedMonth = 'all';
                        $filterStart = \Carbon\Carbon::create((int)$selectedYear, 1, 1)->startOfYear();
                        $filterEnd = \Carbon\Carbon::create((int)$selectedYear, 12, 31)->endOfYear();
                    }
                } else {
                    $selectedMonth = 'all';
                    $filterStart = null;
                    $filterEnd = null;
                }
            }

            // Apply filter to statsQuery
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

            // Monthly Performance Reports Table
            $tableQuery = Ticket::selectRaw("
                DATE_FORMAT(created_at, '%Y-%m') as period,
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status IN ('in_progress', 'pending_approval') THEN 1 ELSE 0 END) as in_progress,
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

            // Total Summary calculation for the table
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

            // Chart 4: Line Chart (Tren Selesai)
            // Rules from User: Line chart responds ONLY to Year filter and always shows Jan-Des for that year, or multi-year chronological if 'all'
            $shortMonths = [
                '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
                '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
                '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
            ];

            $trendLabels = [];
            $trendCreated = [];
            $trendClosed = [];

            // Line chart query solely based on selectedYear
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
            'filterType' => $filterType ?? 'year_month',
            'selectedYear' => $selectedYear ?? '2025',
            'selectedMonth' => $selectedMonth ?? 'all',
            'selectedPreset' => $selectedPreset ?? null,
            'startDate' => $filterStart ? $filterStart->format('Y-m-d') : null,
            'endDate' => $filterEnd ? $filterEnd->format('Y-m-d') : null,
            'statusDistribution' => $statusDistribution ?? null,
            'networkTypeDistribution' => $networkTypeDistribution ?? null,
            'priorityDistribution' => $priorityDistribution ?? null,
            'ticketTrend' => $ticketTrend ?? null,
            'recentTickets' => $recentTickets,
            'recentActivities' => $recentActivities,
            'activeTasks' => $activeTasks,
            'recentFeedbacks' => $recentFeedbacks,
            'technicianResolutionChart' => $technicianResolutionChart,
        ]);
    }
}
