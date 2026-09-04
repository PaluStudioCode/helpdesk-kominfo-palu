<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialController extends Controller
{
    /**
     * Store a newly created material in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:materials,name'],
            'default_unit' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'name.required' => 'Nama material / perangkat wajib diisi.',
            'name.unique' => 'Nama material / perangkat sudah terdaftar.',
            'default_unit.required' => 'Satuan standar wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
        ]);

        Material::create($validated);

        return redirect()->back()->with('success', 'Material / Perangkat berhasil ditambahkan.');
    }

    /**
     * Update the specified material in storage.
     */
    public function update(Request $request, Material $material): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('materials', 'name')->ignore($material->id)],
            'default_unit' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'name.required' => 'Nama material / perangkat wajib diisi.',
            'name.unique' => 'Nama material / perangkat sudah digunakan.',
            'default_unit.required' => 'Satuan standar wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
        ]);

        $material->update($validated);

        return redirect()->back()->with('success', 'Material / Perangkat berhasil diperbarui.');
    }

    /**
     * Remove the specified material from storage.
     */
    public function destroy(Material $material): RedirectResponse
    {
        $material->delete();

        return redirect()->back()->with('success', 'Material / Perangkat berhasil dihapus.');
    }
}
