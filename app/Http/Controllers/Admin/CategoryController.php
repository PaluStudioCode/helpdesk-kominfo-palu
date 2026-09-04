<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketCategory;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TicketCategory::class);

        $query = TicketCategory::query();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $infraType = $request->input('infrastructure_type', $request->input('network_type'));
        if (!empty($infraType) && $infraType !== 'all') {
            $query->where('infrastructure_type', $infraType);
        }

        if ($request->has('sort')) {
            $query->orderBy($request->input('sort'), $request->input('direction', 'asc'));
        } else {
            $query->orderBy('name', 'asc');
        }

        $categories = $query->paginate(10)->withQueryString();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['search', 'sort', 'direction', 'infrastructure_type', 'network_type']),
        ]);
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
        
        $activeTicketsCount = $category->tickets()
            ->whereIn('status', ['pending_admin', 'in_progress', 'pending_approval'])
            ->count();

        if ($activeTicketsCount > 0) {
            return redirect()->back()
                ->with('error', "Tidak dapat menghapus kategori ini karena masih digunakan oleh {$activeTicketsCount} tiket aktif.");
        }

        $category->delete();

        return redirect()->back()
            ->with('success', 'Kategori Gangguan berhasil dihapus.');
    }
}
