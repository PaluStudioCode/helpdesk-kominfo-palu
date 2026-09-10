<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Department::class);

        return redirect()->route('admin.master-data.index', array_merge(
            ['tab' => 'departments'],
            $request->query()
        ));
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
        
        $ticketsCount = $department->tickets()->count();
        if ($ticketsCount > 0) {
            return redirect()->back()
                ->with('error', "Tidak dapat menghapus instansi ini karena masih terhubung dengan {$ticketsCount} data riwayat tiket.");
        }

        $usersCount = $department->users()->count();
        if ($usersCount > 0) {
            return redirect()->back()
                ->with('error', "Tidak dapat menghapus instansi ini karena masih memiliki {$usersCount} akun pengguna.");
        }

        $department->delete();

        return redirect()->back()
            ->with('success', 'Data OPD / Instansi berhasil dihapus.');
    }
}
