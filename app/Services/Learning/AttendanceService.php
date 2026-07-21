<?php

namespace App\Services\Learning;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\TeacherAccessService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private readonly TeacherAccessService $teacherAccess) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(User $teacher, Student $student, array $data): AttendanceRecord
    {
        $classId = (int) $data['school_class_id'];
        $this->teacherAccess->authorizeClass($teacher, $classId);

        if ((int) $student->school_class_id !== $classId) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected student does not belong to this class.',
            ]);
        }

        $status = $data['status'] instanceof AttendanceStatus
            ? $data['status']
            : AttendanceStatus::from((string) $data['status']);

        return AttendanceRecord::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'attendance_date' => $data['attendance_date'],
            ],
            [
                'school_class_id' => $classId,
                'taken_by' => $teacher->id,
                'status' => $status,
                'note' => $data['note'] ?? null,
            ],
        );
    }

    /**
     * @param  array<int|string, array{status: string, note?: string|null}>  $records
     * @return Collection<int, AttendanceRecord>
     */
    public function recordBulk(
        User $teacher,
        int $schoolClassId,
        string $attendanceDate,
        array $records,
    ): Collection {
        $this->teacherAccess->authorizeClass($teacher, $schoolClassId);
        $studentIds = collect(array_keys($records))->map(fn (mixed $id) => (int) $id)->values();
        $students = Student::query()
            ->where('school_class_id', $schoolClassId)
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        if ($students->count() !== $studentIds->unique()->count()) {
            throw ValidationException::withMessages([
                'records' => 'One or more attendance entries do not belong to the selected class.',
            ]);
        }

        return DB::transaction(function () use (
            $teacher,
            $schoolClassId,
            $attendanceDate,
            $records,
            $students,
        ): Collection {
            return $students->map(function (Student $student) use (
                $teacher,
                $schoolClassId,
                $attendanceDate,
                $records,
            ): AttendanceRecord {
                $row = $records[$student->id];

                return AttendanceRecord::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_date' => $attendanceDate,
                    ],
                    [
                        'school_class_id' => $schoolClassId,
                        'taken_by' => $teacher->id,
                        'status' => AttendanceStatus::from($row['status']),
                        'note' => $row['note'] ?? null,
                    ],
                );
            })->values();
        });
    }
}
