@extends('assesspay.layout')
@section('title', 'All Payments')
@section('nav')
    <a href="{{ route('assesspay.admin') }}">Overview</a>
    <a href="{{ route('assesspay.admin.payments') }}" class="active">Payments</a>
    <a href="{{ route('assesspay.admin.receipts') }}">Receipts</a>
    <a href="{{ route('assesspay.admin.analytics') }}">Analytics</a>
    <a href="{{ route('assesspay.admin.history') }}">History</a>
@endsection
@section('header')
    <h1>All payments</h1>
    <p>Read-only audit view. Admins cannot confirm or modify payments.</p>
@endsection
@section('content')
<div class="ap-section">
    <div class="ap-table-wrap">
        <table class="ap-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Receipt</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payments as $p)
                <tr>
                    <td>{{ $p->id }}</td>
                    <td>
                        <strong>{{ $p->student?->name ?? '—' }}</strong>
                        @if($p->student?->email)
                            <br><span class="ap-result-meta">{{ $p->student->email }}</span>
                        @endif
                    </td>
                    <td>₱{{ number_format($p->amount, 2) }}</td>
                    <td>{{ $p->method ?? '—' }}</td>
                    <td><span class="ap-badge {{ ($p->status?->value ?? $p->status) === 'paid' ? 'paid' : 'pending' }}">{{ $p->status?->value ?? $p->status }}</span></td>
                    <td>{{ $p->receipt_number ?? '—' }}</td>
                    <td>{{ $p->created_at->format('M d, Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="ap-empty">No payments found</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="ap-pagination">
        {{ $payments->links() }}
    </div>
</div>
@endsection
