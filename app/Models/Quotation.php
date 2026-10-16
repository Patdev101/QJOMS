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
        'subtotal', 'discount_percent', 'discount_amount', 'grand_total', 'total_cost', 'status', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
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

    /** QT-2026-0001, counting again from 1 every year */
    public static function nextNumber(): string
    {
        $prefix = 'QT-'.now()->year.'-';
        $last = static::where('quotation_no', 'like', $prefix.'%')->max('quotation_no');

        return $prefix.str_pad((int) substr((string) $last, -4) + 1, 4, '0', STR_PAD_LEFT);
    }
}
