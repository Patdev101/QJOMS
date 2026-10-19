<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STATUSES = ['unpaid' => 'Unpaid', 'partial' => 'Partly paid', 'paid' => 'Paid'];

    protected $fillable = ['invoice_no', 'job_order_id', 'date', 'due_date', 'amount', 'notes'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paid(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function balance(): float
    {
        return round((float) $this->amount - $this->paid(), 2);
    }

    /** unpaid / partial / paid, worked out from the payments */
    public function status(): string
    {
        $paid = $this->paid();

        return $paid <= 0 ? 'unpaid' : ($this->balance() <= 0 ? 'paid' : 'partial');
    }

    public function isOverdue(): bool
    {
        return $this->status() !== 'paid' && $this->due_date->lt(today());
    }

    /** INV-2026-0001, counting again from 1 every year */
    public static function nextNumber(): string
    {
        $prefix = 'INV-'.now()->year.'-';
        $last = static::where('invoice_no', 'like', $prefix.'%')->max('invoice_no');

        return $prefix.str_pad((int) substr((string) $last, -4) + 1, 4, '0', STR_PAD_LEFT);
    }
}
