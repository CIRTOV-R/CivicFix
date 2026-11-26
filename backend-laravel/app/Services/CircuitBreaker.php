<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

final class CircuitBreaker
{
    private const KEY = 'civicfix:routing:circuit';

    public function allowsCall(): bool
    {
        $state = $this->state();

        if ($state['status'] !== 'open') {
            return true;
        }

        if ((int) $state['open_until'] <= time()) {
            Cache::put(self::KEY, ['status' => 'half_open', 'failures' => $state['failures'], 'open_until' => 0], now()->addMinutes(5));

            return true;
        }

        return false;
    }

    public function recordSuccess(): void
    {
        Cache::forget(self::KEY);
    }

    public function recordFailure(): void
    {
        $state = $this->state();
        $failures = (int) $state['failures'] + 1;
        $threshold = (int) config('services.routing.circuit_failures', 3);

        if ($failures >= $threshold) {
            $ttl = (int) config('services.routing.circuit_ttl', 30);

            Cache::put(self::KEY, [
                'status' => 'open',
                'failures' => $failures,
                'open_until' => time() + $ttl,
            ], now()->addSeconds($ttl));

            return;
        }

        Cache::put(self::KEY, [
            'status' => 'closed',
            'failures' => $failures,
            'open_until' => 0,
        ], now()->addMinutes(5));
    }

    public function isOpen(): bool
    {
        $state = $this->state();

        return $state['status'] === 'open' && (int) $state['open_until'] > time();
    }

    private function state(): array
    {
        $state = Cache::get(self::KEY);

        return is_array($state) ? $state : ['status' => 'closed', 'failures' => 0, 'open_until' => 0];
    }
}
