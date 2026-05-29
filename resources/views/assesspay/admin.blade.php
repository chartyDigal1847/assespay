@extends('assesspay.layout')
@section('title', 'Audit')
@section('nav')
    <a href="{{ route('assesspay.admin') }}" class="active">Overview</a>
    <a href="{{ route('assesspay.admin.history') }}">History</a>
    <a href="#search">Search</a>
@endsection
@section('header')
    <h1>Financial audit</h1>
    <p>Read-only oversight across collections, balances, and records. Admins cannot process payments.</p>
@endsection
@section('content')
<div class="ap-grid">
    <div class="ap-card">
        <h3>Total collected</h3>
        <div class="ap-value">₱{{ number_format($totalCollected, 2) }}</div>
    </div>
    <div class="ap-card">
        <h3>Pending payments</h3>
        <div class="ap-value">{{ $pendingCount }}</div>
    </div>
    <div class="ap-card ap-card--highlight">
        <h3>Open balances</h3>
        <div class="ap-value">₱{{ number_format($openBalances, 2) }}</div>
    </div>
    <div class="ap-card">
        <h3>Tuition records</h3>
        <div class="ap-value">{{ $tuitionRecords }}</div>
    </div>
</div>

<div class="ap-section" id="search">
    <h2>Search records</h2>
    <input type="search" id="auditSearch" class="ap-search-input" placeholder="Student name, receipt #, reference…" autocomplete="off">
    <div id="auditResults" class="ap-search-results"></div>
</div>
@endsection
@push('scripts')
<script>
function renderAuditResults(data) {
    const el = document.getElementById('auditResults');
    if (!el) return;
    const r = data.results || {};
    const blocks = [];

    (r.students || []).forEach(s => {
        blocks.push(`<div class="ap-result-item"><strong>Student: ${s.name}</strong><span class="ap-result-meta">${s.student_id} · ${s.email || '—'}</span></div>`);
    });
    (r.payments || []).forEach(p => {
        const st = p.student?.name || '—';
        blocks.push(`<div class="ap-result-item"><strong>Payment #${p.id}</strong><span class="ap-result-meta">₱${Number(p.amount).toLocaleString()} · ${p.status} · ${st}</span></div>`);
    });
    (r.receipts || []).forEach(rc => {
        blocks.push(`<div class="ap-result-item"><strong>Receipt ${rc.receipt_number}</strong><span class="ap-result-meta">₱${Number(rc.amount).toLocaleString()}</span></div>`);
    });
    (r.billing || []).forEach(b => {
        blocks.push(`<div class="ap-result-item"><strong>${b.description || 'Billing'}</strong><span class="ap-result-meta">${b.school_year} · ₱${Number(b.total_amount || 0).toLocaleString()}</span></div>`);
    });

    el.innerHTML = blocks.length ? blocks.join('') : '<p class="ap-empty">No matches found</p>';
}

let auditTimer;
document.getElementById('auditSearch')?.addEventListener('input', (e) => {
    clearTimeout(auditTimer);
    const q = e.target.value.trim();
    const el = document.getElementById('auditResults');
    if (q.length < 2) { if (el) el.innerHTML = ''; return; }
    auditTimer = setTimeout(async () => {
        try {
            const res = await AssessPay.search(q);
            renderAuditResults(res);
        } catch (err) {
            AssessPay.toast(err.message, 'error');
        }
    }, 350);
});
</script>
@endpush
