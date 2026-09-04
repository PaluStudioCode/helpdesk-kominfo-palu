<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Material;
use App\Models\NetworkDevice;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TicketService;
use App\Http\Requests\StoreTicketRequest;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;

class TicketController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        $user = $request->user();
        $query = Ticket::with([
            'department:id,name', 
            'category:id,name', 
            'assignee:id,name',
            'technicians:id,name'
        ]);

        // Data Isolation Rule for OPD User & Technician
        if ($user->role === 'opd_user') {
            $query->where('department_id', $user->department_id);
        } elseif ($user->role === 'technician') {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('technicians', fn($qt) => $qt->where('users.id', $user->id));
            });
        }

        // Apply Search Filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('department', fn($qd) => $qd->where('name', 'like', "%{$search}%"));
            });
        }

        // Apply Infrastructure / Network Type Filter
        $infraTypeFilter = $request->input('infrastructure_type', $request->input('network_type'));
        if (!empty($infraTypeFilter) && $infraTypeFilter !== 'all') {
            $query->where('infrastructure_type', $infraTypeFilter);
        }

        // Apply Status Filter
        if ($request->has('status') && $request->input('status') !== 'all') {
            $status = $request->input('status');
            if ($status === 'mendekati_sla') {
                $query->where('status', 'in_progress')
                      ->whereRaw('TIMESTAMPDIFF(HOUR, NOW(), due_at) <= 2')
                      ->where('due_at', '>=', now());
            } elseif ($status === 'overdue') {
                $query->where('status', 'in_progress')
                      ->where('due_at', '<', now());
            } else {
                $query->where('status', $status);
            }
        }

        // Apply Priority Filter
        if ($request->has('priority') && $request->input('priority') !== 'all') {
            $query->where('priority', $request->input('priority'));
        }

        // Apply Sorting
        if ($request->has('sort')) {
            $query->orderBy($request->input('sort'), $request->input('direction', 'desc'));
        } else {
            $query->latest();
        }

        $tickets = $query->paginate(10)->withQueryString()->through(function ($t) {
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
                'department' => $t->department ? ['id' => $t->department->id, 'name' => $t->department->name] : null,
                'category' => $t->category ? ['id' => $t->category->id, 'name' => $t->category->name] : null,
                'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
                'technicians' => $t->technicians ? $t->technicians->map(fn($tech) => ['id' => $tech->id, 'name' => $tech->name]) : [],
                'infrastructure_type' => $t->infrastructure_type,
                'network_type' => $t->infrastructure_type,
                'priority' => $t->priority,
                'status' => $t->status,
                'due_at' => $t->due_at ? $t->due_at->format('d M Y, H:i') : null,
                'due_human' => $dueHuman,
                'is_overdue' => $isOverdue,
                'created_at' => $t->created_at ? $t->created_at->toISOString() : null,
            ];
        });

        $categories = TicketCategory::where('status', 'active')
                        ->select('id', 'name', 'infrastructure_type')
                        ->get()
                        ->groupBy('infrastructure_type');

        $departments = [];
        $technicians = [];
        if ($user->role === 'admin') {
            $departments = Department::where('status', 'active')
                            ->select('id', 'name')
                            ->orderBy('name')
                            ->get();

            $technicians = User::where('role', 'technician')
                            ->where('status', 'active')
                            ->select('id', 'name', 'phone_number')
                            ->orderBy('name')
                            ->get();
        }

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'categoriesMap' => $categories,
            'departments' => $departments,
            'technicians' => $technicians,
            'filters' => $request->only(['search', 'status', 'priority', 'infrastructure_type', 'network_type', 'sort', 'direction']),
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
                        ->select('id', 'name', 'infrastructure_type')
                        ->get()
                        ->groupBy('infrastructure_type');

        $departments = [];
        $technicians = [];
        if (auth()->user()->role === 'admin') {
            $departments = Department::where('status', 'active')
                            ->select('id', 'name')
                            ->orderBy('name')
                            ->get();

            $technicians = User::where('role', 'technician')
                            ->where('status', 'active')
                            ->select('id', 'name', 'phone_number')
                            ->orderBy('name')
                            ->get();
        }

        return Inertia::render('Tickets/Create', [
            'categoriesMap' => $categories,
            'departments' => $departments,
            'technicians' => $technicians,
            'isAdmin' => auth()->user()->role === 'admin'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTicketRequest $request)
    {
        try {
            $ticket = $this->ticketService->createTicket(
                $request->validated(),
                $request->user(),
                $request->file('attachments', [])
            );

            return redirect()->route('tickets.index')->with('success', "Tiket #{$ticket->ticket_number} berhasil didaftarkan.");
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menyimpan tiket: ' . $e->getMessage())->withInput();
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
            'technicians:id,name,phone_number',
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

        $categories = TicketCategory::where('status', 'active')
            ->select('id', 'name', 'infrastructure_type')
            ->get()
            ->groupBy('infrastructure_type');

        $technicians = [];
        if ($user->role === 'admin') {
            $technicians = User::where('role', 'technician')
                ->where('status', 'active')
                ->select('id', 'name', 'phone_number')
                ->orderBy('name')
                ->get();
        }

        // Calculate initial unread replies count based on database ticket_reads
        $lastReadReplyId = \App\Models\TicketRead::where('ticket_id', $ticket->id)
            ->where('user_id', $user->id)
            ->value('last_read_reply_id') ?? 0;

        $unreadRepliesQuery = $ticket->replies()
            ->where('user_id', '!=', $user->id);

        if ($user->role === 'opd_user') {
            $unreadRepliesQuery->where('is_internal', false);
        }

        if ($lastReadReplyId > 0) {
            $unreadRepliesQuery->where('id', '>', $lastReadReplyId);
        }

        $unreadRepliesCount = $unreadRepliesQuery->count();

        $availableDevices = NetworkDevice::where('status', 'active')
            ->orderBy('name')
            ->pluck('name');

        $availableMaterials = Material::where('status', 'active')
            ->orderBy('name')
            ->select('name', 'default_unit')
            ->get();

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'categoriesMap' => $categories,
            'technicians' => $technicians,
            'availableDevices' => $availableDevices,
            'availableMaterials' => $availableMaterials,
            'initialUnreadCount' => $unreadRepliesCount,
        ]);
    }
}
