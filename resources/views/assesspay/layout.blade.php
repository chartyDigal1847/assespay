<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EntryEase') — DEORIS</title>
    <link rel="stylesheet" href="{{ asset('css/assesspay.css') }}">
    <script>
        window.ASSESSPAY_API    = "{{ url('/api/v1') }}";
        window.ASSESSPAY_ROLE   = "{{ session('sso_role', session('assesspay_role', 'guest')) }}";
        window.ASSESSPAY_STUDENT_ID = "{{ session('sso_id', session('assesspay_portal_id', '')) }}";
        window.REVERB_KEY       = "{{ config('broadcasting.connections.reverb.key') }}";
        window.REVERB_HOST      = "{{ config('broadcasting.connections.reverb.options.host') }}";
        window.REVERB_PORT      = {{ config('broadcasting.connections.reverb.options.port', 8084) }};
        window.REVERB_SCHEME    = "{{ config('broadcasting.connections.reverb.options.scheme', 'https') }}";
    </script>
</head>
<body class="ap-body">
<header class="ap-header">
    <nav class="ap-nav">@yield('nav')</nav>
</header>
<main class="ap-main">
    @hasSection('header')
        <div class="ap-page-header">@yield('header')</div>
    @endif
    @yield('content')
</main>
<script src="{{ asset('js/assesspay-api.js') }}?v={{ filemtime(public_path('js/assesspay-api.js')) }}"></script>
@if(config('assesspay.realtime.enabled'))
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script>
(function () {
    if (!window.REVERB_KEY || !window.Echo || !window.Pusher) return;

    window.Pusher.logToConsole = false;

    window.Echo = new Echo({
        broadcaster:     'reverb',
        key:             window.REVERB_KEY,
        wsHost:          window.REVERB_HOST,
        wsPort:          window.REVERB_PORT,
        wssPort:         window.REVERB_PORT,
        forceTLS:        window.REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint:    '/broadcasting/auth',
    });

    window.Echo.connector?.pusher?.connection?.bind('error', (error) => {
        console.warn('[AssessPay] Realtime unavailable', error);
    });

    const role = window.ASSESSPAY_ROLE;

    // ── Student: live balance + receipt notifications ──────────────────────
    if (role === 'student') {
        const studentEl = document.querySelector('[data-student-local-id]');
        const studentLocalId = studentEl ? parseInt(studentEl.dataset.studentLocalId) : null;

        if (studentLocalId) {
            window.Echo.private('assesspay.student.' + studentLocalId)
                .listen('.balance.updated', (e) => {
                    document.querySelectorAll('[data-balance-card]').forEach(el => {
                        el.textContent = '₱' + Number(e.current_balance)
                            .toLocaleString(undefined, { minimumFractionDigits: 2 });
                    });
                    AssessPay.toast('Balance updated: ₱' + Number(e.current_balance)
                        .toLocaleString(undefined, { minimumFractionDigits: 2 }), 'success');
                })
                .listen('.payment.confirmed', (e) => {
                    AssessPay.toast('Payment confirmed — receipt ' + (e.receipt_number || ''), 'success');
                    setTimeout(() => location.reload(), 2000);
                })
                .listen('.receipt.generated', (e) => {
                    AssessPay.toast('Receipt ' + e.receipt_number + ' is ready', 'success');
                });
        }
    }

    // ── Cashier: live payment confirmation feed ────────────────────────────
    if (role === 'cashier' || role === 'admin') {
        window.Echo.private('assesspay.cashier')
            .listen('.payment.confirmed', (e) => {
                AssessPay.toast('Payment #' + e.payment_id + ' confirmed (₱' +
                    Number(e.amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) + ')', 'success');
            });
    }
})();
</script>
@endif
@stack('scripts')
</body>
</html>
