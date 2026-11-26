<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DashboardResource;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('view-dashboard');

        $byStatus = [];
        foreach (IncidentStatus::cases() as $status) {
            $byStatus[$status->value] = Incident::query()->byStatus($status)->count();
        }

        $byPriority = [];
        foreach (IncidentPriority::cases() as $priority) {
            $byPriority[$priority->value] = Incident::query()->where('priority', $priority->value)->count();
        }

        $responseMinutes = [];
        Incident::query()
            ->whereNotNull('resolved_at')
            ->select(['created_at', 'resolved_at'])
            ->chunkById(500, function ($incidents) use (&$responseMinutes): void {
                foreach ($incidents as $incident) {
                    $responseMinutes[] = $incident->created_at->diffInMinutes($incident->resolved_at);
                }
            });

        $heatmap = Incident::query()
            ->select(['id', 'title', 'latitude', 'longitude', 'priority', 'status'])
            ->open()
            ->limit(500)
            ->get()
            ->map(fn (Incident $incident): array => [
                'id' => $incident->id,
                'title' => $incident->title,
                'latitude' => (float) $incident->latitude,
                'longitude' => (float) $incident->longitude,
                'priority' => $incident->priority?->value,
                'status' => $incident->status?->value,
            ])
            ->all();

        return (new DashboardResource([
            'summary' => [
                'total' => Incident::query()->count(),
                'open' => Incident::query()->open()->count(),
                'by_status' => $byStatus,
                'by_priority' => $byPriority,
                'avg_response_minutes' => $responseMinutes === []
                    ? 0
                    : (int) round(array_sum($responseMinutes) / count($responseMinutes)),
            ],
            'heatmap' => $heatmap,
        ]))->response();
    }
}
