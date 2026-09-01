<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SimpleExcelExport;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
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
     * Export reports to PDF format.
     */
    public function exportPdf(Request $request)
    {
        $tickets = $this->buildFilteredQuery($request)->get();

        $pdf = Pdf::loadView('reports.tickets-pdf', [
            'tickets' => $tickets,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ])->setPaper('a4', 'landscape');

        $fileName = 'Laporan-Rekapitulasi-Helpdesk-' . date('Ymd-His') . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * Export reports to Excel format.
     */
    public function exportExcel(Request $request)
    {
        $tickets = $this->buildFilteredQuery($request)->get();
        $fileName = 'Laporan-Rekapitulasi-Helpdesk-' . date('Ymd-His') . '.csv';

        return SimpleExcelExport::download($tickets, $fileName);
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
