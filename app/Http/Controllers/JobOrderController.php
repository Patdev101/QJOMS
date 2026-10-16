<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobOrderController extends Controller
{
    /** filter chips on the list: "in progress" covers both open and ongoing */
    public const FILTERS = [
        'active' => ['open', 'ongoing'],
        'completed' => ['completed'],
        'billed' => ['billed'],
    ];

    public function index(Request $request)
    {
        $filter = self::FILTERS[$request->status] ?? null;

        $jobOrders = JobOrder::with('quotation.customer')
            ->when($filter, fn ($q) => $q->whereIn('status', $filter))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('jo_no', 'like', "%{$s}%")
                ->orWhereHas('quotation', fn ($qt) => $qt->where('quotation_no', 'like', "%{$s}%")
                    ->orWhere('project', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")))))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $byStatus = JobOrder::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $counts = collect(self::FILTERS)->map(fn ($statuses) => $byStatus->only($statuses)->sum());

        return view('job-orders.index', [
            'jobOrders' => $jobOrders,
            'counts' => $counts,
            'total' => $byStatus->sum(),
        ]);
    }

    public function show(JobOrder $jobOrder)
    {
        $jobOrder->load('quotation.customer', 'quotation.lines');

        return view('job-orders.show', compact('jobOrder'));
    }

    public function updateStatus(Request $request, JobOrder $jobOrder)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(JobOrder::STATUSES))]]);

        $jobOrder->update($data);

        return back()->with('success', 'Job order marked as '.JobOrder::STATUSES[$data['status']].'.');
    }
}
