<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CircuitBreaker;
use Tests\TestCase;

final class CircuitBreakerTest extends TestCase
{
    public function test_breaker_opens_after_threshold_and_blocks_calls(): void
    {
        config()->set('services.routing.circuit_failures', 2);

        $breaker = app(CircuitBreaker::class);

        $this->assertTrue($breaker->allowsCall());
        $breaker->recordFailure();
        $this->assertTrue($breaker->allowsCall());
        $breaker->recordFailure();

        $this->assertTrue($breaker->isOpen());
        $this->assertFalse($breaker->allowsCall());
    }

    public function test_success_resets_breaker(): void
    {
        config()->set('services.routing.circuit_failures', 1);

        $breaker = app(CircuitBreaker::class);
        $breaker->recordFailure();

        $this->assertTrue($breaker->isOpen());

        $breaker->recordSuccess();

        $this->assertFalse($breaker->isOpen());
        $this->assertTrue($breaker->allowsCall());
    }
}
