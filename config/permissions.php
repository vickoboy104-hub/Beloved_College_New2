<?php

use App\Enums\Permission;
use App\Enums\UserRole;

$allPermissions = array_map(
    fn (Permission $permission) => $permission->value,
    Permission::cases(),
);

return [
    'role_defaults' => [
        UserRole::SuperAdmin->value => $allPermissions,

        UserRole::Admin->value => array_values(array_diff($allPermissions, [
            Permission::ManageSuperAdmins->value,
        ])),

        UserRole::Principal->value => [
            Permission::ManageStudents->value,
            Permission::ManageParents->value,
            Permission::ManageStaff->value,
            Permission::ViewMedicalRecords->value,
            Permission::ManageTeacherAccess->value,
            Permission::ManageAcademicStructure->value,
            Permission::ManageSessions->value,
            Permission::ProcessPromotions->value,
            Permission::ManageAnnouncements->value,
            Permission::PublishLessons->value,
            Permission::ManageAssignments->value,
            Permission::GradeAssignments->value,
            Permission::RecordAttendance->value,
            Permission::RecordResults->value,
            Permission::ReviewReports->value,
            Permission::PublishReports->value,
            Permission::ManageCbt->value,
            Permission::ManageFinance->value,
            Permission::RecordPayments->value,
        ],

        UserRole::Accountant->value => [
            Permission::ManageFinance->value,
            Permission::RecordPayments->value,
        ],

        UserRole::Teacher->value => [
            Permission::PublishLessons->value,
            Permission::ManageAssignments->value,
            Permission::GradeAssignments->value,
            Permission::RecordAttendance->value,
            Permission::RecordResults->value,
            Permission::ManageCbt->value,
        ],

        UserRole::Parent->value => [
            Permission::UseParentPortal->value,
            Permission::PayInvoices->value,
        ],

        UserRole::Student->value => [
            Permission::UseStudentPortal->value,
            Permission::PayInvoices->value,
            Permission::TakeCbt->value,
        ],
    ],
];
