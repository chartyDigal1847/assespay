@extends('assesspay.layout')
@section('title', 'All Receipts')
@section('nav')
    <a href="{{ route('assesspay.admin') }}">Overview</a>
    <a href="{{ route('assesspay.admin.payments') }}">Payments</a>
    <a href="{{ route('assesspay.admin.receipts') }}" class="active">Receipts</a>
    <a href="{{ route('assesspay.admin.analytics') }}">Analytics</a>
    <a href="{{ route('assesspay.admin.history') }}">History</a>
@endsection
@section('header')
    <h1>Official receipts</h1>
    <p>Full receipt history across all students. Read-only.</p>
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
    <div class="ap-pagination">
        {{ $receipts->links() }}
    </div>
</div>
@endsection
