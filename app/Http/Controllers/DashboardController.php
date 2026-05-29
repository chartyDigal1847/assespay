<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Balance;
use App\Models\OfficialReceipt;
use App\Models\Payment;
use App\Models\Student;
use App\Models\TuitionRecord;
use App\Services\BalanceService;
use App\Services\BillingAccountService;
use App\Services\DeorisStudentDirectory;
use App\Services\PortalUserService;
use App\Support\PortalSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function __construct(protected PortalUserService $portalUsers) {}

    public function index()
    {
        return view('assesspay.shell');
    }

    /**
     * POST /sso/redirect — receive identity from module-bridge after DEORIS SSO.
     *
     * Mirrors EnrollEase's ssoRedirect pattern: the module-bridge exchanges the
     * token directly with DEORIS, then POSTs the resolved user identity here.
     * We trust this POST because it comes from our own origin (same-origin fetch
     * with credentials) and is CSRF-protected by the web middleware group.
     */
    public function complete(Request $request)
    {
        // If called via POST with identity payload (module-bridge path), hydrate session
        if ($request->isMethod('POST') && $request->filled('id')) {
            $role     = $this->portalUsers->mapPortalRole($request->input('role', 'student'));
            $name     = $request->input('name', 'User');
            $email    = strtolower($request->input('email', ''));
            $id       = (string) $request->input('id');
            $embedded = $request->input('embedded') === '1';

            $this->portalUsers->hydrateSession($request, [
                'id'    => $id,
                'name'  => $name,
                'email' => $email,
                'role'  => $role,
            ], $embedded);

            \Illuminate\Support\Facades\Log::info('[AssessPay][SSO] Session hydrated via sso/redirect', [
                'portal_id' => $id,
                'role'      => $role,
            ]);
        }

        if (! $this->portalUsers->isAuthenticated($request)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'unauthenticated'], 401);
            }
            return redirect()->route('assesspay.home');
        }

        $this->portalUsers->ensureStudentRecord($request);
        $role = $this->portalUsers->role($request);
        $dashboardRoute = $this->portalUsers->dashboardRouteForRole($role);

        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success'  => true,
                'redirect' => route($dashboardRoute),
            ]);
        }

        return redirect()->route($dashboardRoute);
    }

    /** @deprecated Use POST /sso/redirect — kept for backward compatibility */
    public function redirect(Request $request)
    {
        return $this->complete($request);
    }

    public function admin(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'admin')) {
            return $redirect;
        }

        return view('assesspay.admin', $this->sharedMetrics());
    }

    public function cashier(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'cashier')) {
            return $redirect;
        }

        $directory = app(DeorisStudentDirectory::class);

        return view('assesspay.cashier', array_merge($this->sharedMetrics(), [
            'pendingPayments' => Payment::where('status', 'pending')->with('student')->latest()->limit(20)->get(),
            'eligibleStudents' => $this->assignableStudents($directory->eligibleStudents()),
            'paymentStudents' => Student::with('balance')->orderBy('name')->get(),
            'openPayables' => $this->cashierPayables(),
            'schoolYears' => $directory->schoolYears(),
            'termsBySchoolYear' => $directory->termsBySchoolYear(),
        ]));
    }

    public function storePayable(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'cashier')) {
            return $redirect;
        }

        $data = $request->validate([
            'source' => ['required', 'in:deoris'],
            'source_id' => ['required', 'string', 'max:100'],
            'school_year' => ['required', 'string', 'max:20'],
            'term' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
            'tuition_amount' => ['required', 'numeric', 'min:1'],
            'misc_amount' => ['nullable', 'numeric', 'min:0'],
            'other_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $directory = app(DeorisStudentDirectory::class);
        $deorisStudent = $directory->findEligible($data['source_id']);

        if (! $deorisStudent) {
            return back()
                ->withInput()
                ->with('error', 'That student is not approved and enrolled in DEORIS.');
        }

        try {
            [$student, $record] = DB::transaction(function () use ($data, $directory, $deorisStudent) {
                $student = Student::updateOrCreate(
                    ['portal_user_id' => $deorisStudent['id']],
                    [
                        'student_id' => $directory->studentNumberFor($deorisStudent),
                        'name' => $deorisStudent['name'],
                        'email' => $deorisStudent['email'],
                        'program' => $deorisStudent['program'] ?: 'Enrolled',
                        'year_level' => $deorisStudent['grade_level'] ?: null,
                        'status' => 'active',
                    ]
                );

                $account = app(BillingAccountService::class)->ensureForStudent($student);

                $hasExistingPayable = TuitionRecord::where('student_id', $student->id)
                    ->whereNotIn('status', [
                        PaymentStatus::Cancelled,
                        PaymentStatus::Refunded,
                    ])
                    ->exists();

                if ($hasExistingPayable) {
                    throw ValidationException::withMessages([
                        'source_id' => 'This student already has a payable record in AssessPay.',
                    ]);
                }

                $tuition = (float) $data['tuition_amount'];
                $misc = (float) ($data['misc_amount'] ?? 0);
                $other = (float) ($data['other_amount'] ?? 0);
                $total = $tuition + $misc + $other;
                $term = $data['term'] ?: 'Annual';
                $description = $data['description'] ?: 'Tuition assessment';

                $recordKey = [
                    'student_id' => $student->id,
                    'school_year' => $data['school_year'],
                    'term' => $term,
                    'description' => $description,
                ];

                $matchingRecords = TuitionRecord::with('payments')->where($recordKey)->get();
                foreach ($matchingRecords as $matchingRecord) {
                    $paid = (float) $matchingRecord->payments
                        ->where('status', PaymentStatus::Paid)
                        ->sum('amount');
                    $remaining = max(0, (float) $matchingRecord->total_amount - $paid);

                    if (in_array($matchingRecord->status, [PaymentStatus::Paid, PaymentStatus::Refunded], true) || $remaining <= 0) {
                        throw ValidationException::withMessages([
                            'tuition_amount' => 'This payable has already been paid and cannot be added again.',
                        ]);
                    }
                }

                $record = $matchingRecords
                    ->first(fn (TuitionRecord $matchingRecord) => ! in_array($matchingRecord->status, [
                        PaymentStatus::Paid,
                        PaymentStatus::Cancelled,
                        PaymentStatus::Refunded,
                    ], true));

                $values = [
                    'billing_account_id' => $account->id,
                    'tuition_amount' => $tuition,
                    'misc_amount' => $misc,
                    'other_amount' => $other,
                    'total_amount' => $total,
                    'status' => PaymentStatus::Pending,
                ];

                if ($record) {
                    $record->update($values);
                } else {
                    $record = TuitionRecord::create([...$recordKey, ...$values]);
                }

                app(BalanceService::class)->recalculate($account);

                return [$student->fresh('balance'), $record->fresh()];
            });
        } catch (ValidationException $e) {
            return back()
                ->withInput()
                ->with('error', $e->validator->errors()->first());
        } catch (\Throwable $e) {
            Log::error('[AssessPay] Failed to create cashier payable', [
                'source_id' => $data['source_id'],
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Payable was not created: '.$e->getMessage());
        }

        return redirect(route('assesspay.cashier').'#payables')
            ->with('success', 'Payable created for '.$student->name.'.')
            ->with('payable_student_id', $student->id)
            ->with('payable_record_id', $record->id)
            ->with('payable_amount', number_format((float) $record->total_amount, 2, '.', ''));
    }

    public function student(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'student')) {
            return $redirect;
        }

        $student = $this->portalUsers->ensureStudentRecord($request);
        $studentId = $student?->id;

        $balance = $studentId ? Balance::where('student_id', $studentId)->first() : null;
        $payments = $studentId ? Payment::where('student_id', $studentId)->latest()->limit(10)->get() : collect();
        $receipts = $studentId ? OfficialReceipt::where('student_id', $studentId)->latest()->limit(10)->get() : collect();
        $openPayables = $studentId ? $this->studentPayables($studentId) : [];

        return view('assesspay.student', compact('balance', 'payments', 'receipts', 'student', 'openPayables'));
    }

    protected function guardRole(Request $request, string $role): ?\Illuminate\Http\RedirectResponse
    {
        if (PortalSession::role($request) !== $role) {
            return redirect()->route('assesspay.home');
        }
        return null;
    }

    protected function assignableStudents(array $eligibleStudents): array
    {
        $portalIds = collect($eligibleStudents)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->values();
        $emails = collect($eligibleStudents)
            ->pluck('email')
            ->map(fn ($email) => strtolower((string) $email))
            ->filter()
            ->values();

        if ($portalIds->isEmpty() && $emails->isEmpty()) {
            return $eligibleStudents;
        }

        $studentsWithPayables = Student::query()
            ->where(function ($query) use ($portalIds, $emails) {
                if ($portalIds->isNotEmpty()) {
                    $query->whereIn('portal_user_id', $portalIds);
                }

                if ($emails->isNotEmpty()) {
                    $method = $portalIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}(DB::raw('LOWER(email)'), $emails);
                }
            })
            ->whereHas('tuitionRecords', function ($query) {
                $query->whereNotIn('status', [
                    PaymentStatus::Cancelled,
                    PaymentStatus::Refunded,
                ]);
            })
            ->get(['portal_user_id', 'email']);

        $blockedPortalIds = $studentsWithPayables
            ->pluck('portal_user_id')
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->all();
        $blockedEmails = $studentsWithPayables
            ->pluck('email')
            ->map(fn ($email) => strtolower((string) $email))
            ->filter()
            ->all();

        return collect($eligibleStudents)
            ->reject(fn ($student) => in_array((string) $student['id'], $blockedPortalIds, true)
                || in_array(strtolower((string) $student['email']), $blockedEmails, true))
            ->values()
            ->all();
    }

    protected function sharedMetrics(): array
    {
        return [
            'totalCollected' => (float) Payment::where('status', 'paid')->sum('amount'),
            'pendingCount' => Payment::where('status', 'pending')->count(),
            'openBalances' => (float) Balance::sum('current_balance'),
            'tuitionRecords' => TuitionRecord::count(),
        ];
    }

    protected function cashierPayables(): array
    {
        return TuitionRecord::with(['student', 'payments'])
            ->whereNotIn('status', [
                PaymentStatus::Paid->value,
                PaymentStatus::Cancelled->value,
                PaymentStatus::Refunded->value,
            ])
            ->latest()
            ->get()
            ->map(function (TuitionRecord $record) {
                $paid = (float) $record->payments
                    ->where('status', PaymentStatus::Paid)
                    ->sum('amount');
                $remaining = max(0, (float) $record->total_amount - $paid);

                if ($remaining <= 0) {
                    return null;
                }

                return [
                    'id' => $record->id,
                    'student_id' => $record->student_id,
                    'student_name' => $record->student?->name ?? 'Unknown',
                    'student_number' => $record->student?->student_id ?? '',
                    'description' => $record->description,
                    'school_year' => $record->school_year,
                    'term' => $record->term ?: 'Annual',
                    'remaining' => number_format($remaining, 2, '.', ''),
                    'label' => trim(($record->student?->name ?? 'Unknown').' - '.$record->description.' (₱'.number_format($remaining, 2).')'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function studentPayables(int $studentId): array
    {
        return TuitionRecord::with('payments')
            ->where('student_id', $studentId)
            ->whereNotIn('status', [
                PaymentStatus::Paid->value,
                PaymentStatus::Cancelled->value,
                PaymentStatus::Refunded->value,
            ])
            ->latest()
            ->get()
            ->map(function (TuitionRecord $record) {
                $paid = (float) $record->payments
                    ->where('status', PaymentStatus::Paid)
                    ->sum('amount');
                $remaining = max(0, (float) $record->total_amount - $paid);

                if ($remaining <= 0) {
                    return null;
                }

                return [
                    'id' => $record->id,
                    'description' => $record->description,
                    'school_year' => $record->school_year,
                    'term' => $record->term ?: 'Annual',
                    'remaining' => number_format($remaining, 2, '.', ''),
                    'label' => $record->description.' - '.$record->school_year.' '.$record->term.' (₱'.number_format($remaining, 2).')',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    // ── Admin sub-views ───────────────────────────────────────────────────────

    public function adminPayments(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'admin')) {
            return $redirect;
        }
        $payments = Payment::with('student')->latest()->paginate(25);
        return view('assesspay.admin.payments', compact('payments'));
    }

    public function adminReceipts(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'admin')) {
            return $redirect;
        }
        $receipts = OfficialReceipt::with('student')->latest()->paginate(25);
        return view('assesspay.admin.receipts', compact('receipts'));
    }

    public function adminAnalytics(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'admin')) {
            return $redirect;
        }
        return view('assesspay.admin.analytics');
    }

    public function adminHistory(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'admin')) {
            return $redirect;
        }

        return view('assesspay.history', $this->historyData('admin'));
    }

    // ── Cashier sub-views ─────────────────────────────────────────────────────

    public function cashierPayments(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'cashier')) {
            return $redirect;
        }
        $payments = Payment::with('student')->latest()->paginate(25);
        return view('assesspay.cashier.payments', compact('payments'));
    }

    public function cashierReceipts(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'cashier')) {
            return $redirect;
        }
        $receipts = OfficialReceipt::with('student')->latest()->paginate(25);
        return view('assesspay.cashier.receipts', compact('receipts'));
    }

    public function cashierHistory(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'cashier')) {
            return $redirect;
        }

        return view('assesspay.history', $this->historyData('cashier'));
    }

    protected function historyData(string $role): array
    {
        return [
            'role' => $role,
            'payments' => Payment::with(['student', 'officialReceipt', 'tuitionRecord'])
                ->latest()
                ->paginate(15, ['*'], 'payments_page'),
            'payables' => TuitionRecord::with(['student', 'payments'])
                ->latest()
                ->paginate(15, ['*'], 'payables_page'),
        ];
    }

    // ── Student sub-views ─────────────────────────────────────────────────────

    public function studentPayments(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'student')) {
            return $redirect;
        }
        $studentId = $this->portalUsers->studentIdForSession($request);
        $payments = Payment::where('student_id', $studentId)->latest()->paginate(20);
        return view('assesspay.student.payments', compact('payments'));
    }

    public function studentReceipts(Request $request)
    {
        if ($redirect = $this->guardRole($request, 'student')) {
            return $redirect;
        }
        $studentId = $this->portalUsers->studentIdForSession($request);
        $receipts = OfficialReceipt::with('payment')
            ->where('student_id', $studentId)
            ->latest()
            ->paginate(20);
        return view('assesspay.student.receipts', compact('receipts'));
    }
}
