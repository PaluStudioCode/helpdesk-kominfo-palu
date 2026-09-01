<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Policies\DepartmentPolicy;
use App\Policies\TicketCategoryPolicy;
use App\Policies\TicketPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(TicketCategory::class, TicketCategoryPolicy::class);
    }
}
