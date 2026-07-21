<?php

namespace App\Http\Controllers;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\FeeInvoice;
use App\Models\Payment;
use App\Services\Payments\PaymentAccessService;
use App\Services\Payments\PaymentCheckoutService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Portal\PortalStudentResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PaymentController extends Controller
{
    public function index(
        Request $request,
        PortalStudentResolver $resolver,
        PaymentGatewayManager $gateways,
    ): View {
        $student = $resolver->resolve($request->user(), $request->query('student_id'));
        $student->loadMissing('user', 'schoolClass');

        return view('payments.portal', [
            'student' => $student,
            'children' => $request->user()->hasAnyRole(UserRole::Parent)
                ? $request->user()->children()->with(['user', 'schoolClass'])->whereNull('archived_at')->get()
                : collect([$student]),
            'invoices' => $student->feeInvoices()
                ->with(['feeItem.term.academicSession', 'payments'])
                ->latest('issued_at')
                ->get(),
            'payments' => $student->payments()
                ->with('feeInvoice.feeItem')
                ->where('status', PaymentStatus::Paid->value)
                ->latest('paid_at')
                ->get()
                ->reject(fn (Payment $payment) => data_get($payment->payload, 'source') === 'bundle_allocation')
                ->values(),
            'gateways' => $gateways->catalog(onlyAvailable: true),
        ]);
    }

    public function checkout(
        Request $request,
        FeeInvoice $invoice,
        string $provider,
        PaymentCheckoutService $checkout,
    ): RedirectResponse {
        $providerEnum = PaymentProvider::tryFrom($provider);
        abort_unless($providerEnum?->isOnline(), 404);
        $result = $checkout->startInvoice($request->user(), $invoice, $providerEnum);

        return redirect()->away($result['authorization_url']);
    }

    public function checkoutSelection(
        Request $request,
        string $provider,
        PaymentCheckoutService $checkout,
    ): RedirectResponse {
        $data = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['required', 'integer', 'exists:fee_invoices,id'],
        ]);
        $providerEnum = PaymentProvider::tryFrom($provider);
        abort_unless($providerEnum?->isOnline(), 404);
        $result = $checkout->startBundle(
            $request->user(),
            $data['invoice_ids'],
            $providerEnum,
        );

        return redirect()->away($result['authorization_url']);
    }

    public function callback(
        Request $request,
        string $provider,
        PaymentCheckoutService $checkout,
    ): View {
        $providerEnum = PaymentProvider::tryFrom($provider);
        abort_unless($providerEnum?->isOnline(), 404);
        $reference = collect([
            $request->string('reference')->toString(),
            $request->string('trxref')->toString(),
            $request->string('tx_ref')->toString(),
            $request->string('paymentReference')->toString(),
            $request->string('payment_reference')->toString(),
        ])->first(fn (string $value) => $value !== '');
        abort_if(blank($reference), 422, 'A payment reference is required.');

        try {
            $payment = $checkout->confirm($providerEnum, $reference, [
                'transaction_id' => $request->input('transaction_id') ?: $request->input('id'),
                'transaction_reference' => $request->input('transactionReference'),
            ]);
            $status = $payment->status === PaymentStatus::Paid ? 'paid' : 'failed';
        } catch (Throwable $exception) {
            report($exception);
            $payment = Payment::query()->where('reference', $reference)->first();
            $status = 'pending';
        }

        return view('payments.callback', [
            'payment' => $payment,
            'status' => $status,
            'provider' => $providerEnum,
        ]);
    }

    public function receipt(
        Request $request,
        Payment $payment,
        PaymentAccessService $access,
    ): View {
        $access->authorizePayment($request->user(), $payment);
        $payment->load([
            'student.user',
            'student.schoolClass',
            'feeInvoice.feeItem',
            'recorder',
        ]);

        return view('payments.receipt', compact('payment'));
    }
}
