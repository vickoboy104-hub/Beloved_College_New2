@php
    $student = $student ?? null;
    $user = $student?->user;
    $parent = $student?->parent;
@endphp

<fieldset class="form-section">
    <legend>Student identity</legend>
    <div class="form-grid form-grid-3">
        <label class="field-group">
            <span>First name</span>
            <input name="first_name" value="{{ old('first_name', $user?->first_name) }}" required>
        </label>
        <label class="field-group">
            <span>Middle name</span>
            <input name="middle_name" value="{{ old('middle_name', $user?->middle_name) }}">
        </label>
        <label class="field-group">
            <span>Last name</span>
            <input name="last_name" value="{{ old('last_name', $user?->last_name) }}" required>
        </label>
        <label class="field-group">
            <span>Email</span>
            <input name="email" type="email" value="{{ old('email', $user?->email) }}">
        </label>
        <label class="field-group">
            <span>Phone</span>
            <input name="phone" inputmode="tel" value="{{ old('phone', $user?->phone) }}">
        </label>
        <label class="field-group">
            <span>Passport photograph</span>
            <input name="passport_photo" type="file" accept="image/*">
        </label>
        <label class="field-group">
            <span>Admission number</span>
            <input name="admission_no" value="{{ old('admission_no', $student?->admission_no) }}" placeholder="Generated when left blank" {{ $student ? 'required' : '' }}>
        </label>
        <label class="field-group">
            <span>Student ID</span>
            <input name="student_id_no" value="{{ old('student_id_no', $student?->student_id_no) }}">
        </label>
        <label class="field-group">
            <span>Class</span>
            <select name="school_class_id">
                <option value="">Unassigned</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected((string) old('school_class_id', $student?->school_class_id) === (string) $class->id)>
                        {{ $class->display_name }}
                    </option>
                @endforeach
            </select>
        </label>
        <label class="field-group">
            <span>Gender</span>
            <select name="gender">
                <option value="">Not specified</option>
                @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('gender', $student?->gender) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="field-group">
            <span>Date of birth</span>
            <input name="date_of_birth" type="date" value="{{ old('date_of_birth', $student?->date_of_birth?->format('Y-m-d')) }}">
        </label>
        <label class="field-group">
            <span>Place of birth</span>
            <input name="place_of_birth" value="{{ old('place_of_birth', $student?->place_of_birth) }}">
        </label>
    </div>
</fieldset>

<fieldset class="form-section">
    <legend>Background and placement</legend>
    <div class="form-grid form-grid-3">
        <label class="field-group">
            <span>Nationality</span>
            <input name="nationality" value="{{ old('nationality', $student?->nationality) }}">
        </label>
        <label class="field-group">
            <span>State of origin</span>
            <input name="state_of_origin" value="{{ old('state_of_origin', $student?->state_of_origin) }}">
        </label>
        <label class="field-group">
            <span>Local government area</span>
            <input name="lga" value="{{ old('lga', $student?->lga) }}">
        </label>
        <label class="field-group">
            <span>Religion</span>
            <input name="religion" value="{{ old('religion', $student?->religion) }}">
        </label>
        <label class="field-group">
            <span>Boarding status</span>
            <input name="boarding_status" value="{{ old('boarding_status', $student?->boarding_status) }}">
        </label>
        <label class="field-group">
            <span>House</span>
            <input name="house" value="{{ old('house', $student?->house) }}">
        </label>
        <label class="field-group">
            <span>Previous school</span>
            <input name="previous_school" value="{{ old('previous_school', $student?->previous_school) }}">
        </label>
        <label class="field-group">
            <span>Previous class</span>
            <input name="previous_class" value="{{ old('previous_class', $student?->previous_class) }}">
        </label>
        <label class="field-group">
            <span>Blood group</span>
            <input name="blood_group" value="{{ old('blood_group', $student?->blood_group) }}">
        </label>
    </div>
</fieldset>

<fieldset class="form-section">
    <legend>Parent and guardian</legend>
    <div class="form-grid form-grid-3">
        <label class="field-group">
            <span>Parent account name</span>
            <input name="parent_name" value="{{ old('parent_name', $parent?->name) }}">
        </label>
        <label class="field-group">
            <span>Parent email</span>
            <input name="parent_email" type="email" value="{{ old('parent_email', $parent?->email) }}">
        </label>
        <label class="field-group">
            <span>Parent phone</span>
            <input name="parent_phone" inputmode="tel" value="{{ old('parent_phone', $parent?->phone) }}">
        </label>
        <label class="field-group">
            <span>Guardian name</span>
            <input name="guardian_name" value="{{ old('guardian_name', $student?->guardian_name) }}">
        </label>
        <label class="field-group">
            <span>Guardian phone</span>
            <input name="guardian_phone" inputmode="tel" value="{{ old('guardian_phone', $student?->guardian_phone) }}">
        </label>
        <label class="field-group">
            <span>Parents' occupation</span>
            <input name="parents_occupation" value="{{ old('parents_occupation', $student?->parents_occupation) }}">
        </label>
        <label class="field-group">
            <span>Office/residence phone</span>
            <input name="office_residence_phone" inputmode="tel" value="{{ old('office_residence_phone', $student?->office_residence_phone) }}">
        </label>
        <label class="field-group form-span-2">
            <span>Home address</span>
            <textarea name="address" rows="3">{{ old('address', $student?->address) }}</textarea>
        </label>
    </div>
</fieldset>

<fieldset class="form-section">
    <legend>Health and physical information</legend>
    <div class="form-grid form-grid-2">
        <label class="field-group">
            <span>Doctor's name</span>
            <input name="doctor_name" value="{{ old('doctor_name', $student?->doctor_name) }}">
        </label>
        <label class="field-group">
            <span>Doctor's phone</span>
            <input name="doctor_phone" inputmode="tel" value="{{ old('doctor_phone', $student?->doctor_phone) }}">
        </label>
        <label class="field-group form-span-full">
            <span>Doctor's address</span>
            <textarea name="doctor_address" rows="2">{{ old('doctor_address', $student?->doctor_address) }}</textarea>
        </label>
        <label class="field-group">
            <span>Medical notes</span>
            <textarea name="medical_notes" rows="4">{{ old('medical_notes', $student?->medical_notes) }}</textarea>
        </label>
        <label class="field-group">
            <span>Physical notes</span>
            <textarea name="physical_notes" rows="4">{{ old('physical_notes', $student?->physical_notes) }}</textarea>
        </label>
    </div>
</fieldset>
