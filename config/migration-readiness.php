<?php

return [
    'report_disk' => env('MIGRATION_REPORT_DISK', 'local'),
    'report_directory' => env('MIGRATION_REPORT_DIRECTORY', 'migration-reports'),
    'allow_production' => (bool) env('MIGRATION_READINESS_ALLOW_PRODUCTION', false),
    'source_connection' => env('LEGACY_DB_CONNECTION', 'legacy'),
    'target_connection' => env('MIGRATION_TARGET_CONNECTION', env('DB_CONNECTION', 'sqlite')),
    'expected_app_key_fingerprint' => env('MIGRATION_EXPECTED_APP_KEY_FINGERPRINT'),

    'legacy_connection' => [
        'driver' => env('LEGACY_DB_DRIVER', 'mysql'),
        'url' => env('LEGACY_DATABASE_URL'),
        'host' => env('LEGACY_DB_HOST', '127.0.0.1'),
        'port' => env('LEGACY_DB_PORT', '3306'),
        'database' => env('LEGACY_DB_DATABASE'),
        'username' => env('LEGACY_DB_USERNAME'),
        'password' => env('LEGACY_DB_PASSWORD'),
        'unix_socket' => env('LEGACY_DB_SOCKET', ''),
        'charset' => env('LEGACY_DB_CHARSET', 'utf8mb4'),
        'collation' => env('LEGACY_DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ],

    'identity_columns' => [
        'users' => ['email'],
        'students' => ['admission_no', 'student_id'],
        'staff_profiles' => ['employee_no'],
        'fee_invoices' => ['invoice_no'],
        'payments' => ['reference', 'receipt_no'],
    ],

    'file_column_pattern' => '/(^path$|_path$|_file$|file_path$|photo$|photo_path$|avatar_path$|passport_path$|video_path$|document_path$|receipt_path$)/i',
    'file_disks' => ['local', 'public'],
    'required_php_extensions' => ['bcmath', 'ctype', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml'],

    'required_acceptance_roles' => [
        'public_visitor' => 'Public Visitor',
        'student' => 'Student',
        'parent' => 'Parent',
        'teacher' => 'Teacher',
        'accountant' => 'Accountant',
        'principal' => 'Principal',
        'admin' => 'Admin',
        'super_admin' => 'Super Admin',
    ],

    'required_approvals' => [
        'technical_owner' => 'Technical Owner',
        'data_migration_owner' => 'Data Migration Owner',
        'school_operations_owner' => 'School Operations Owner',
        'finance_owner' => 'Finance Owner',
        'rollback_owner' => 'Rollback Owner',
    ],
];
