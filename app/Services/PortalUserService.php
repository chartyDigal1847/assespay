<?php

namespace App\Services;

use App\Enums\AssessPayRole;
use App\Models\Student;
use Illuminate\Http\Request;

/**
 * Identity from DEORIS Portal SSO session only (no portal_user_sync mirror).
 */
class PortalUserService
{
    public const SESSION_PORTAL_ID = 'sso_id';

    public const SESSION_NAME = 'sso_name';

    public const SESSION_EMAIL = 'sso_email';

    public const SESSION_ROLE = 'sso_role';

    public const SESSION_EMBEDDED = 'sso_embedded';

    public const SESSION_AUTHENTICATED_AT = 'sso_authenticated_at';

    public function isAuthenticated(Request $request): bool
    {
        return $request->session()->has(self::SESSION_PORTAL_ID)
            && $request->session()->has(self::SESSION_ROLE);
    }

    public function portalId(Request $request): ?string
    {
        return $request->session()->get(self::SESSION_PORTAL_ID)
            ?? $request->session()->get('assesspay_portal_id');
    }

    public function role(Request $request): ?string
    {
        return $request->session()->get(self::SESSION_ROLE)
            ?? $request->session()->get('assesspay_role');
    }

    public function mapPortalRole(string $portalRole): string
    {
        return match ($portalRole) {
            'admin' => 'admin',
            'hr', 'cashier' => 'cashier',
            default => 'student',
        };
    }

    /**
     * @param  array{id: string|int, name?: string, email?: string, role?: string}  $portalUser
     */
    public function hydrateSession(Request $request, array $portalUser, bool $embedded = false): void
    {
        $role = $this->mapPortalRole($portalUser['role'] ?? 'student');

        $request->session()->flush();
        // Note: do NOT call regenerate() here — it changes the session ID after
        // the cookie has already been sent, causing the next request to arrive
        // with the old (now-invalid) session ID and appear unauthenticated.

        $request->session()->put([
            self::SESSION_PORTAL_ID => (string) $portalUser['id'],
            self::SESSION_NAME => $portalUser['name'] ?? 'User',
            self::SESSION_EMAIL => strtolower($portalUser['email'] ?? ''),
            self::SESSION_ROLE => $role,
            self::SESSION_EMBEDDED => $embedded,
            self::SESSION_AUTHENTICATED_AT => now()->timestamp,
        ]);
    }

    public function sessionUser(Request $request): array
    {
        return [
            'id' => $this->portalId($request),
            'name' => $request->session()->get(self::SESSION_NAME, 'User'),
            'email' => $request->session()->get(self::SESSION_EMAIL, ''),
            'role' => $this->role($request),
        ];
    }

    /**
     * Ensure a billing student row exists for the current portal student session.
     */
    public function ensureStudentRecord(Request $request): ?Student
    {
        if ($this->role($request) !== 'student') {
            return null;
        }

        $portalId = $this->portalId($request);
        $email = strtolower($request->session()->get(self::SESSION_EMAIL, ''));
        $name = $request->session()->get(self::SESSION_NAME, 'User');

        if (! $portalId) {
            return null;
        }

        $student = Student::updateOrCreate(
            ['portal_user_id' => $portalId],
            [
                'student_id' => 'DEORIS-'.$portalId,
                'name' => $name,
                'email' => $email,
                'program' => '—',
                'year_level' => '—',
                'status' => 'active',
            ]
        );

        app(BillingAccountService::class)->ensureForStudent($student);

        return $student;
    }

    public function studentIdForSession(Request $request): ?int
    {
        return $this->ensureStudentRecord($request)?->id;
    }

    public function dashboardRouteForRole(?string $role): string
    {
        return match ($role) {
            'admin' => 'assesspay.admin',
            'cashier' => 'assesspay.cashier',
            default => 'assesspay.student',
        };
    }
}
