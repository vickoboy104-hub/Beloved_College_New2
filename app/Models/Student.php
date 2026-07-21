<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parent_user_id',
        'admission_no',
        'student_id_no',
        'school_class_id',
        'academic_session_id',
        'boarding_status',
        'house',
        'gender',
        'date_of_birth',
        'place_of_birth',
        'nationality',
        'lga',
        'blood_group',
        'state_of_origin',
        'religion',
        'guardian_name',
        'guardian_phone',
        'parents_occupation',
        'office_residence_phone',
        'address',
        'previous_school',
        'previous_class',
        'medical_notes',
        'physical_notes',
        'doctor_name',
        'doctor_address',
        'doctor_phone',
        'enrolled_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'enrolled_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function assessmentResults(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function feeInvoices(): HasMany
    {
        return $this->hasMany(FeeInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function cbtAttempts(): HasMany
    {
        return $this->hasMany(CbtAttempt::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(StudentPromotion::class);
    }

    public function termReports(): HasMany
    {
        return $this->hasMany(StudentTermReport::class);
    }
}
