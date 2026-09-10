<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', User::class);

        return redirect()->route('admin.master-data.index', array_merge(
            ['tab' => 'users'],
            $request->query()
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);
        
        if ($validated['role'] !== 'opd_user') {
            $validated['department_id'] = null;
        }

        User::create($validated);

        return redirect()->back()
            ->with('success', 'Pengguna berhasil didaftarkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if ($validated['role'] !== 'opd_user') {
            $validated['department_id'] = null;
        }

        $user->update($validated);

        return redirect()->back()
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        
        $ticketsCount = \App\Models\Ticket::where('reporter_id', $user->id)
            ->orWhere('assigned_to', $user->id)
            ->count();

        if ($ticketsCount > 0) {
            return redirect()->back()
                ->with('error', "Tidak dapat menghapus akun ini karena masih terhubung dengan {$ticketsCount} data tiket.");
        }

        $user->delete();

        return redirect()->back()
            ->with('success', 'Akun pengguna berhasil dihapus.');
    }
}
