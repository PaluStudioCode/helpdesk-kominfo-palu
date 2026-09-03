<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        $query = Department::with('operator');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhereHas('operator', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('sort')) {
            $query->orderBy($request->input('sort'), $request->input('direction', 'asc'));
        } else {
            $query->orderBy('name', 'asc');
        }

        $departments = $query->paginate(10)->withQueryString();

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
            'filters' => $request->only(['search', 'sort', 'direction']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return redirect()->back()
            ->with('success', 'Data OPD / Instansi berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()->back()
            ->with('success', 'Data OPD / Instansi berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);
        
        $activeTicketsCount = $department->tickets()
            ->whereIn('status', ['pending_admin', 'in_progress', 'pending_approval'])
            ->count();

        if ($activeTicketsCount > 0) {
            return redirect()->back()
                ->with('error', "Tidak dapat menghapus instansi ini karena masih memiliki {$activeTicketsCount} tiket aktif yang sedang berjalan.");
        }

        $department->delete();

        return redirect()->back()
            ->with('success', 'Data OPD / Instansi berhasil dihapus.');
    }
}
