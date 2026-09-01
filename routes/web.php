<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketActionController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Ticket Resource Routes
    Route::resource('tickets', TicketController::class);
    
    // Ticket Lifecycle Actions (New Admin-Centric Workflow)
    Route::post('tickets/{ticket}/verify-assign', [TicketActionController::class, 'verifyAndAssign'])->name('tickets.verify-assign');
    Route::post('tickets/{ticket}/reject', [TicketActionController::class, 'reject'])->name('tickets.reject');
    Route::post('tickets/{ticket}/resubmit', [TicketActionController::class, 'resubmit'])->name('tickets.resubmit');
    Route::post('tickets/{ticket}/submit-resolution', [TicketActionController::class, 'submitResolution'])->name('tickets.submit-resolution');
    Route::post('tickets/{ticket}/approve-resolution', [TicketActionController::class, 'approveResolution'])->name('tickets.approve-resolution');
    Route::post('tickets/{ticket}/request-revision', [TicketActionController::class, 'requestRevision'])->name('tickets.request-revision');
    Route::post('tickets/{ticket}/rate', [TicketActionController::class, 'rate'])->name('tickets.rate');
    Route::post('tickets/{ticket}/replies', [TicketActionController::class, 'storeReply'])->name('tickets.replies.store');
    Route::post('tickets/{ticket}/mark-read', [TicketActionController::class, 'markAsRead'])->name('tickets.mark-read');

    // Admin Routes
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('master-data', [MasterDataController::class, 'index'])->name('master-data.index');

        Route::resource('departments', DepartmentController::class)->except(['create', 'show', 'edit']);
        Route::resource('categories', CategoryController::class)->except(['create', 'show', 'edit']);
        Route::resource('users', UserController::class)->except(['create', 'show', 'edit']);

        // Reports & Export
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
        Route::get('reports/export/excel', [ReportController::class, 'exportExcel'])->name('reports.export.excel');
    });
});

require __DIR__.'/auth.php';
