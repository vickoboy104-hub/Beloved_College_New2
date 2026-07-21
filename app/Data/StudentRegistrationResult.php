<?php

namespace App\Data;

use App\Models\Student;

readonly class StudentRegistrationResult
{
    /**
     * @param  array<int, GeneratedCredential>  $credentials
     */
    public function __construct(
        public Student $student,
        public array $credentials,
    ) {}
}
