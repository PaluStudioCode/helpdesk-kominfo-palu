<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    /**
     * Store a newly created vendor in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:vendors,name'],
            'category' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'name.required' => 'Nama mitra / vendor wajib diisi.',
            'name.unique' => 'Nama mitra / vendor sudah terdaftar.',
            'category.required' => 'Kategori bidang usaha wajib diisi.',
            'phone.required' => 'Nomor telepon / kontak wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
        ]);

        Vendor::create($validated);

        return redirect()->back()->with('success', 'Mitra / Rekanan Vendor berhasil ditambahkan.');
    }

    /**
     * Update the specified vendor in storage.
     */
    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('vendors', 'name')->ignore($vendor->id)],
            'category' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'name.required' => 'Nama mitra / vendor wajib diisi.',
            'name.unique' => 'Nama mitra / vendor sudah digunakan.',
            'category.required' => 'Kategori bidang usaha wajib diisi.',
            'phone.required' => 'Nomor telepon / kontak wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
        ]);

        $vendor->update($validated);

        return redirect()->back()->with('success', 'Data Mitra / Rekanan Vendor berhasil diperbarui.');
    }

    /**
     * Remove the specified vendor from storage.
     */
    public function destroy(Vendor $vendor): RedirectResponse
    {
        $vendor->delete();

        return redirect()->back()->with('success', 'Mitra / Rekanan Vendor berhasil dihapus.');
    }
}
