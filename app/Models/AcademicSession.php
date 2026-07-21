<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'promotion_pass_mark',
        'is_current',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'promotion_pass_mark' => 'decimal:2',
            'is_current' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function feeItems(): HasMany
    {
        return $this->hasMany(FeeItem::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function outgoingPromotions(): HasMany
    {
        return $this->hasMany(StudentPromotion::class, 'from_academic_session_id');
    }

    public function incomingPromotions(): HasMany
    {
        return $this->hasMany(StudentPromotion::class, 'to_academic_session_id');
    }
}
