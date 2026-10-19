<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = ['cash' => 'Cash', 'check' => 'Check', 'bank' => 'Bank transfer', 'gcash' => 'GCash'];

    protected $fillable = ['invoice_id', 'date', 'amount', 'method', 'reference'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
