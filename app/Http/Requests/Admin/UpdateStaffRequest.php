<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\StaffProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('people.manage_staff') ?? false;
    }

    public function rules(): array
    {
        /** @var StaffProfile $staff */
        $staff = $this->route('staff');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->user_id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in([
                UserRole::SuperAdmin->value,
                UserRole::Admin->value,
                UserRole::Principal->value,
                UserRole::Accountant->value,
                UserRole::Teacher->value,
            ])],
            'employee_no' => ['required', 'string', 'max:255', Rule::unique('staff_profiles', 'employee_no')->ignore($staff->id)],
            'department' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'passport_photo' => ['nullable', 'image', 'max:51200'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
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
