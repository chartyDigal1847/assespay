@extends('assesspay.layout')
@section('title', 'My Receipts')
@section('nav')
    <a href="{{ route('assesspay.student') }}">Overview</a>
    <a href="{{ route('assesspay.student.payments') }}">Payments</a>
    <a href="{{ route('assesspay.student.receipts') }}" class="active">Receipts</a>
@endsection
@section('header')
    <h1>My official receipts</h1>
    <p>Download or reference your issued receipts.</p>
@endsection
@section('content')
<div class="ap-section">
    <div class="ap-table-wrap">
        <table class="ap-table">
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Issued at</th>
                </tr>
            </thead>
            <tbody>
            @forelse($receipts as $r)
                <tr>
                    <td><strong>{{ $r->receipt_number }}</strong></td>
                    <td>₱{{ number_format($r->amount, 2) }}</td>
                    <td>{{ $r->payment?->method ?? '—' }}</td>
                    <td>{{ $r->issued_at?->format('M d, Y g:i A') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="ap-empty">No receipts issued yet</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="ap-pagination">{{ $receipts->links() }}</div>
</div>
@endsection
