<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // the signed-in user (null on the sign-in page)
            'auth' => ['user' => fn () => $request->user()?->only('id', 'name', 'email')],
            // the green "saved" message after an action
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'today' => now()->format('l, F j, Y'),
            // the numbers beside the menu items — only for signed-in users
            'activeJobs' => fn () => $request->user() ? \App\Models\JobOrder::whereIn('status', ['open', 'ongoing'])->count() : 0,
            'unpaidInvoices' => fn () => $request->user() // invoices with money still owed
                ? \App\Models\Invoice::whereRaw('amount > (select coalesce(sum(amount), 0) from payments where payments.invoice_id = invoices.id)')->count()
                : 0,
            'pendingCount' => fn () => $request->user() ? \App\Models\Quotation::where('status', 'pending')->count() : 0,
        ];
    }
}
