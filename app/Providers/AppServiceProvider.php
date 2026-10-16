<?php

namespace App\Providers;

use App\Models\JobOrder;
use App\Models\Quotation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
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
        // plain <ul class="pagination"> markup, styled in style.css
        Paginator::useBootstrapFour();

        // the counts shown beside the menu items
        View::composer('layout', function ($view) {
            $view->with('navCounts', [
                'pending' => Quotation::where('status', 'pending')->count(),
                'active_jobs' => JobOrder::whereIn('status', ['open', 'ongoing'])->count(),
            ]);
        });
    }
}
