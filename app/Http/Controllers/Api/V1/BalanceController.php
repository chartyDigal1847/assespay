<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BalanceResource;
use App\Models\Balance;
use App\Models\BillingAccount;
use App\Services\BalanceService;
use App\Support\PortalSession;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    public function __construct(protected BalanceService $balances) {}

    public function index(Request $request)
    {
        $query = Balance::with('student')->latest('last_recalculated_at');

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if (PortalSession::role($request) === 'student') {
            $studentId = PortalSession::studentId($request);
            abort_unless($studentId, 403, 'Student account is not available.');
            $query->where('student_id', $studentId);
        }

        return BalanceResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function show(Request $request, Balance $balance)
    {
        if (PortalSession::role($request) === 'student') {
            abort_unless((int) PortalSession::studentId($request) === (int) $balance->student_id, 404);
        }

        return new BalanceResource($balance->load('student'));
    }

    public function update(Request $request, BillingAccount $billingAccount)
    {
        $data = $request->validate([
            'current_balance' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
        ]);

        $balance = $this->balances->adjustByCashier(
            $billingAccount,
            (float) $data['current_balance'],
            PortalSession::portalId($request),
            $data['reason'],
        );

        return new BalanceResource($balance->load('student'));
    }

    public function recalculate(BillingAccount $billingAccount)
    {
        $balance = $this->balances->recalculate($billingAccount);

        return new BalanceResource($balance->load('student'));
    }
}
