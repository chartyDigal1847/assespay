@extends('assesspay.layout')
@section('title', 'My Billing')
@section('nav')
    <a href="{{ route('assesspay.student') }}" class="active">Overview</a>
    <a href="#submit-payment">Pay</a>
    <a href="#history">History</a>
@endsection
@section('header')
    <h1>My Billing</h1>
    <p>View your balance, pay assigned payables, and track receipts.</p>
@endsection
@section('content')
@php
    $due = ($balance?->current_balance ?? 0) > 0;
@endphp
<div class="ap-grid" data-student-local-id="{{ $student?->id ?? '' }}">
    <div class="ap-card ap-card--highlight">
        <h3>Amount Due</h3>
        <div class="ap-value" data-balance-card>₱{{ number_format($balance?->current_balance ?? 0, 2) }}</div>
    </div>
    <div class="ap-card">
        <h3>Total Paid</h3>
        <div class="ap-value">₱{{ number_format($balance?->total_paid ?? 0, 2) }}</div>
    </div>
    <div class="ap-card">
        <h3>Clearance</h3>
        <div class="ap-value" style="font-size:1.1rem">
            @if($due)
                <span class="ap-badge due">Balance due</span>
            @else
                <span class="ap-badge ok">Eligible</span>
            @endif
        </div>
    </div>
</div>

<div class="ap-section" id="submit-payment">
    <h2>Pay payable</h2>
    <form class="ap-form" id="studentPaymentForm">
        <input type="hidden" name="student_id" value="{{ $student?->id }}">
        <div class="ap-form-row">
            <label for="studentPayableId">Payable</label>
            <select id="studentPayableId" name="tuition_record_id" required>
                <option value="">Select a payable</option>
                @foreach($openPayables ?? [] as $payable)
                    <option value="{{ $payable['id'] }}" data-amount="{{ $payable['remaining'] }}">
                        {{ $payable['label'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="ap-form-row">
            <label for="payAmount">Amount (₱)</label>
            <input type="number" id="payAmount" name="amount" step="0.01" min="1" required placeholder="0.00">
            <p class="ap-section-note" id="payAmountLimit" style="display:none"></p>
        </div>
        <div class="ap-form-row">
            <label for="payMethod">Payment method</label>
            <select id="payMethod" name="method">
                <option value="online">Online</option>
                <option value="bank">Bank transfer</option>
            </select>
        </div>
        <div class="ap-form-row">
            <label for="payRef">Reference number <span style="font-weight:400;color:var(--ap-muted)">(optional)</span></label>
            <input type="text" id="payRef" name="reference_number" placeholder="Transaction or OR number">
        </div>
        <div class="ap-form-actions">
            <button type="submit" class="ap-btn ap-btn-primary">Pay now</button>
        </div>
    </form>
</div>

<div class="ap-section" id="history">
    <h2>Payment history</h2>
    <div class="ap-table-wrap">
        <table class="ap-table">
            <thead><tr><th>Date</th><th>Amount</th><th>Status</th><th>Receipt</th></tr></thead>
            <tbody>
            @forelse($payments as $p)
                <tr>
                    <td>{{ $p->created_at->format('M d, Y') }}</td>
                    <td>₱{{ number_format($p->amount, 2) }}</td>
                    <td><span class="ap-badge {{ ($p->status?->value ?? $p->status) === 'paid' ? 'paid' : 'pending' }}">{{ $p->status?->value ?? $p->status }}</span></td>
                    <td>{{ $p->receipt_number ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="ap-empty">No payments yet</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="ap-section" id="receipts">
    <h2>Official receipts</h2>
    <div class="ap-table-wrap">
        <table class="ap-table">
            <thead><tr><th>Receipt #</th><th>Amount</th><th>Issued</th></tr></thead>
            <tbody>
            @forelse($receipts as $r)
                <tr>
                    <td>{{ $r->receipt_number }}</td>
                    <td>₱{{ number_format($r->amount, 2) }}</td>
                    <td>{{ $r->issued_at->format('M d, Y g:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="ap-empty">No receipts issued yet</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.getElementById('studentPayableId')?.addEventListener('change', (e) => {
    const maxAmount = e.target.selectedOptions[0]?.dataset.amount || '';
    const amountInput = document.getElementById('payAmount');
    const limit = document.getElementById('payAmountLimit');

    amountInput.value = '';
    amountInput.max = maxAmount || '';

    if (maxAmount) {
        limit.textContent = `Maximum payable balance: ₱${Number(maxAmount).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
        limit.style.display = 'block';
    } else {
        limit.textContent = '';
        limit.style.display = 'none';
    }
});

document.getElementById('studentPaymentForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    if (!fd.get('tuition_record_id')) {
        AssessPay.toast('Select a payable first', 'error');
        return;
    }
    const amount = Number(fd.get('amount'));
    const maxAmount = Number(document.getElementById('payAmount').max || 0);
    if (!amount || amount <= 0) {
        AssessPay.toast('Enter the amount you want to pay', 'error');
        return;
    }
    if (maxAmount && amount > maxAmount) {
        AssessPay.toast('Amount cannot exceed the payable balance', 'error');
        return;
    }
    const btn = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    try {
        await AssessPay.submitPayment({
            student_id: +fd.get('student_id'),
            tuition_record_id: +fd.get('tuition_record_id'),
            amount,
            method: fd.get('method'),
            reference_number: fd.get('reference_number') || null,
        });
        AssessPay.toast('Payment completed', 'success');
        setTimeout(() => location.reload(), 1600);
    } catch (err) {
        AssessPay.toast(err.message, 'error');
        btn.disabled = false;
    }
});
</script>
@endpush
