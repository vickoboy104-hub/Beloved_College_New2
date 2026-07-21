<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('people.manage_students') ?? false;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'admission_no' => ['nullable', 'string', 'max:255', 'unique:students,admission_no'],
            'student_id_no' => ['nullable', 'string', 'max:255', 'unique:students,student_id_no'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:255'],
            'passport_photo' => ['nullable', 'image', 'max:51200'],
            'boarding_status' => ['nullable', 'string', 'max:255'],
            'house' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'lga' => ['nullable', 'string', 'max:255'],
            'blood_group' => ['nullable', 'string', 'max:50'],
            'state_of_origin' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:255'],
            'parents_occupation' => ['nullable', 'string', 'max:255'],
            'office_residence_phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'previous_class' => ['nullable', 'string', 'max:255'],
            'medical_notes' => ['nullable', 'string', 'max:5000'],
            'physical_notes' => ['nullable', 'string', 'max:5000'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'doctor_address' => ['nullable', 'string', 'max:2000'],
            'doctor_phone' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function payload(): array
    {
        return [
            ...$this->validated(),
            'passport_photo' => $this->file('passport_photo'),
        ];
    }
}
