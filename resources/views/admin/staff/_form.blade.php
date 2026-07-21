@php
    $staff = $staff ?? null;
    $staffUser = $staff?->user;
@endphp

<fieldset class="form-section">
    <legend>Staff identity</legend>
    <div class="form-grid form-grid-3">
        <label class="field-group">
            <span>First name</span>
            <input name="first_name" value="{{ old('first_name', $staffUser?->first_name) }}" required>
        </label>
        <label class="field-group">
            <span>Middle name</span>
            <input name="middle_name" value="{{ old('middle_name', $staffUser?->middle_name) }}">
        </label>
        <label class="field-group">
            <span>Last name</span>
            <input name="last_name" value="{{ old('last_name', $staffUser?->last_name) }}" required>
        </label>
        <label class="field-group">
            <span>Email</span>
            <input name="email" type="email" value="{{ old('email', $staffUser?->email) }}" required>
        </label>
        <label class="field-group">
            <span>Phone</span>
            <input name="phone" inputmode="tel" value="{{ old('phone', $staffUser?->phone) }}">
        </label>
        <label class="field-group">
            <span>Passport photograph</span>
            <input name="passport_photo" type="file" accept="image/*">
        </label>
        <label class="field-group">
            <span>Employee number</span>
            <input name="employee_no" value="{{ old('employee_no', $staff?->employee_no) }}" placeholder="Generated when left blank" {{ $staff ? 'required' : '' }}>
        </label>
        <label class="field-group">
            <span>Role</span>
            <select name="role" required>
                @foreach ([
                    'teacher' => 'Teacher',
                    'accountant' => 'Accountant',
                    'principal' => 'Principal',
                    'admin' => 'Admin',
                    'super_admin' => 'Super Admin',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', $staffUser?->role?->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <small>Role ceilings are enforced by the server. Principals cannot create Admins; only Super Admin can create another Super Admin.</small>
        </label>
        @if (! $staff)
            <label class="field-group">
                <span>Optional temporary password</span>
                <input name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Generated when left blank">
            </label>
        @endif
    </div>
</fieldset>

<fieldset class="form-section">
    <legend>Employment information</legend>
    <div class="form-grid form-grid-3">
        <label class="field-group">
            <span>Department</span>
            <input name="department" value="{{ old('department', $staff?->department) }}">
        </label>
        <label class="field-group">
            <span>Designation</span>
            <input name="designation" value="{{ old('designation', $staff?->designation) }}">
        </label>
        <label class="field-group">
            <span>Qualification</span>
            <input name="qualification" value="{{ old('qualification', $staff?->qualification) }}">
        </label>
        <label class="field-group">
            <span>Hire date</span>
            <input name="hire_date" type="date" value="{{ old('hire_date', $staff?->hire_date?->format('Y-m-d')) }}">
        </label>
        <label class="field-group">
            <span>Monthly salary</span>
            <input name="salary" type="number" min="0" step="0.01" inputmode="decimal" value="{{ old('salary', $staff?->salary) }}">
        </label>
        @if ($staff)
            <label class="field-group">
                <span>Status</span>
                <select name="status">
                    <option value="active" @selected(old('status', $staff->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $staff->status) === 'inactive')>Inactive</option>
                </select>
            </label>
        @endif
    </div>
</fieldset>
