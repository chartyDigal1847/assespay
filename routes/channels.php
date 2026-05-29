<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| AssessPay Broadcast Channel Authorisation
|--------------------------------------------------------------------------
|
| Private channels are authorised here. The session-based portal identity
| (assesspay_portal_id, assesspay_role) is used — no Sanctum bearer token.
|
*/

// Student-specific channel — only the owning student may subscribe.
// Channel name: assesspay.student.{student_id}
Broadcast::channel('assesspay.student.{studentId}', function ($user, int $studentId) {
    // In AssessPay, "auth" is session-based, not model-based.
    // We resolve identity from the session directly.
    $portalId = session('assesspay_portal_id');
    $role     = session('assesspay_role');

    if (! $portalId) {
        return false;
    }

    // Cashiers and admins may subscribe to any student channel (for live desk updates)
    if (in_array($role, ['cashier', 'admin'])) {
        return ['role' => $role, 'portal_id' => $portalId];
    }

    // Students may only subscribe to their own channel
    $student = \App\Models\Student::where('portal_user_id', $portalId)->first();
    if ($student && $student->id === $studentId) {
        return ['role' => 'student', 'portal_id' => $portalId];
    }

    return false;
});

// Cashier broadcast channel — any authenticated cashier or admin may subscribe.
Broadcast::channel('assesspay.cashier', function ($user) {
    $role = session('assesspay_role');
    return in_array($role, ['cashier', 'admin'])
        ? ['role' => $role, 'portal_id' => session('assesspay_portal_id')]
        : false;
});
