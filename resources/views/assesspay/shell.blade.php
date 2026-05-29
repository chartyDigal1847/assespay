<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AssessPay — DEORIS</title>
    <link rel="stylesheet" href="{{ asset('css/assesspay.css') }}?v={{ filemtime(public_path('css/assesspay.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/deoris-module-theme.css') }}?v={{ filemtime(public_path('css/deoris-module-theme.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        window.ASSESSPAY_API_BASE = "{{ config('assesspay.service_url', config('app.url')) }}";
        window.PORTAL_ORIGIN = "{{ config('assesspay.portal.trusted_url') }}";
        window.SSO_TIMEOUT_MS = 15000;
        window.DEORIS_SSO_MODE = "module";
    </script>
</head>
<body class="ap-shell-body">
<div class="ap-shell">
    <div class="ap-shell-card">
        <div class="ap-spinner" aria-hidden="true"></div>
        <h1 class="ap-shell-title">AssessPay</h1>
        <p class="ap-shell-msg" id="assesspay-loader-msg">Opening your finance portal…</p>
        <p class="ap-shell-error" id="assesspay-loader-error" role="alert"></p>
        <p class="ap-shell-hint">Secure sign-in via your DEORIS account</p>
    </div>
</div>
<script src="{{ rtrim(config('assesspay.portal.trusted_url', config('app.portal_url', 'https://deoris.test')), '/') }}/module-bridge.js"></script>
<script src="{{ asset('js/assesment.js') }}?v={{ filemtime(public_path('js/assesment.js')) }}"></script>
</body>
</html>
