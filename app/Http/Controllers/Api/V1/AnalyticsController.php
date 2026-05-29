<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TuitionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $months = $request->integer('months', 6);

        $byStatus = TuitionRecord::select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('status')
            ->get();

        $dateExpr = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', COALESCE(confirmed_at, paid_at, created_at))"
            : "DATE_FORMAT(COALESCE(confirmed_at, paid_at, created_at), '%Y-%m')";

        $paymentsTrend = Payment::select(
            DB::raw("{$dateExpr} as period"),
            DB::raw('COUNT(*) as payment_count'),
            DB::raw('SUM(amount) as total_amount')
        )
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupBy('period')
            ->orderByDesc('period')
            ->get();

        $summary = [
            'total_collected' => (float) Payment::where('status', 'paid')->sum('amount'),
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'active_tuition_records' => TuitionRecord::whereNotIn('status', ['cancelled', 'refunded'])->count(),
            'by_status' => $byStatus,
            'payment_trend' => $paymentsTrend,
        ];

        return response()->json(['data' => $summary]);
    }
}
