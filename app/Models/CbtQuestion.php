<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'question_type',
        'prompt',
        'points',
        'image_paths',
        'video_path',
        'video_url',
        'resource_link',
        'theory_sample_answer',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'image_paths' => 'array',
            'points' => 'decimal:2',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(CbtQuestionOption::class)->orderBy('sort_order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CbtAnswer::class);
    }
}
