<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationLine extends Model
{
    protected $fillable = ['tariff_id', 'type', 'section', 'code', 'description', 'note', 'uom', 'uom2', 'qty', 'qty2', 'unit_cost', 'unit_price', 'total'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'qty2' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /** "Pax / Day" for a two-part unit */
    public function unitLabel(): string
    {
        return Tariff::unitLabel($this->uom, $this->uom2);
    }

    /** "12 × 30" for a two-part unit, otherwise "12"; hour blocks ("8 Hrs") were typed in hours: "16 hrs × 10" */
    public function qtyLabel(): string
    {
        $part = fn ($n, $unit) => self::number($n).(Tariff::hoursPerUnit($unit) ? ' hrs' : '');

        return $this->uom2 && $this->qty2 !== null ? $part($this->qty, $this->uom).' × '.$part($this->qty2, $this->uom2) : $part($this->qty, $this->uom);
    }

    /** one quantity with its unit for the printout: "16 hrs" for an hour block, "12 Pax", "3 Sqm" */
    public static function quantityWithUnit($n, ?string $unit): string
    {
        return trim(self::number($n).' '.(Tariff::hoursPerUnit($unit) ? 'hrs' : (string) $unit));
    }

    private static function number($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.');
    }

    /** the tariff it was picked from (gone if the tariff was deleted) — used to group the printout by category */
    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
