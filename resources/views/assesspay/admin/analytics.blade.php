@extends('assesspay.layout')
@section('title', 'Financial Analytics')
@section('nav')
    <a href="{{ route('assesspay.admin') }}">Overview</a>
    <a href="{{ route('assesspay.admin.payments') }}">Payments</a>
    <a href="{{ route('assesspay.admin.receipts') }}">Receipts</a>
    <a href="{{ route('assesspay.admin.analytics') }}" class="active">Analytics</a>
    <a href="{{ route('assesspay.admin.history') }}">History</a>
@endsection
@section('header')
    <h1>Financial analytics</h1>
    <p>Payment trends, cashier summaries, and tuition analytics. Read-only.</p>
@endsection
@section('content')

{{-- Summary cards --}}
<div class="ap-grid" id="analyticsCards">
    <div class="ap-card ap-card--highlight">
        <h3>Total collected</h3>
        <div class="ap-value" id="totalCollected">Loading…</div>
    </div>
    <div class="ap-card">
        <h3>Payments this month</h3>
        <div class="ap-value" id="paymentsThisMonth">—</div>
    </div>
    <div class="ap-card">
        <h3>Open balances</h3>
        <div class="ap-value" id="openBalances">—</div>
    </div>
    <div class="ap-card">
        <h3>Pending payments</h3>
        <div class="ap-value" id="pendingCount">—</div>
    </div>
</div>

{{-- Payment trend table --}}
<div class="ap-section">
    <h2>Monthly payment trend</h2>
    <div class="ap-table-wrap">
        <table class="ap-table" id="trendTable">
            <thead><tr><th>Month</th><th>Payments</th><th>Total collected</th></tr></thead>
            <tbody id="trendBody"><tr><td colspan="3" class="ap-empty">Loading…</td></tr></tbody>
        </table>
    </div>
</div>

{{-- Tuition analytics --}}
<div class="ap-section">
    <h2>Tuition analytics</h2>
    <div class="ap-table-wrap">
        <table class="ap-table" id="tuitionTable">
            <thead><tr><th>School year</th><th>Term</th><th>Status</th><th>Count</th><th>Total</th></tr></thead>
            <tbody id="tuitionBody"><tr><td colspan="5" class="ap-empty">Loading…</td></tr></tbody>
        </table>
    </div>
</div>

@endsection
@push('scripts')
<script>
async function loadAnalytics() {
    try {
        const data = await AssessPay.getAnalytics();
        const a = data.data || data;

        // Summary cards
        document.getElementById('totalCollected').textContent =
            '₱' + Number(a.summary?.total_collected ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('paymentsThisMonth').textContent =
            a.summary?.payments_this_month ?? '—';
        document.getElementById('openBalances').textContent =
            '₱' + Number(a.summary?.open_balances ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('pendingCount').textContent =
            a.summary?.pending_count ?? '—';

        // Trend table
        const trendBody = document.getElementById('trendBody');
        const trend = a.payment_trend || [];
        trendBody.innerHTML = trend.length
            ? trend.map(r => `<tr>
                <td>${r.month ?? r.period ?? '—'}</td>
                <td>${r.payment_count ?? r.count ?? '—'}</td>
                <td>₱${Number(r.total_amount ?? r.total ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
              </tr>`).join('')
            : '<tr><td colspan="3" class="ap-empty">No trend data</td></tr>';

        // Tuition analytics
        const tuitionBody = document.getElementById('tuitionBody');
        const tuition = a.tuition_analytics || [];
        tuitionBody.innerHTML = tuition.length
            ? tuition.map(r => `<tr>
                <td>${r.school_year ?? '—'}</td>
                <td>${r.term ?? '—'}</td>
                <td><span class="ap-badge ${r.status === 'paid' ? 'paid' : 'pending'}">${r.status ?? '—'}</span></td>
                <td>${r.record_count ?? r.count ?? '—'}</td>
                <td>₱${Number(r.total_amount ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
              </tr>`).join('')
            : '<tr><td colspan="5" class="ap-empty">No tuition data</td></tr>';

    } catch (err) {
        AssessPay.toast('Failed to load analytics: ' + err.message, 'error');
    }
}

loadAnalytics();
// Refresh every 60 seconds
setInterval(loadAnalytics, 60000);
</script>
@endpush
