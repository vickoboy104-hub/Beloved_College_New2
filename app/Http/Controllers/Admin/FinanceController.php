<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeInvoice;
use App\Models\FeeItem;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Services\Finance\FinanceDirectoryService;
use App\Services\Finance\FinanceService;
use App\Services\Payments\PaymentAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request, FinanceDirectoryService $directory): View
    {
        $allowed = [
            'overview',
            'fee-items',
            'invoices',
            'manual-payment',
            'student-balances',
            'class-bills',
            'payment-summary',
            'recent-payments',
            'overpayments',
            'payment-progression',
        ];
        $section = in_array($request->query('section'), $allowed, true)
            ? $request->query('section')
            : 'overview';

        return view('admin.finance.index', [
            ...$directory->workspace($request->only('student_search')),
            'activeSection' => $section,
            'filters' => $request->only('student_search'),
        ]);
    }

    public function storeFeeItem(Request $request, FinanceService $finance): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'academic_session_id' => ['nullable', 'exists:academic_sessions,id'],
            'term_id' => ['nullable', 'exists:terms,id'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_mandatory' => ['nullable', 'boolean'],
        ]);

        $finance->createFeeItem([
            ...$data,
            'is_mandatory' => $request->boolean('is_mandatory', true),
        ]);

        return back()->with('status', 'Fee item created successfully.');
    }

    public function destroyFeeItem(FeeItem $feeItem, FinanceService $finance): RedirectResponse
    {
        $name = $feeItem->name;
        $finance->deleteUnusedFeeItem($feeItem);

        return back()->with('status', $name.' deleted successfully.');
    }

    public function generateInvoices(Request $request, FinanceService $finance): RedirectResponse
    {
        $data = $request->validate([
            'fee_item_id' => ['required', 'exists:fee_items,id'],
            'student_id' => ['nullable', 'exists:students,id', 'required_without:school_class_id'],
            'school_class_id' => ['nullable', 'exists:school_classes,id', 'required_without:student_id'],
            'amount_due' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $result = $finance->generateInvoices(
            FeeItem::query()->findOrFail($data['fee_item_id']),
            isset($data['student_id']) ? (int) $data['student_id'] : null,
            isset($data['school_class_id']) ? (int) $data['school_class_id'] : null,
            isset($data['amount_due']) ? (float) $data['amount_due'] : null,
            $data['due_date'] ?? null,
            $data['notes'] ?? null,
        );

        return back()->with(
            'status',
            $result['created'].' invoice(s) created; '.$result['skipped'].' existing invoice(s) skipped.',
        );
    }

    public function recordManualPayment(Request $request, FinanceService $finance): RedirectResponse
    {
        $data = $request->validate([
            'fee_invoice_id' => ['required', 'exists:fee_invoices,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'channel' => ['required', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $payment = $finance->recordManualPayment(
            $request->user(),
            FeeInvoice::query()->findOrFail($data['fee_invoice_id']),
            $data,
        );

        return redirect()
            ->route('web.admin.finance.receipt', $payment)
            ->with('status', 'Manual payment recorded successfully.');
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

    public function printableFeeList(Request $request): View
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:school_classes,id'],
            'fee_item_ids' => ['nullable', 'array'],
            'fee_item_ids.*' => ['integer', 'exists:fee_items,id'],
        ]);
        $class = SchoolClass::query()->findOrFail($data['class_id']);
        $ids = collect($data['fee_item_ids'] ?? [])->map(fn (mixed $id) => (int) $id);
        $items = FeeItem::query()
            ->with(['term', 'schoolClass'])
            ->where(fn ($query) => $query->whereNull('school_class_id')->orWhere('school_class_id', $class->id))
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('id', $ids))
            ->get()
            ->sortBy(fn (FeeItem $item) => ($item->term?->name ?? 'ZZZ').' '.$item->name)
            ->values();

        return view('admin.finance.printable-fee-list', [
            'schoolClass' => $class,
            'feeItems' => $items,
            'total' => (float) $items->sum('amount'),
        ]);
    }
}
