<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    public const TYPES = ['item' => 'Item', 'service' => 'Service'];

    protected $fillable = ['type', 'code', 'description', 'uom', 'unit', 'cost', 'price', 'active'];

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
