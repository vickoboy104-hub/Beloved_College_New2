<?php

namespace App\Services\Identity;

use App\Models\StaffProfile;
use App\Models\Student;
use Illuminate\Support\Str;

class CredentialGenerator
{
    public function temporaryPassword(): string
    {
        return Str::upper(Str::random(3)).'@'.Str::random(5);
    }

    public function admissionNumber(): string
    {
        do {
            $candidate = 'ADM-'.now()->format('y').'-'.Str::upper(Str::random(6));
        } while (Student::query()->where('admission_no', $candidate)->exists());

        return $candidate;
    }

    public function employeeNumber(): string
    {
        do {
            $candidate = 'STF-'.now()->format('y').'-'.Str::upper(Str::random(6));
        } while (StaffProfile::query()->where('employee_no', $candidate)->exists());

        return $candidate;
    }
}
