@extends('assesspay.layout')
@section('title', 'History')
@section('nav')
    @if($role === 'admin')
        <a href="{{ route('assesspay.admin') }}">Overview</a>
        <a href="{{ route('assesspay.admin.payments') }}">Payments</a>
        <a href="{{ route('assesspay.admin.receipts') }}">Receipts</a>
        <a href="{{ route('assesspay.admin.analytics') }}">Analytics</a>
        <a href="{{ route('assesspay.admin.history') }}" class="active">History</a>
    @else
        <a href="{{ route('assesspay.cashier') }}">Desk</a>
        <a href="{{ route('assesspay.cashier.payments') }}">All payments</a>
        <a href="{{ route('assesspay.cashier.receipts') }}">Receipts</a>
        <a href="{{ route('assesspay.cashier.history') }}" class="active">History</a>
    @endif
@endsection
@section('header')
    <h1>History</h1>
    <p>Review payable assignments, student payments, receipts, and remaining balances.</p>
@endsection
@section('content')
<div class="ap-section">
    <h2>Payment history</h2>
    <div class="ap-table-wrap">
        <table class="ap-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Payable</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Receipt</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payments as $payment)
                @php $status = $payment->status?->value ?? $payment->status; @endphp
                <tr>
                    <td>{{ $payment->id }}</td>
                    <td>
                        <strong>{{ $payment->student?->name ?? '—' }}</strong>
                        @if($payment->student?->student_id)
                            <br><span class="ap-result-meta">{{ $payment->student->student_id }}</span>
                        @endif
                    </td>
                    <td>{{ $payment->tuitionRecord?->description ?? '—' }}</td>
                    <td>₱{{ number_format($payment->amount, 2) }}</td>
                    <td>{{ $payment->method ?? '—' }}</td>
                    <td><span class="ap-badge {{ $status === 'paid' ? 'paid' : 'pending' }}">{{ $status }}</span></td>
                    <td>{{ $payment->receipt_number ?? $payment->officialReceipt?->receipt_number ?? '—' }}</td>
                    <td>{{ $payment->created_at?->format('M d, Y g:i A') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="ap-empty">No payment history yet</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="ap-pagination">{{ $payments->withQueryString()->links() }}</div>
</div>

<div class="ap-section">
    <h2>Payable history</h2>
    <div class="ap-table-wrap">
        <table class="ap-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Description</th>
                    <th>School year</th>
                    <th>Term</th>
                    <th>Assigned</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payables as $payable)
                @php
                    $paid = (float) $payable->payments->where('status', \App\Enums\PaymentStatus::Paid)->sum('amount');
                    $balance = max(0, (float) $payable->total_amount - $paid);
                    $status = $payable->status?->value ?? $payable->status;
                @endphp
                <tr>
                    <td>{{ $payable->id }}</td>
                    <td>
                        <strong>{{ $payable->student?->name ?? '—' }}</strong>
                        @if($payable->student?->student_id)
                            <br><span class="ap-result-meta">{{ $payable->student->student_id }}</span>
                        @endif
                    </td>
                    <td>{{ $payable->description }}</td>
                    <td>{{ $payable->school_year }}</td>
                    <td>{{ $payable->term ?: 'Annual' }}</td>
                    <td>₱{{ number_format($payable->total_amount, 2) }}</td>
                    <td>₱{{ number_format($paid, 2) }}</td>
                    <td>₱{{ number_format($balance, 2) }}</td>
                    <td><span class="ap-badge {{ $status === 'paid' ? 'paid' : 'pending' }}">{{ $status }}</span></td>
                    <td>{{ $payable->created_at?->format('M d, Y g:i A') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="ap-empty">No payable history yet</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="ap-pagination">{{ $payables->withQueryString()->links() }}</div>
</div>
@endsection
