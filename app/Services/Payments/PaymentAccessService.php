<?php

namespace App\Services\Payments;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\FeeInvoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class PaymentAccessService
{
    public function authorizeInvoice(User $user, FeeInvoice $invoice): void
    {
        $invoice->loadMissing('student.user', 'student.parent');

        if ($user->hasPermission(Permission::ManageFinance)
            || $user->hasPermission(Permission::RecordPayments)) {
            return;
        }

        if (! $user->hasPermission(Permission::PayInvoices)) {
            throw new AuthorizationException('You are not allowed to pay school invoices.');
        }

        if ($user->hasAnyRole(UserRole::Student)
            && $invoice->student->user_id === $user->id) {
            return;
        }

        if ($user->hasAnyRole(UserRole::Parent)
            && $invoice->student->parent_user_id === $user->id) {
            return;
        }

        throw new AuthorizationException('This invoice does not belong to your account.');
    }

    public function authorizePayment(User $user, Payment $payment): void
    {
        $payment->loadMissing('student.user', 'student.parent');

        if ($user->hasPermission(Permission::ManageFinance)
            || $user->hasPermission(Permission::RecordPayments)) {
            return;
        }

        if ($user->hasAnyRole(UserRole::Student)
            && $payment->student->user_id === $user->id) {
            return;
        }

        if ($user->hasAnyRole(UserRole::Parent)
            && $payment->student->parent_user_id === $user->id) {
            return;
        }

        throw new AuthorizationException('This payment does not belong to your account.');
    }
}
