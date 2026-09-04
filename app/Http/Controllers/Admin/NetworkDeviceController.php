<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NetworkDeviceController extends Controller
{
    /**
     * Store a newly created network device in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:network_devices,name'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'name.required' => 'Nama perangkat / node wajib diisi.',
            'name.unique' => 'Nama perangkat / node sudah terdaftar.',
            'status.required' => 'Status wajib dipilih.',
        ]);

        NetworkDevice::create($validated);

        return redirect()->back()->with('success', 'Perangkat / Node berhasil ditambahkan.');
    }

    /**
     * Update the specified network device in storage.
     */
    public function update(Request $request, NetworkDevice $device): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('network_devices', 'name')->ignore($device->id)],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'name.required' => 'Nama perangkat / node wajib diisi.',
            'name.unique' => 'Nama perangkat / node sudah digunakan.',
            'status.required' => 'Status wajib dipilih.',
        ]);

        $device->update($validated);

        return redirect()->back()->with('success', 'Perangkat / Node berhasil diperbarui.');
    }

    /**
     * Remove the specified network device from storage.
     */
    public function destroy(NetworkDevice $device): RedirectResponse
    {
        $device->delete();

        return redirect()->back()->with('success', 'Perangkat / Node berhasil dihapus.');
    }
}
