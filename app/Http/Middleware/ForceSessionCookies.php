<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pin session cookie attributes and APP_KEY on every request.
 *
 * Reads directly from .env to bypass OPcache/env() contamination from
 * XAMPP cross-vhost worker reuse.
 */
class ForceSessionCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        $envPath = base_path('.env');
        $appKey  = $this->readEnvValue($envPath, 'APP_KEY');

        if ($appKey) {
            // Update config so Laravel's Crypt facade uses the correct key.
            // This must happen before EncryptCookies/StartSession run.
            config(['app.key' => $appKey]);

            // Rebind the encrypter singleton with the correct key.
            // Without this, the already-bound encrypter uses the contaminated key.
            try {
                $rawKey = str_starts_with($appKey, 'base64:')
                    ? base64_decode(substr($appKey, 7))
                    : $appKey;
                $cipher = config('app.cipher', 'AES-256-CBC');
                $encrypter = new \Illuminate\Encryption\Encrypter($rawKey, $cipher);
                app()->instance('encrypter', $encrypter);
            } catch (\Throwable $e) {
                // Non-fatal — log and continue with config-only fix
                \Illuminate\Support\Facades\Log::warning('[AssessPay] Encrypter rebind failed: ' . $e->getMessage());
            }
        }

        $sessionDomain = $this->readEnvValue($envPath, 'SESSION_DOMAIN');

        config([
            'session.cookie'    => $this->readEnvValue($envPath, 'SESSION_COOKIE') ?: 'assesspay_session',
            'session.domain'    => $this->normalizeNullableEnvValue($sessionDomain),
            'session.secure'    => true,
            'session.same_site' => 'none',
            'session.http_only' => true,
        ]);

        return $next($request);
    }

    private function readEnvValue(string $envFile, string $key): ?string
    {
        if (! is_readable($envFile)) {
            return null;
        }
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            if (trim(substr($line, 0, $eq)) !== $key) {
                continue;
            }
            $val = trim(substr($line, $eq + 1));
            if (strlen($val) >= 2 && $val[0] === '"'  && $val[-1] === '"')  {
                $val = substr($val, 1, -1);
            } elseif (strlen($val) >= 2 && $val[0] === "'" && $val[-1] === "'") {
                $val = substr($val, 1, -1);
            }
            return $val;
        }
        return null;
    }

    private function normalizeNullableEnvValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        return $value;
    }
}
