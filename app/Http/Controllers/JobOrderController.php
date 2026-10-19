<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

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
            ->withQueryString()
            ->through(fn ($jo) => [
                'id' => $jo->id,
                'jo_no' => $jo->jo_no,
                'quotation_no' => $jo->quotation->quotation_no,
                'customer' => $jo->quotation->customer->name,
                'project' => $jo->quotation->project,
                'date' => $jo->date->format('M j, Y'),
                'grand_total' => (float) $jo->quotation->grand_total,
                'status' => $jo->status,
                'status_label' => JobOrder::STATUSES[$jo->status],
            ]);

        $byStatus = JobOrder::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return Inertia::render('JobOrders/Index', [
            'jobOrders' => $jobOrders,
            'counts' => ['all' => $byStatus->sum()] + collect(self::FILTERS)->map(fn ($statuses) => $byStatus->only($statuses)->sum())->all(),
            'filters' => ['status' => $request->status ?? '', 'q' => $request->q ?? ''],
        ]);
    }

    public function show(JobOrder $jobOrder)
    {
        return Inertia::render('JobOrders/Show', [
            'jobOrder' => [
                'id' => $jobOrder->id,
                'jo_no' => $jobOrder->jo_no,
                'date' => $jobOrder->date->format('M j, Y'),
                'status' => $jobOrder->status,
                'status_label' => JobOrder::STATUSES[$jobOrder->status],
            ],
            'statuses' => JobOrder::STATUSES,
            'quotation' => $jobOrder->quotation->details(),
        ]);
    }

    public function updateStatus(Request $request, JobOrder $jobOrder)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(JobOrder::STATUSES))]]);

        $jobOrder->update($data);

        return back()->with('success', 'Job order marked as '.JobOrder::STATUSES[$data['status']].'.');
    }
}
