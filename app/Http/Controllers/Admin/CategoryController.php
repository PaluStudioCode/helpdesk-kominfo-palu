<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketCategory;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', TicketCategory::class);

        return redirect()->route('admin.master-data.index', array_merge(
            ['tab' => 'categories'],
            $request->query()
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        TicketCategory::create($request->validated());

        return redirect()->back()
            ->with('success', 'Kategori Gangguan berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, TicketCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->back()
            ->with('success', 'Kategori Gangguan berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);
        
        $ticketsCount = $category->tickets()->count();
        if ($ticketsCount > 0) {
            return redirect()->back()
                ->with('error', "Tidak dapat menghapus kategori ini karena masih digunakan oleh {$ticketsCount} data tiket.");
        }

        $category->delete();

        return redirect()->back()
            ->with('success', 'Kategori Gangguan berhasil dihapus.');
    }
}
