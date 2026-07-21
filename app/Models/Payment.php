<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'fee_invoice_id',
        'student_id',
        'provider',
        'reference',
        'receipt_no',
        'gateway_reference',
        'amount',
        'currency',
        'status',
        'channel',
        'paid_at',
        'recorded_by',
        'note',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function feeInvoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function allocateBundleInvoices(): void
    {
        $payload = $this->payload ?? [];
        $invoiceIds = collect(data_get($payload, 'invoice_ids', []))
            ->filter()
            ->map(fn (mixed $id) => (int) $id)
            ->unique()
            ->values();

        if ($invoiceIds->isEmpty() || data_get($payload, 'bundle_allocated')) {
            return;
        }

        $invoices = FeeInvoice::query()
            ->with('feeItem')
            ->where('student_id', $this->student_id)
            ->whereIn('id', $invoiceIds)
            ->get()
            ->sortBy(fn (FeeInvoice $invoice) => sprintf(
                '%s-%010d',
                optional($invoice->due_date)->format('Ymd') ?: '99999999',
                $invoice->id,
            ))
            ->values();

        $remaining = (float) $this->amount;
        $allocations = [];

        DB::transaction(function () use (&$remaining, &$allocations, $invoices): void {
            foreach ($invoices as $index => $invoice) {
                $invoice->refresh();

                if ($remaining <= 0 || (float) $invoice->balance <= 0) {
                    continue;
                }

                $applied = min((float) $invoice->balance, $remaining);

                static::query()->create([
                    'fee_invoice_id' => $invoice->id,
                    'student_id' => $invoice->student_id,
                    'provider' => $this->provider,
                    'reference' => $this->reference.'-'.($index + 1).'-'.Str::upper(Str::random(4)),
                    'receipt_no' => $this->receipt_no,
                    'gateway_reference' => $this->gateway_reference,
                    'amount' => $applied,
                    'currency' => $this->currency,
                    'status' => PaymentStatus::Paid,
                    'channel' => $this->channel,
                    'paid_at' => $this->paid_at ?? now(),
                    'recorded_by' => $this->recorded_by,
                    'note' => 'Allocated from grouped payment '.$this->reference,
                    'payload' => [
                        'source' => 'bundle_allocation',
                        'parent_payment_id' => $this->id,
                    ],
                ]);

                $invoice->refresh()->syncBalance();
                $invoice->refresh()->loadMissing('feeItem');

                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'fee_item' => $invoice->feeItem->name ?? 'School fee payment',
                    'amount_due' => (float) $invoice->amount_due,
                    'amount_paid_now' => $applied,
                    'amount_paid_total' => (float) $invoice->amount_paid,
                    'balance' => (float) $invoice->balance,
                    'status' => (string) $invoice->status,
                ];

                $remaining -= $applied;
            }
        });

        $payload['bundle_allocated'] = true;
        $payload['allocated_invoices'] = $allocations;

        $this->forceFill(['payload' => $payload])->save();
    }
}
