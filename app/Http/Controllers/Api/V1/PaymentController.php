<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\PortalSession;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $payments) {}

    public function index(Request $request)
    {
        $query = Payment::with(['student', 'officialReceipt'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if (PortalSession::role($request) === 'student') {
            $studentId = PortalSession::studentId($request);
            abort_unless($studentId, 403, 'Student account is not available.');
            $query->where('student_id', $studentId);
        }

        return PaymentResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function show(Request $request, Payment $payment)
    {
        $this->ensureVisibleToSession($request, $payment->student_id);

        return new PaymentResource($payment->load(['student', 'officialReceipt']));
    }

    public function store(Request $request)
    {
        $role = PortalSession::role($request) ?? 'student';
        $studentId = PortalSession::studentId($request);

        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'tuition_record_id' => 'nullable|exists:tuition_records,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'correlation_id' => 'nullable|uuid',
        ]);

        if ($role === 'student' && (int) $data['student_id'] !== (int) $studentId) {
            abort(403, 'Students can only submit payments for their own account.');
        }

        if ($role === 'student' && empty($data['tuition_record_id'])) {
            abort(422, 'Select the payable you are paying.');
        }

        if ($role === 'cashier') {
            abort(403, 'Cashiers create payables only. Students handle their own payments.');
        }

        $payment = $this->payments->submit(
            $data,
            PortalSession::portalId($request),
            $role
        );

        if ($role === 'student') {
            $payment = $this->payments->confirm($payment, PortalSession::portalId($request));
        }

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    public function update(Request $request, Payment $payment)
    {
        if (PortalSession::role($request) !== 'cashier') {
            abort(403, 'Only cashiers can modify payments.');
        }

        $data = $request->validate([
            'method' => 'sometimes|string|max:50',
            'reference_number' => 'sometimes|nullable|string|max:100',
            'amount' => 'sometimes|numeric|min:0.01',
        ]);

        $payment->update($data);

        return new PaymentResource($payment->fresh());
    }

    public function destroy(Request $request, Payment $payment)
    {
        if (PortalSession::role($request) !== 'cashier') {
            abort(403, 'Only cashiers can delete payments.');
        }

        $payment->delete();

        return response()->json(['success' => true]);
    }

    public function confirm(Request $request, Payment $payment)
    {
        abort(403, 'Payments are completed by the student payment flow.');
    }

    public function reverse(Request $request, Payment $payment)
    {
        if (PortalSession::role($request) !== 'cashier') {
            abort(403, 'Only cashiers can reverse payments.');
        }

        $data = $request->validate(['reason' => 'required|string|max:255']);
        $reversed = $this->payments->reverse($payment, PortalSession::portalId($request), $data['reason']);

        return new PaymentResource($reversed);
    }

    protected function ensureVisibleToSession(Request $request, int $studentId): void
    {
        if (PortalSession::role($request) !== 'student') {
            return;
        }

        abort_unless((int) PortalSession::studentId($request) === $studentId, 404);
    }
}
