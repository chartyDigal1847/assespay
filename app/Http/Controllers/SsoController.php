<?php

namespace App\Http\Controllers;

use App\Services\PortalUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SsoController extends Controller
{
    public function __construct(protected PortalUserService $portalUsers) {}

    private function debugLog(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        try {
            $payload = json_encode([
                'sessionId' => '0cc008',
                'runId' => 'run6',
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) floor(microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return;
            }
            file_put_contents('C:/xampp/htdocs/deoris/debug-0cc008.log', $payload . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Ignore debug log failures.
        }
    }

    /**
     * POST /sso/exchange — validate token with DEORIS Auth, hydrate session.
     */
    public function exchange(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string|max:500',
                'embedded' => 'sometimes|boolean',
            ]);
            // #region agent log
            $this->debugLog('H17', 'AssessPay\\SsoController::exchange:entry', 'assesspay exchange called', [
                'hasToken' => !empty($validated['token']),
                'embedded' => (bool) ($validated['embedded'] ?? false),
                'sessionId' => $request->session()->getId(),
            ]);
            // #endregion

            $tokenString = $validated['token'];
            $embedded = $validated['embedded'] ?? false;

            $response = $this->deorisHttp($tokenString)->post(
                rtrim(config('services.auth.url', config('assesspay.portal.auth_url')), '/')
                    .config('assesspay.portal.sso_exchange_path', '/api/v1/sso/exchange'),
                ['token' => $tokenString]
            );
            // #region agent log
            $this->debugLog('H17', 'AssessPay\\SsoController::exchange:portalResponse', 'assesspay portal exchange response', [
                'status' => $response->status(),
                'ok' => $response->ok(),
            ]);
            // #endregion

            if (! $response->ok()) {
                Log::warning('[AssessPay][SSO] Token validation failed', [
                    'status' => $response->status(),
                ]);

                return response()->json(['success' => false, 'error' => 'invalid_token'], 401);
            }

            $data = $response->json();

            if (empty($data['user']['id'])) {
                // #region agent log
                $this->debugLog('H17', 'AssessPay\\SsoController::exchange:invalidPayload', 'assesspay payload missing user id', [
                    'hasUser' => !empty($data['user']),
                ]);
                // #endregion
                return response()->json(['success' => false, 'error' => 'missing_user'], 401);
            }

            $portalUser = [
                'id' => $data['user']['id'],
                'name' => $data['user']['name'] ?? 'User',
                'email' => $data['user']['email'] ?? '',
                'role' => $data['user']['role'] ?? 'student',
            ];

            $mappedRole = $this->portalUsers->mapPortalRole($portalUser['role']);
            $portalUser['role'] = $mappedRole;

            $this->portalUsers->hydrateSession($request, $portalUser, $embedded);
            $this->portalUsers->ensureStudentRecord($request);
            // #region agent log
            $this->debugLog('H17', 'AssessPay\\SsoController::exchange:sessionHydrated', 'assesspay session hydrated after exchange', [
                'ssoId' => (string) $portalUser['id'],
                'role' => $mappedRole,
                'sessionId' => $request->session()->getId(),
            ]);
            // #endregion

            Log::info('[AssessPay][SSO] Authenticated via DEORIS', [
                'portal_id' => $portalUser['id'],
                'role' => $mappedRole,
            ]);

            $dashboardRoute = $this->portalUsers->dashboardRouteForRole($mappedRole);

            return response()->json([
                'success'  => true,
                'user'     => $portalUser,
                'embedded' => $embedded,
                'redirect' => route($dashboardRoute),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'error' => 'validation_error'], 400);
        } catch (\Exception $e) {
            Log::error('[AssessPay][SSO] Exchange error', ['error' => $e->getMessage()]);

            $error = 'exchange_error';
            if (str_contains($e->getMessage(), 'SSL certificate')) {
                $error = 'auth_ssl_error';
            }

            return response()->json(['success' => false, 'error' => $error], 500);
        }
    }

    private function deorisHttp(string $token): PendingRequest
    {
        $client = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $verifySsl = config('services.auth.verify_ssl', config('assesspay.portal.verify_ssl', true));
        if (! $verifySsl) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * GET /api/sso/heartbeat
     */
    public function heartbeat(Request $request): JsonResponse
    {
        if (! $this->portalUsers->isAuthenticated($request)) {
            return response()->json(['valid' => false, 'error' => 'no_session']);
        }

        return response()->json([
            'valid' => true,
            'user' => $this->portalUsers->sessionUser($request),
        ]);
    }

    /**
     * POST /api/sso/revoke
     */
    public function revoke(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string|max:500']);
        $request->session()->flush();

        return response()->json(['success' => true]);
    }
}
