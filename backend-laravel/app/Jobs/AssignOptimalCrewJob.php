<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Incidents\AssignOptimalCrewAction;
use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class AssignOptimalCrewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private readonly Incident $incident) {}

    public function handle(AssignOptimalCrewAction $action): void
    {
        $action->execute($this->incident->fresh());
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('AssignOptimalCrewJob failed', [
            'incident_id' => $this->incident->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
