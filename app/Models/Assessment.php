<?php

namespace App\Models;

use App\Enums\AssessmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'term_id',
        'subject_id',
        'school_class_id',
        'title',
        'type',
        'is_cbt',
        'total_score',
        'cbt_duration_minutes',
        'scheduled_at',
        'notes',
        'cbt_starts_at',
        'cbt_ends_at',
        'cbt_instructions',
        'cbt_is_active',
        'cbt_show_results',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssessmentType::class,
            'is_cbt' => 'boolean',
            'total_score' => 'decimal:2',
            'cbt_duration_minutes' => 'integer',
            'scheduled_at' => 'datetime',
            'cbt_starts_at' => 'datetime',
            'cbt_ends_at' => 'datetime',
            'cbt_is_active' => 'boolean',
            'cbt_show_results' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }

    public function cbtQuestions(): HasMany
    {
        return $this->hasMany(CbtQuestion::class)->orderBy('sort_order');
    }

    public function cbtAttempts(): HasMany
    {
        return $this->hasMany(CbtAttempt::class);
    }

    public function syncCbtTotalScore(): void
    {
        $this->forceFill([
            'total_score' => (float) $this->cbtQuestions()->sum('points'),
        ])->save();
    }
}
