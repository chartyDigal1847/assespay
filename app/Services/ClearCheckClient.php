<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ClearCheckClient
{
    public function studentCleared(string $studentNumber): array
    {
        $cacheKey = config('assesspay.redis.prefix').'clearcheck:'.$studentNumber;

        return Cache::remember($cacheKey, config('assesspay.clearcheck.cache_ttl'), function () use ($studentNumber) {
            $url = rtrim(config('assesspay.clearcheck.api_url'), '/').'/clearance/'.$studentNumber;

            try {
                $response = Http::timeout(8)->acceptJson()->get($url);

                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable) {
                // fall through to local balance check only
            }

            return ['cleared' => null, 'source' => 'assesspay_fallback'];
        });
    }

    public function canCompleteAcademically(float $balance): bool
    {
        return $balance <= 0;
    }
}
