<?php

namespace App\Enums;

enum Permission: string
{
    case ManageSuperAdmins = 'system.manage_super_admins';
    case ManageUsers = 'identity.manage_users';
    case ManageRoles = 'identity.manage_roles';
    case ManagePermissions = 'identity.manage_permissions';
    case ManageStudents = 'people.manage_students';
    case ManageParents = 'people.manage_parents';
    case ManageStaff = 'people.manage_staff';
    case ViewMedicalRecords = 'people.view_medical_records';
    case ManageTeacherAccess = 'academics.manage_teacher_access';
    case ManageAcademicStructure = 'academics.manage_structure';
    case ManageSessions = 'academics.manage_sessions';
    case ProcessPromotions = 'academics.process_promotions';
    case ManageAnnouncements = 'communication.manage_announcements';
    case PublishLessons = 'learning.publish_lessons';
    case ManageAssignments = 'learning.manage_assignments';
    case GradeAssignments = 'learning.grade_assignments';
    case RecordAttendance = 'learning.record_attendance';
    case RecordResults = 'results.record_results';
    case ReviewReports = 'results.review_reports';
    case PublishReports = 'results.publish_reports';
    case ManageCbt = 'cbt.manage';
    case TakeCbt = 'cbt.take';
    case ManageFinance = 'finance.manage';
    case RecordPayments = 'finance.record_payments';
    case ConfigurePaymentGateways = 'finance.configure_gateways';
    case PayInvoices = 'finance.pay_invoices';
    case ManageWebsite = 'website.manage_content';
    case ManageThemes = 'website.manage_themes';
    case ManageSettings = 'system.manage_settings';
    case ViewAuditLogs = 'system.view_audit_logs';
    case ManageBackups = 'system.manage_backups';
    case UseStudentPortal = 'portal.student';
    case UseParentPortal = 'portal.parent';

    public function label(): string
    {
        return str($this->value)
            ->after('.')
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }
}
