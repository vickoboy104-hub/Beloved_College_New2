<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'cbt_attempt_id',
        'cbt_question_id',
        'selected_option_id',
        'answer_text',
        'is_correct',
        'awarded_score',
        'feedback',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'awarded_score' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CbtAttempt::class, 'cbt_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(CbtQuestion::class, 'cbt_question_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(CbtQuestionOption::class, 'selected_option_id');
    }
}
