@extends('assesspay.layout')
@section('title', 'Issued Receipts')
@section('nav')
    <a href="{{ route('assesspay.cashier') }}">Desk</a>
    <a href="{{ route('assesspay.cashier.payments') }}">All payments</a>
    <a href="{{ route('assesspay.cashier.receipts') }}" class="active">Receipts</a>
    <a href="{{ route('assesspay.cashier.history') }}">History</a>
@endsection
@section('header')
    <h1>Issued receipts</h1>
    <p>All official receipts issued by cashiers.</p>
@endsection
@section('content')
<div class="ap-section">
    <div class="ap-table-wrap">
        <table class="ap-table">
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Student</th>
                    <th>Amount</th>
                    <th>Issued by</th>
                    <th>Issued at</th>
                </tr>
            </thead>
            <tbody>
            @forelse($receipts as $r)
                <tr>
                    <td><strong>{{ $r->receipt_number }}</strong></td>
                    <td>{{ $r->student?->name ?? '—' }}</td>
                    <td>₱{{ number_format($r->amount, 2) }}</td>
                    <td>{{ $r->issued_by_portal_id ?? '—' }}</td>
                    <td>{{ $r->issued_at?->format('M d, Y g:i A') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="ap-empty">No receipts found</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="ap-pagination">{{ $receipts->links() }}</div>
</div>
@endsection
