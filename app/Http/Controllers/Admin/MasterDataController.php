<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    /**
     * Display the combined master data hub.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        $activeTab = $request->input('tab', 'departments');

        // 1. Departments Query
        $deptQuery = Department::query();
        if ($activeTab === 'departments' && $request->has('search')) {
            $search = $request->input('search');
            $deptQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        if ($activeTab === 'departments' && $request->has('sort')) {
            $deptQuery->orderBy($request->input('sort'), $request->input('direction', 'asc'));
        } else {
            $deptQuery->orderBy('name', 'asc');
        }
        $departments = $deptQuery->paginate(10, ['*'], 'dept_page')->withQueryString();

        // 2. Categories Query
        $catQuery = TicketCategory::query();
        if ($activeTab === 'categories' && $request->has('search')) {
            $search = $request->input('search');
            $catQuery->where('name', 'like', "%{$search}%");
        }
        if ($activeTab === 'categories' && $request->has('sort')) {
            $catQuery->orderBy($request->input('sort'), $request->input('direction', 'asc'));
        } else {
            $catQuery->orderBy('name', 'asc');
        }
        $categories = $catQuery->paginate(10, ['*'], 'cat_page')->withQueryString();

        // 3. Users Query
        $userQuery = User::with('department');
        if ($activeTab === 'users' && $request->has('search')) {
            $search = $request->input('search');
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }
        if ($activeTab === 'users' && $request->has('role') && $request->input('role') !== 'all') {
            $userQuery->where('role', $request->input('role'));
        }
        if ($activeTab === 'users' && $request->has('sort')) {
            $userQuery->orderBy($request->input('sort'), $request->input('direction', 'asc'));
        } else {
            $userQuery->orderBy('created_at', 'desc');
        }
        $users = $userQuery->paginate(10, ['*'], 'user_page')->withQueryString();

        // All active departments for User Create/Edit dropdown
        $allDepartments = Department::select('id', 'name')->where('status', 'active')->orderBy('name')->get();

        // Counts for Badges
        $counts = [
            'departments' => Department::count(),
            'categories' => TicketCategory::count(),
            'users' => User::count(),
        ];

        return Inertia::render('Admin/MasterData/Index', [
            'activeTab' => $activeTab,
            'departments' => $departments,
            'categories' => $categories,
            'users' => $users,
            'counts' => $counts,
            'allDepartments' => $allDepartments,
            'filters' => $request->only(['tab', 'search', 'sort', 'direction', 'role']),
        ]);
    }
}
