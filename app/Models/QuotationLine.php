<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationLine extends Model
{
    protected $fillable = ['tariff_id', 'type', 'code', 'description', 'uom', 'qty', 'unit_cost', 'unit_price', 'total'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
