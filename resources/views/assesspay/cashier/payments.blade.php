@extends('assesspay.layout')
@section('title', 'Payment History')
@section('nav')
    <a href="{{ route('assesspay.cashier') }}">Desk</a>
    <a href="{{ route('assesspay.cashier.payments') }}" class="active">All payments</a>
    <a href="{{ route('assesspay.cashier.receipts') }}">Receipts</a>
    <a href="{{ route('assesspay.cashier.history') }}">History</a>
@endsection
@section('header')
    <h1>All payments</h1>
    <p>Full payment history for student-handled payments.</p>
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
                @php $status = $p->status?->value ?? $p->status; @endphp
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
                    <td><span class="ap-badge {{ $status === 'paid' ? 'paid' : 'pending' }}">{{ $status }}</span></td>
                    <td>{{ $p->receipt_number ?? '—' }}</td>
                    <td>{{ $p->created_at->format('M d, Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="ap-empty">No payments found</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="ap-pagination">{{ $payments->links() }}</div>
</div>
@endsection
