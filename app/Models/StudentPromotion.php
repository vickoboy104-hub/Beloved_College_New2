<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPromotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'from_academic_session_id',
        'to_academic_session_id',
        'from_school_class_id',
        'to_school_class_id',
        'promotion_status',
        'promotion_threshold',
        'overall_percentage',
        'subject_total_percentage',
        'subject_count',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'promotion_threshold' => 'decimal:2',
            'overall_percentage' => 'decimal:2',
            'subject_total_percentage' => 'decimal:2',
            'subject_count' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fromAcademicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'from_academic_session_id');
    }

    public function toAcademicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'to_academic_session_id');
    }

    public function fromSchoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'from_school_class_id');
    }

    public function toSchoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'to_school_class_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
