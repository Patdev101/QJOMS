<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use App\Models\Quotation;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $hour = now()->hour;

        return view('dashboard', [
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'),
            'stats' => [
                'pending_count' => Quotation::where('status', 'pending')->count(),
                'pending_value' => Quotation::where('status', 'pending')->sum('grand_total'),
                'active_jobs' => JobOrder::whereIn('status', ['open', 'ongoing'])->count(),
                'to_bill_count' => JobOrder::where('status', 'completed')->count(),
                'to_bill_value' => Quotation::whereHas('jobOrder', fn ($q) => $q->where('status', 'completed'))->sum('grand_total'),
                'month_count' => Quotation::where('status', 'approved')->where('approved_at', '>=', now()->startOfMonth())->count(),
                'month_value' => Quotation::where('status', 'approved')->where('approved_at', '>=', now()->startOfMonth())->sum('grand_total'),
            ],
            // the ones waiting longest come first, so nobody forgets to follow up
            'waiting' => Quotation::with('customer')->where('status', 'pending')->oldest('created_at')->take(6)->get(),
            'jobs' => JobOrder::with('quotation.customer')->whereIn('status', ['open', 'ongoing', 'completed'])->latest('id')->take(6)->get(),
        ]);
    }
}
