<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no',
        'student_id',
        'fee_item_id',
        'amount_due',
        'amount_paid',
        'balance',
        'due_date',
        'status',
        'issued_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'due_date' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function syncBalance(): void
    {
        $paid = (float) $this->payments()
            ->where('status', 'paid')
            ->sum('amount');
        $amountDue = (float) $this->amount_due;
        $balance = max($amountDue - $paid, 0);

        $this->forceFill([
            'amount_paid' => $paid,
            'balance' => $balance,
            'status' => $balance <= 0
                ? 'paid'
                : ($paid > 0 ? 'part-paid' : 'unpaid'),
        ])->save();
    }
}
