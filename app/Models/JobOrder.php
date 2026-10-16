<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOrder extends Model
{
    public const STATUSES = ['open' => 'Open', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'billed' => 'Billed'];

    protected $fillable = ['jo_no', 'date', 'quotation_id', 'status'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** JO-2026-0001, counting again from 1 every year */
    public static function nextNumber(): string
    {
        $prefix = 'JO-'.now()->year.'-';
        $last = static::where('jo_no', 'like', $prefix.'%')->max('jo_no');

        return $prefix.str_pad((int) substr((string) $last, -4) + 1, 4, '0', STR_PAD_LEFT);
    }
}
