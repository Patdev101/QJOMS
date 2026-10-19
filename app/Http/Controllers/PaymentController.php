<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice)
    {
        $invoice->load('payments');
        $balance = $invoice->balance();

        // a payment can't be more than what is still owed
        $data = $request->validate([
            'date' => 'required|date',
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.$balance],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => 'nullable|string|max:100',
        ], ['amount.max' => 'The payment is more than the balance of ₱'.number_format($balance, 2).'.']);

        $payment = $invoice->payments()->create($data);
        $invoice->load('payments');

        Activity::log($invoice, 'payment', "Payment of ₱".number_format($payment->amount, 2).' received ('.Payment::METHODS[$payment->method]
            .($payment->reference ? ", ref {$payment->reference}" : '').'). Balance ₱'.number_format($invoice->balance(), 2));

        return back()->with('success', $invoice->balance() <= 0 ? "Invoice {$invoice->invoice_no} is fully paid." : 'Payment recorded.');
    }

    /** remove a payment entered by mistake */
    public function destroy(Payment $payment)
    {
        $invoice = $payment->invoice;
        $payment->delete();
        $invoice->load('payments');

        Activity::log($invoice, 'payment_removed', 'Payment of ₱'.number_format($payment->amount, 2).' removed. Balance ₱'.number_format($invoice->balance(), 2));

        return back()->with('success', 'Payment removed.');
    }
}
