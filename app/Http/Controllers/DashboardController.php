<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use App\Models\Quotation;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $hour = now()->hour;

        return Inertia::render('Dashboard', [
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'),
            'month' => now()->format('F'),
            'stats' => [
                'pending_count' => Quotation::where('status', 'pending')->count(),
                'pending_value' => (float) Quotation::where('status', 'pending')->sum('grand_total'),
                'active_jobs' => JobOrder::whereIn('status', ['open', 'ongoing'])->count(),
                'to_bill_count' => JobOrder::where('status', 'completed')->count(),
                'to_bill_value' => (float) Quotation::whereHas('jobOrder', fn ($q) => $q->where('status', 'completed'))->sum('grand_total'),
                'month_count' => Quotation::where('status', 'approved')->where('approved_at', '>=', now()->startOfMonth())->count(),
                'month_value' => (float) Quotation::where('status', 'approved')->where('approved_at', '>=', now()->startOfMonth())->sum('grand_total'),
            ],
            // the ones waiting longest come first, so nobody forgets to follow up
            'waiting' => Quotation::with('customer')->where('status', 'pending')->oldest('created_at')->take(6)->get()
                ->map(fn ($q) => [
                    'id' => $q->id,
                    'quotation_no' => $q->quotation_no,
                    'customer' => $q->customer->name,
                    'project' => $q->project,
                    'grand_total' => (float) $q->grand_total,
                    'sent' => $q->created_at->diffForHumans(),
                ]),
            'jobs' => JobOrder::with('quotation.customer')->whereIn('status', ['open', 'ongoing', 'completed'])->latest('id')->take(6)->get()
                ->map(fn ($jo) => [
                    'id' => $jo->id,
                    'jo_no' => $jo->jo_no,
                    'customer' => $jo->quotation->customer->name,
                    'project' => $jo->quotation->project,
                    'status' => $jo->status,
                    'status_label' => JobOrder::STATUSES[$jo->status],
                ]),
        ]);
    }
}
