<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quotation extends Model
{
    protected $fillable = [
        'quotation_no', 'date', 'customer_id', 'address', 'contact_person', 'contact_no', 'project',
        'vessel_name', 'loa', 'lbp', 'breadth', 'depth', 'date_arrival', 'date_drydocked', 'date_undocked', 'date_sail_out',
        'subtotal', 'discount_percent', 'discount_amount', 'grand_total', 'total_cost', 'status', 'approved_at',
    ];

    /** vessel particulars and dates: [field, label, suffix] — shown and printed only when filled in */
    public const VESSEL_FIELDS = [
        ['loa', 'L.O.A.', ' m'], ['lbp', 'L.B.P.', ' m'], ['breadth', 'Breadth mld.', ' m'], ['depth', 'Depth mld.', ' m'],
        ['date_arrival', 'Date arrival', ''], ['date_drydocked', 'Date drydocked', ''],
        ['date_undocked', 'Date undocked', ''], ['date_sail_out', 'Date sail-out / tow-out', ''],
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'date_arrival' => 'date',
            'date_drydocked' => 'date',
            'date_undocked' => 'date',
            'date_sail_out' => 'date',
            'loa' => 'decimal:2',
            'lbp' => 'decimal:2',
            'breadth' => 'decimal:2',
            'depth' => 'decimal:2',
            'approved_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
    }

    public function jobOrder(): HasOne
    {
        return $this->hasOne(JobOrder::class);
    }

    /** filled-in vessel particulars and dates as [label => "66.16 m" | "July 1, 2026"] */
    public function vesselDetails(): array
    {
        $out = [];
        foreach (self::VESSEL_FIELDS as [$field, $label, $suffix]) {
            $value = $this->{$field};
            if ($value === null || $value === '') {
                continue;
            }
            $out[$label] = $value instanceof \DateTimeInterface ? $value->format('F j, Y') : rtrim(rtrim((string) $value, '0'), '.').$suffix;
        }

        return $out;
    }

    public function isEditable(): bool
    {
        return $this->status === 'pending';
    }

    public function profit(): float
    {
        return (float) $this->grand_total - (float) $this->total_cost;
    }

    /** profit as a % of the grand total; null when the total is zero */
    public function margin(): ?float
    {
        return $this->grand_total > 0 ? $this->profit() / $this->grand_total * 100 : null;
    }

    /** everything the quotation and job order pages show */
    public function details(): array
    {
        $this->loadMissing('customer', 'lines', 'jobOrder.invoice.payments');
        $jo = $this->jobOrder;
        $invoice = $jo?->invoice;

        return [
            'id' => $this->id,
            'quotation_no' => $this->quotation_no,
            'status' => $this->status,
            'customer' => $this->customer->name,
            'contact_person' => $this->contact_person,
            'contact_no' => $this->contact_no,
            'address' => $this->address,
            'project' => $this->project,
            'vessel_name' => $this->vessel_name,
            'vessel' => $this->vesselDetails(),
            'date' => $this->date->format('F j, Y'),
            'created' => $this->created_at->format('M j, Y'),
            'approved' => $this->approved_at?->format('M j, Y'),
            'subtotal' => (float) $this->subtotal,
            'discount_percent' => (float) $this->discount_percent,
            'discount_amount' => (float) $this->discount_amount,
            'grand_total' => (float) $this->grand_total,
            'total_cost' => (float) $this->total_cost,
            'profit' => $this->profit(),
            'margin' => $this->margin(),
            'editable' => $this->isEditable(),
            'job_order' => $jo ? ['id' => $jo->id, 'jo_no' => $jo->jo_no, 'status' => $jo->status, 'status_label' => JobOrder::STATUSES[$jo->status]] : null,
            // for the Quotation → Job order → Invoice → Paid strip shown on all three pages
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'status' => $invoice->status(),
                'status_label' => Invoice::STATUSES[$invoice->status()],
                'paid_on' => $invoice->status() === 'paid' ? $invoice->payments->max('date')?->format('M j, Y') : null,
            ] : null,
            'lines' => $this->lines->map(fn ($l) => [
                'id' => $l->id,
                'type' => $l->type,
                'type_label' => Tariff::TYPES[$l->type],
                'section' => $l->section,
                'code' => $l->code,
                'description' => $l->description,
                'note' => $l->note,
                'uom' => $l->unitLabel(),
                'qty' => (float) $l->qty,
                'qty_label' => $l->qtyLabel(),
                'unit_price' => (float) $l->unit_price,
                'total' => (float) $l->total,
            ])->all(),
        ];
    }

    /** QT-2026-0001, counting again from 1 every year */
    public static function nextNumber(): string
    {
        $prefix = 'QT-'.now()->year.'-';
        $last = static::where('quotation_no', 'like', $prefix.'%')->max('quotation_no');

        return $prefix.str_pad((int) substr((string) $last, -4) + 1, 4, '0', STR_PAD_LEFT);
    }
}
