<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Handle the incoming request for dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $dashboardData = $this->dashboardService->getDashboardData($request, $user);

        return Inertia::render('Dashboard', $dashboardData);
    }
}
