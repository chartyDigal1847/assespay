@extends('assesspay.layout')
@section('title', 'My Payments')
@section('nav')
    <a href="{{ route('assesspay.student') }}">Overview</a>
    <a href="{{ route('assesspay.student.payments') }}" class="active">Payments</a>
    <a href="{{ route('assesspay.student.receipts') }}">Receipts</a>
@endsection
@section('header')
    <h1>My payment history</h1>
    <p>All payment requests you have submitted.</p>
@endsection
@section('content')
<div class="ap-section">
    <div class="ap-table-wrap">
        <table class="ap-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Receipt</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payments as $p)
                @php $status = $p->status?->value ?? $p->status; @endphp
                <tr>
                    <td>{{ $p->created_at->format('M d, Y') }}</td>
                    <td>₱{{ number_format($p->amount, 2) }}</td>
                    <td>{{ $p->method ?? '—' }}</td>
                    <td>{{ $p->reference_number ?? '—' }}</td>
                    <td><span class="ap-badge {{ $status === 'paid' ? 'paid' : 'pending' }}">{{ $status }}</span></td>
                    <td>{{ $p->receipt_number ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="ap-empty">No payments yet</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="ap-pagination">{{ $payments->links() }}</div>
</div>
@endsection
