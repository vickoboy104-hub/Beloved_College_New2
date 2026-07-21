<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ArchivePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('identity.manage_users')
            || $this->user()?->hasPermission('people.manage_students')
            || $this->user()?->hasPermission('people.manage_staff');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
