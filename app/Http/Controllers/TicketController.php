<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\Department;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationDispatcher;
use App\Http\Requests\StoreTicketRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;

class TicketController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        $user = $request->user();
        $query = Ticket::with(['department:id,name', 'category:id,name', 'assignee:id,name']);

        // Data Isolation Rule
        if ($user->role === 'opd_user') {
            $query->where('department_id', $user->department_id);
        }

        // Apply Filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('department', fn($qd) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->has('network_type') && $request->input('network_type') !== 'all') {
            $query->where('network_type', $request->input('network_type'));
        }

        if ($request->has('status') && $request->input('status') !== 'all') {
            $status = $request->input('status');
            if ($status === 'mendekati_sla') {
                $query->whereIn('status', ['open', 'in_progress'])
                      ->whereRaw('TIMESTAMPDIFF(HOUR, NOW(), due_at) <= 2')
                      ->where('due_at', '>=', now());
            } elseif ($status === 'overdue') {
                $query->whereIn('status', ['open', 'in_progress'])
                      ->where('due_at', '<', now());
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->has('priority') && $request->input('priority') !== 'all') {
            $query->where('priority', $request->input('priority'));
        }

        // Apply Sort
        if ($request->has('sort')) {
            $query->orderBy($request->input('sort'), $request->input('direction', 'desc'));
        } else {
            $query->latest();
        }

        $tickets = $query->paginate(10)->withQueryString();

        $categories = TicketCategory::where('status', 'active')
                        ->select('id', 'name', 'network_type')
                        ->get()
                        ->groupBy('network_type');

        $departments = [];
        if ($user->role === 'admin') {
            $departments = Department::where('status', 'active')
                            ->select('id', 'name')
                            ->orderBy('name')
                            ->get();
        }

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'categoriesMap' => $categories,
            'departments' => $departments,
            'filters' => $request->only(['search', 'status', 'priority', 'network_type', 'sort', 'direction']),
            'canCreateOnBehalf' => $user->role === 'admin'
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Ticket::class);

        $categories = TicketCategory::where('status', 'active')
                        ->select('id', 'name', 'network_type')
                        ->get()
                        ->groupBy('network_type');

        $departments = [];
        if (auth()->user()->role === 'admin') {
            $departments = Department::where('status', 'active')
                            ->select('id', 'name')
                            ->orderBy('name')
                            ->get();
        }

        return Inertia::render('Tickets/Create', [
            'categoriesMap' => $categories,
            'departments' => $departments,
            'isAdmin' => auth()->user()->role === 'admin'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTicketRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();

        // Transaction for Atomic Locking & Integrity
        DB::beginTransaction();
        try {
            // Generate unique ticket number logic (TKT-YYYYMMDD-XXXX)
            $datePrefix = date('Ymd');
            
            // Get the latest ticket for today using lockForUpdate to prevent race conditions
            $latestTicket = Ticket::where('ticket_number', 'like', "TKT-{$datePrefix}-%")
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            $sequence = 1;
            if ($latestTicket) {
                $lastSequence = (int) substr($latestTicket->ticket_number, -4);
                $sequence = $lastSequence + 1;
            }

            $ticketNumber = "TKT-{$datePrefix}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            // Fetch SLA config
            $category = TicketCategory::find($validated['category_id']);
            $dueAt = now()->addHours($category->sla_hours);

            // Set Ownership
            $departmentId = $user->role === 'admin' ? $validated['department_id'] : $user->department_id;

            // Create Ticket
            $ticket = Ticket::create([
                'ticket_number' => $ticketNumber,
                'department_id' => $departmentId,
                'reporter_id' => $user->id,
                'category_id' => $category->id,
                'network_type' => $validated['network_type'],
                'title' => $validated['title'],
                'location_details' => $validated['location_details'],
                'description' => $validated['description'],
                'priority' => $validated['priority'],
                'status' => 'open',
                'due_at' => $dueAt,
            ]);

            // Handle Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('ticket-attachments', 'public');
                    
                    $ticket->attachments()->create([
                        'uploaded_by' => $user->id,
                        'attachment_type' => 'issue_proof',
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            // Log Initial Status History
            $ticket->statusHistories()->create([
                'changed_by' => $user->id,
                'new_status' => 'open',
                'comment' => 'Tiket berhasil dibuat.',
                'created_at' => now(),
            ]);

            // Log Activity
            ActivityLogger::log('ticket.created', $ticket, [
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'priority' => $ticket->priority,
                'network_type' => $ticket->network_type,
            ], $user->id);

            DB::commit();

            // Dispatch Queued Notification (after commit)
            NotificationDispatcher::ticketCreated($ticket);

            return redirect()->route('tickets.index')->with('success', "Tiket #{$ticketNumber} berhasil dibuat.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat menyimpan tiket.')->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        $user = $request->user();
        
        $ticket->load([
            'department', 
            'reporter', 
            'assignee', 
            'category', 
            'attachments',
            'statusHistories.changer'
        ]);

        $ticket->load(['replies' => function ($query) use ($user) {
            if ($user->role === 'opd_user') {
                $query->where('is_internal', false);
            }
            $query->with(['user:id,name,role', 'attachments'])->oldest();
        }]);

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        abort(404); // Using specialized status update routes instead (Phase 7)
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        abort(404); // Tickets shouldn't be hard-deleted directly. We use Cancel in Phase 7.
    }
}
