<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** one line in the history: what happened, to what, and when */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['subject_type', 'subject_id', 'quotation_id', 'action', 'description', 'user_name', 'created_at'];

    /** record an event for a quotation, job order or invoice */
    public static function log(Model $subject, string $action, string $description): void
    {
        [$type, $quotationId] = match (true) {
            $subject instanceof Quotation => ['quotation', $subject->id],
            $subject instanceof JobOrder => ['job_order', $subject->quotation_id],
            $subject instanceof Invoice => ['invoice', $subject->jobOrder->quotation_id],
        };

        static::create([
            'subject_type' => $type,
            'subject_id' => $subject->id,
            'quotation_id' => $quotationId,
            'action' => $action,
            'description' => $description,
            'user_name' => auth()->user()?->name, // who did it
        ]);
    }

    /** the whole story of one quotation (its job order and invoice included), newest first */
    public static function timeline(int $quotationId): array
    {
        return static::where('quotation_id', $quotationId)->latest('id')->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'description' => $a->description,
                'user' => $a->user_name,
                'when' => $a->created_at->format('M j, Y g:i A'),
                'ago' => $a->created_at->diffForHumans(),
            ])->all();
    }
}
