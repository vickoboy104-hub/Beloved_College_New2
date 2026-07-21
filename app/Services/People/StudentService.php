<?php

namespace App\Services\People;

use App\Data\GeneratedCredential;
use App\Data\StudentRegistrationResult;
use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\MandatoryInvoiceService;
use App\Services\Identity\CredentialGenerator;
use App\Services\Media\ProfileMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StudentService
{
    private const PROFILE_FIELDS = [
        'student_id_no',
        'school_class_id',
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
    ];

    public function __construct(
        private readonly CredentialGenerator $credentials,
        private readonly ParentAccountService $parents,
        private readonly MandatoryInvoiceService $mandatoryInvoices,
        private readonly ProfileMediaService $profileMedia,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): StudentRegistrationResult
    {
        return DB::transaction(function () use ($data): StudentRegistrationResult {
            $parentResult = $this->parents->sync($data);
            $temporaryPassword = filled($data['password'] ?? null)
                ? (string) $data['password']
                : $this->credentials->temporaryPassword();
            $name = $this->fullName($data);
            $admissionNumber = filled($data['admission_no'] ?? null)
                ? trim((string) $data['admission_no'])
                : $this->credentials->admissionNumber();
            $passport = $data['passport_photo'] ?? null;
            $avatarPath = $passport instanceof UploadedFile
                ? $this->profileMedia->store($passport, 'student-'.$admissionNumber)
                : null;

            $user = User::query()->create([
                'name' => $name,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'email' => filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null,
                'phone' => $data['phone'] ?? null,
                'role' => UserRole::Student,
                'status' => 'active',
                'password' => $temporaryPassword,
                'email_verified_at' => now(),
                'must_change_password' => true,
                'avatar_path' => $avatarPath,
            ]);

            $student = Student::query()->create([
                ...Arr::only($data, self::PROFILE_FIELDS),
                'user_id' => $user->id,
                'parent_user_id' => $parentResult['parent']?->id,
                'admission_no' => $admissionNumber,
                'academic_session_id' => AcademicSession::query()->where('is_current', true)->value('id'),
                'status' => 'active',
                'enrolled_at' => now()->toDateString(),
            ]);

            $this->mandatoryInvoices->syncForStudent($student);

            $credentials = [new GeneratedCredential(
                audience: 'student',
                name: $name,
                identifier: $admissionNumber,
                email: $user->email,
                temporaryPassword: $temporaryPassword,
            )];

            if ($parentResult['credential']) {
                $credentials[] = $parentResult['credential'];
            }

            return new StudentRegistrationResult($student->load('user', 'parent'), $credentials);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data): Student {
            $student->loadMissing('user', 'parent');
            $parentResult = $this->parents->sync($data, $student->parent);
            $passport = $data['passport_photo'] ?? null;
            $avatarPath = $student->user->avatar_path;

            if ($passport instanceof UploadedFile) {
                $avatarPath = $this->profileMedia->replace(
                    $avatarPath,
                    $passport,
                    'student-'.$student->admission_no,
                );
            }

            $student->user->update([
                'name' => $this->fullName($data),
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'email' => filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null,
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'] ?? $student->user->status,
                'avatar_path' => $avatarPath,
            ]);

            $student->update([
                ...Arr::only($data, self::PROFILE_FIELDS),
                'parent_user_id' => $parentResult['parent']?->id,
                'admission_no' => $data['admission_no'] ?? $student->admission_no,
                'status' => $data['status'] ?? $student->status,
            ]);

            $this->mandatoryInvoices->syncForStudent($student);

            return $student->fresh(['user', 'parent', 'schoolClass', 'academicSession']);
        });
    }

    public function resetTemporaryPassword(Student $student): GeneratedCredential
    {
        $temporaryPassword = $this->credentials->temporaryPassword();
        $student->loadMissing('user');
        $student->user->update([
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ]);

        return new GeneratedCredential(
            audience: 'student',
            name: $student->user->fullName(),
            identifier: $student->admission_no ?: ($student->student_id_no ?: 'No login ID'),
            email: $student->user->email,
            temporaryPassword: $temporaryPassword,
        );
    }

    public function archive(Student $student, User $actor, string $reason): Student
    {
        return DB::transaction(function () use ($student, $actor, $reason): Student {
            $values = [
                'status' => 'inactive',
                'archived_at' => now(),
                'archived_by' => $actor->id,
                'archive_reason' => $reason,
            ];

            $student->forceFill($values)->save();
            $student->user->forceFill($values)->save();

            return $student->fresh(['user']);
        });
    }

    public function restore(Student $student): Student
    {
        return DB::transaction(function () use ($student): Student {
            $values = [
                'status' => 'active',
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
            ];

            $student->forceFill($values)->save();
            $student->user->forceFill($values)->save();

            return $student->fresh(['user']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fullName(array $data): string
    {
        return collect([
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
        ])->filter()->implode(' ');
    }
}
