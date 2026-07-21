<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'student_id',
        'graded_by',
        'status',
        'started_at',
        'expires_at',
        'submitted_at',
        'objective_score',
        'theory_score',
        'total_score',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'objective_score' => 'decimal:2',
            'theory_score' => 'decimal:2',
            'total_score' => 'decimal:2',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CbtAnswer::class)->orderBy('cbt_question_id');
    }

    public function syncScores(): void
    {
        $answers = $this->answers()->with('question')->get();

        $objectiveScore = (float) $answers
            ->filter(fn (CbtAnswer $answer) => $answer->question?->question_type === 'objective')
            ->sum(fn (CbtAnswer $answer) => (float) ($answer->awarded_score ?? 0));

        $theoryScore = (float) $answers
            ->filter(fn (CbtAnswer $answer) => $answer->question?->question_type === 'theory')
            ->sum(fn (CbtAnswer $answer) => (float) ($answer->awarded_score ?? 0));

        $theoryQuestionCount = $this->assessment->cbtQuestions()
            ->where('question_type', 'theory')
            ->count();

        $gradedTheoryCount = $answers
            ->filter(fn (CbtAnswer $answer) => $answer->question?->question_type === 'theory'
                && $answer->graded_at !== null)
            ->count();

        $status = $this->status;

        if ($this->submitted_at) {
            $status = $theoryQuestionCount > 0 && $gradedTheoryCount < $theoryQuestionCount
                ? 'submitted'
                : 'graded';
        }

        $total = $objectiveScore + $theoryScore;

        $this->forceFill([
            'objective_score' => $objectiveScore,
            'theory_score' => $theoryScore,
            'total_score' => $total,
            'status' => $status,
        ])->save();

        AssessmentResult::query()->updateOrCreate(
            [
                'assessment_id' => $this->assessment_id,
                'student_id' => $this->student_id,
            ],
            [
                'score' => $total,
                'grade' => null,
                'remark' => $status === 'graded'
                    ? 'CBT graded.'
                    : 'CBT submitted. Theory answers pending review.',
            ],
        );
    }
}
