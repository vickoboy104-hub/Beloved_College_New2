<?php

return [
    'report_disk' => env('MIGRATION_REPORT_DISK', 'local'),
    'report_directory' => env('MIGRATION_REPORT_DIRECTORY', 'migration-reports'),

    'critical_tables' => [
        'users',
        'students',
        'staff_profiles',
        'school_classes',
        'subjects',
        'teacher_subject_assignments',
        'fee_items',
        'fee_invoices',
        'payments',
        'assessment_results',
        'student_term_reports',
        'attendance_records',
    ],

    'unique_identifiers' => [
        'users' => ['email'],
        'students' => ['admission_no', 'student_id_no'],
        'staff_profiles' => ['employee_no'],
        'fee_invoices' => ['invoice_no'],
        'payments' => ['reference', 'receipt_no', 'gateway_reference'],
    ],

    'foreign_keys' => [
        ['table' => 'students', 'column' => 'user_id', 'parent_table' => 'users'],
        ['table' => 'students', 'column' => 'parent_user_id', 'parent_table' => 'users', 'nullable' => true],
        ['table' => 'students', 'column' => 'school_class_id', 'parent_table' => 'school_classes', 'nullable' => true],
        ['table' => 'staff_profiles', 'column' => 'user_id', 'parent_table' => 'users'],
        ['table' => 'fee_invoices', 'column' => 'student_id', 'parent_table' => 'students'],
        ['table' => 'payments', 'column' => 'student_id', 'parent_table' => 'students'],
        ['table' => 'payments', 'column' => 'fee_invoice_id', 'parent_table' => 'fee_invoices', 'nullable' => true],
        ['table' => 'teacher_subject_assignments', 'column' => 'teacher_id', 'parent_table' => 'users'],
        ['table' => 'teacher_subject_assignments', 'column' => 'school_class_id', 'parent_table' => 'school_classes'],
        ['table' => 'teacher_subject_assignments', 'column' => 'subject_id', 'parent_table' => 'subjects'],
    ],

    'file_sources' => [
        ['table' => 'users', 'column' => 'avatar_path', 'disk' => 'local'],
        ['table' => 'website_media', 'column' => 'path', 'disk' => 'local'],
        ['table' => 'testimonials', 'column' => 'photo_path', 'disk' => 'local'],
        ['table' => 'lessons', 'column' => 'video_path', 'disk' => 'local'],
        ['table' => 'lessons', 'column' => 'note_images', 'disk' => 'local', 'json' => true],
        ['table' => 'assignments', 'column' => 'attachment_images', 'disk' => 'local', 'json' => true],
        ['table' => 'assignment_submissions', 'column' => 'file_paths', 'disk' => 'local', 'json' => true],
    ],
];
