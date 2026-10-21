<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    public const TYPES = ['item' => 'Item', 'service' => 'Service'];

    // uom2: the second part of a unit like "Pax / Day"; on a quotation both quantities are multiplied
    // section: the main section of the tariff sheet ("B. GENERAL RELATED SERVICES"), used for the printout summary
    protected $fillable = ['type', 'section', 'category', 'subcategory', 'code', 'description', 'uom', 'uom2', 'unit', 'cost', 'price', 'active'];

    /** "Pax / Day" when the unit has two parts, otherwise just the UOM */
    public static function unitLabel(?string $uom, ?string $uom2): string
    {
        return $uom2 ? "{$uom} / {$uom2}" : (string) $uom;
    }

    /**
     * A unit that is a block of hours ("8 Hrs", "24 Hrs", "8Hrs") gives its size (8, 24); anything else null.
     * On a quotation that box takes hours, so 16 hours of an "8 Hrs" unit is 2 blocks.
     * A plain "Hr" is already counted in hours and needs no conversion.
     */
    public static function hoursPerUnit(?string $unit): ?float
    {
        return preg_match('/^(\d+(?:\.\d+)?)\s*hrs?$/i', trim((string) $unit), $m) && (float) $m[1] > 0 ? (float) $m[1] : null;
    }

    /** how many priced units a typed quantity is: hours ÷ block size for hour blocks, otherwise the quantity itself */
    public static function units(?string $unit, float $typed): float
    {
        $hours = self::hoursPerUnit($unit);

        return $hours ? $typed / $hours : $typed;
    }

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /** profit as a % of the selling price; null when there is no price */
    public function margin(): ?float
    {
        return $this->price > 0 ? ($this->price - $this->cost) / $this->price * 100 : null;
    }
}
