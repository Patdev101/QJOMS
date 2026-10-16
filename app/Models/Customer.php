<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'address', 'contact_person', 'contact_no', 'discount_percent'];

    protected function casts(): array
    {
        return ['discount_percent' => 'decimal:2'];
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /** "ABC Construction" → "AC", shown in the customer's avatar */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->join('');
    }

    /** the same customer always gets the same avatar color */
    public function avatarColor(): string
    {
        $colors = ['#2b6450', '#b8492f', '#a8701c', '#6b4a7a', '#5d6b2f', '#7a5a3c'];

        return $colors[crc32($this->name) % count($colors)];
    }
}
