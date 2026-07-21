<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTermReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'term_id',
        'academic_session_id',
        'school_class_id',
        'days_school_open',
        'days_present',
        'days_absent',
        'next_term_begins_on',
        'character_traits',
        'practical_skills',
        'class_teacher_remark',
        'guidance_remark',
        'principal_remark',
        'house_master_remark',
        'overall_grade',
        'average_score',
        'total_score',
        'subject_count',
        'class_position',
        'portal_enabled',
        'checker_enabled',
        'checker_pin_hash',
        'metadata',
        'approved_by',
        'approved_at',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'days_school_open' => 'integer',
            'days_present' => 'integer',
            'days_absent' => 'integer',
            'next_term_begins_on' => 'date',
            'character_traits' => 'array',
            'practical_skills' => 'array',
            'average_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'subject_count' => 'integer',
            'class_position' => 'integer',
            'portal_enabled' => 'boolean',
            'checker_enabled' => 'boolean',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
