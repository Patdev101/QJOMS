<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $all = Invoice::with('payments', 'jobOrder.quotation.customer')->latest('id')->get();

        // status comes from the payments, so filtering happens after loading
        $counts = [
            'all' => $all->count(),
            'unpaid' => $all->filter(fn ($i) => $i->status() === 'unpaid')->count(),
            'partial' => $all->filter(fn ($i) => $i->status() === 'partial')->count(),
            'paid' => $all->filter(fn ($i) => $i->status() === 'paid')->count(),
            'overdue' => $all->filter(fn ($i) => $i->isOverdue())->count(),
        ];

        $s = mb_strtolower(trim((string) $request->q));
        $invoices = $all
            ->when($request->status === 'overdue', fn ($c) => $c->filter(fn ($i) => $i->isOverdue()))
            ->when(in_array($request->status, ['unpaid', 'partial', 'paid']), fn ($c) => $c->filter(fn ($i) => $i->status() === $request->status))
            ->when($s !== '', fn ($c) => $c->filter(fn ($i) => str_contains(mb_strtolower(implode(' ', [
                $i->invoice_no, $i->jobOrder->jo_no, $i->jobOrder->quotation->customer->name, $i->jobOrder->quotation->project,
            ])), $s)))
            ->values()
            ->map(fn ($i) => $this->row($i));

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'counts' => $counts,
            'outstanding' => round($all->sum(fn ($i) => max($i->balance(), 0)), 2),
            'filters' => ['status' => $request->status ?? '', 'q' => $request->q ?? ''],
        ]);
    }

    /** a completed job order is billed: the invoice is made and the job order becomes "Billed" */
    public function store(Request $request, JobOrder $jobOrder)
    {
        if ($jobOrder->status !== 'completed' || $jobOrder->invoice()->exists()) {
            return back()->with('error', 'Only a completed job order without an invoice can be billed.');
        }

        $data = $request->validate([
            'date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:date',
            'notes' => 'nullable|string|max:255',
        ], ['due_date.after_or_equal' => 'The due date cannot be before the invoice date.']);

        $invoice = DB::transaction(function () use ($jobOrder, $data) {
            $invoice = Invoice::create($data + [
                'invoice_no' => Invoice::nextNumber(),
                'job_order_id' => $jobOrder->id,
                'amount' => $jobOrder->quotation->grand_total,
            ]);
            $jobOrder->update(['status' => 'billed']);

            return $invoice;
        });

        Activity::log($invoice, 'invoiced', "Invoice {$invoice->invoice_no} issued for ₱".number_format($invoice->amount, 2).', due '.$invoice->due_date->format('M j, Y'));

        return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->invoice_no} created.");
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('payments', 'jobOrder.quotation');

        return Inertia::render('Invoices/Show', [
            'invoice' => $this->row($invoice) + [
                'notes' => $invoice->notes,
                'job_order_id' => $invoice->jobOrder->id,
                'payments' => $invoice->payments->sortBy('date')->values()->map(fn ($p) => [
                    'id' => $p->id,
                    'date' => $p->date->format('M j, Y'),
                    'amount' => (float) $p->amount,
                    'method' => Payment::METHODS[$p->method] ?? $p->method,
                    'reference' => $p->reference,
                ]),
            ],
            'quotation' => $invoice->jobOrder->quotation->details(),
            'methods' => Payment::METHODS,
            'today' => now()->format('Y-m-d'),
            'history' => Activity::timeline($invoice->jobOrder->quotation_id),
        ]);
    }

    public function print(Invoice $invoice)
    {
        $invoice->load('payments', 'jobOrder.quotation.customer', 'jobOrder.quotation.lines');

        return view('invoices.print', ['invoice' => $invoice, 'quotation' => $invoice->jobOrder->quotation]);
    }

    private function row(Invoice $i): array
    {
        return [
            'id' => $i->id,
            'invoice_no' => $i->invoice_no,
            'jo_no' => $i->jobOrder->jo_no,
            'customer' => $i->jobOrder->quotation->customer->name,
            'project' => $i->jobOrder->quotation->project,
            'date' => $i->date->format('M j, Y'),
            'due_date' => $i->due_date->format('M j, Y'),
            'amount' => (float) $i->amount,
            'paid' => $i->paid(),
            'balance' => $i->balance(),
            'status' => $i->status(),
            'status_label' => Invoice::STATUSES[$i->status()],
            'overdue' => $i->isOverdue(),
        ];
    }
}
