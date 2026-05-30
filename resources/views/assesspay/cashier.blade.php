@extends('assesspay.layout')
@section('title', 'Cashier')
@section('nav')
    <a href="{{ route('assesspay.cashier') }}" class="active">Desk</a>
    <a href="{{ route('assesspay.cashier.payments') }}">All payments</a>
    <a href="{{ route('assesspay.cashier.receipts') }}">Receipts</a>
    <a href="{{ route('assesspay.cashier.history') }}">History</a>
@endsection
@section('header')
    <h1>Cashier desk</h1>
    <p>Create the amount each enrolled student needs to pay.</p>
@endsection
@section('content')
<div class="ap-grid">
    <div class="ap-card ap-card--highlight">
        <h3>Open payables</h3>
        <div class="ap-value">{{ count($openPayables ?? []) }}</div>
    </div>
    <div class="ap-card">
        <h3>Total collected (paid)</h3>
        <div class="ap-value">₱{{ number_format($totalCollected, 2) }}</div>
    </div>
</div>

@if(session('success'))
    <div class="ap-section-note" style="color:#166534">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="ap-section-note" style="color:#991b1b">{{ session('error') }}</div>
@endif

<div class="ap-section" id="create-payable">
    <h2>Create payable</h2>
    <form class="ap-form" id="enrolledAssessmentForm" method="POST" action="{{ route('assesspay.cashier.payables') }}">
        @csrf
        <input type="hidden" name="source" value="deoris">
        <div class="ap-form-row">
            <label for="enrolledStudentSelect">Approved and enrolled student</label>
            <select id="enrolledStudentSelect" name="source_id" class="ap-search-input" required>
                <option value="">Select a student</option>
                @forelse($eligibleStudents ?? [] as $eligibleStudent)
                    <option value="{{ $eligibleStudent['id'] }}" @selected(old('source_id') === $eligibleStudent['id'])>
                        {{ $eligibleStudent['name'] }} - {{ $eligibleStudent['student_number'] ?: $eligibleStudent['email'] }}
                    </option>
                @empty
                    <option value="">No approved enrolled students found</option>
                @endforelse
            </select>
            <p class="ap-section-note" id="enrolledSelectedLabel" style="display:none"></p>
        </div>
        <div class="ap-grid">
            <div class="ap-form-row">
                <label for="assessSchoolYear">School year</label>
                <select id="assessSchoolYear" name="school_year" required>
                    <option value="">Select school year</option>
                    @foreach($schoolYears ?? [] as $schoolYear)
                        <option value="{{ $schoolYear }}" @selected(old('school_year') === $schoolYear)>{{ $schoolYear }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ap-form-row">
                <label for="assessTerm">Term</label>
                <select id="assessTerm" name="term">
                    <option value="">Select term</option>
                </select>
            </div>
        </div>
        <div class="ap-form-row">
            <label for="assessTuition">Amount to pay (₱)</label>
            <input type="number" id="assessTuition" name="tuition_amount" step="0.01" min="1" value="{{ old('tuition_amount', 0) }}" required>
            <input type="hidden" id="assessMisc" name="misc_amount" value="{{ old('misc_amount', 0) }}">
            <input type="hidden" id="assessOther" name="other_amount" value="{{ old('other_amount', 0) }}">
        </div>
        <div class="ap-form-row">
            <label for="assessDescription">Description</label>
            <input type="text" id="assessDescription" name="description" value="{{ old('description') }}" placeholder="Tuition assessment">
        </div>
        <div class="ap-form-actions">
            <button type="submit" class="ap-btn ap-btn-primary">Create payable</button>
        </div>
    </form>
</div>

<div class="ap-section" id="payables">
    <h2>Open payables</h2>
    <div class="ap-table-wrap">
        <table class="ap-table">
            <thead><tr><th>Student</th><th>Description</th><th>School year</th><th>Term</th><th>Amount due</th></tr></thead>
            <tbody>
            @forelse($openPayables ?? [] as $payable)
                <tr>
                    <td>
                        <strong>{{ $payable['student_name'] }}</strong>
                        @if($payable['student_number'])
                            <br><span class="ap-result-meta">{{ $payable['student_number'] }}</span>
                        @endif
                    </td>
                    <td>{{ $payable['description'] }}</td>
                    <td>{{ $payable['school_year'] }}</td>
                    <td>{{ $payable['term'] }}</td>
                    <td>₱{{ number_format((float) $payable['remaining'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="ap-empty">No open payables</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script>
window.AssessPayEligibleStudents = @json($eligibleStudents ?? []);
window.AssessPaySchoolYears = @json($schoolYears ?? []);
window.AssessPayTermsBySchoolYear = @json($termsBySchoolYear ?? []);
window.AssessPayOldTerm = @json(old('term'));

(function initEnrolledDetection() {
    const select = document.getElementById('enrolledStudentSelect');
    const label = document.getElementById('enrolledSelectedLabel');
    const schoolYear = document.getElementById('assessSchoolYear');
    const term = document.getElementById('assessTerm');
    if (!select) return;

    let students = [];

    function fillAmounts(item) {
        if (item.school_year) {
            schoolYear.value = item.school_year;
        }

        renderTerms();

        document.getElementById('assessDescription').value = item.program
            ? `${item.program} payable - ${item.name}`
            : `Payable - ${item.name}`;
    }

    function renderTerms() {
        const selectedYear = schoolYear.value;
        const terms = (window.AssessPayTermsBySchoolYear || {})[selectedYear] || [];
        const options = terms.length ? terms : ['Annual'];
        term.innerHTML = '<option value="">Select term</option>' + options
            .map(value => `<option value="${value}">${value}</option>`)
            .join('');
        if (window.AssessPayOldTerm) {
            term.value = window.AssessPayOldTerm;
        } else if (options.length === 1) {
            term.value = options[0];
        }
    }

    function renderStudents() {
        window.AssessPaySelectedEnrollment = null;
        label.style.display = 'none';
        students = window.AssessPayEligibleStudents || [];
        renderTerms();
    }

    select.addEventListener('change', () => {
        const selected = students.find(student => String(student.id) === String(select.value));
        window.AssessPaySelectedEnrollment = selected || null;
        label.textContent = selected
            ? `Selected: ${selected.name} (${selected.admission_status}, ${selected.enrollment_status})`
            : '';
        label.style.display = selected ? 'block' : 'none';
        if (selected) fillAmounts(selected);
    });

    schoolYear.addEventListener('change', renderTerms);

    renderStudents();
    if (select.value) {
        select.dispatchEvent(new Event('change'));
    }
})();

</script>
@endpush
