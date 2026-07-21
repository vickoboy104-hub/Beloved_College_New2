<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'school_class_id',
        'title',
        'instructions',
        'attachment_images',
        'due_date',
        'total_score',
        'status',
        'allowed_submission_types',
        'max_submission_files',
    ];

    protected function casts(): array
    {
        return [
            'attachment_images' => 'array',
            'due_date' => 'datetime',
            'total_score' => 'decimal:2',
            'allowed_submission_types' => 'array',
            'max_submission_files' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function accepts(string $type): bool
    {
        $types = $this->allowed_submission_types ?: ['text'];

        return in_array($type, $types, true);
    }
}
