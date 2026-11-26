<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Incidents\AssignManualCrewAction;
use App\Actions\Incidents\CreateIncidentAction;
use App\Actions\Incidents\RejectIncidentAction;
use App\Actions\Incidents\ResolveIncidentAction;
use App\Actions\Incidents\StartIncidentAction;
use App\Enums\IncidentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignIncidentRequest;
use App\Http\Requests\Api\V1\IndexIncidentRequest;
use App\Http\Requests\Api\V1\RejectIncidentRequest;
use App\Http\Requests\Api\V1\ResolveIncidentRequest;
use App\Http\Requests\Api\V1\StartIncidentRequest;
use App\Http\Requests\Api\V1\StoreIncidentRequest;
use App\Http\Resources\Api\V1\IncidentResource;
use App\Models\Crew;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class IncidentController extends Controller
{
    public function index(IndexIncidentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('viewAny', Incident::class);

        $incidents = Incident::query()
            ->with(['category', 'crew', 'parish', 'media'])
            ->when(! $user->isStaff(), fn ($query) => $query->where('citizen_id', $user->id))
            ->when($user->hasRole('CUADRILLA'), fn ($query) => $query->where('crew_id', $user->crew_id))
            ->when(
                $request->input('status'),
                fn ($query, $status) => $query->byStatus(IncidentStatus::from($status)),
            )
            ->when($request->input('priority'), fn ($query, $priority) => $query->where('priority', $priority))
            ->latest()
            ->paginate((int) ($request->input('per_page') ?? 15));

        return IncidentResource::collection($incidents)->response();
    }

    public function store(StoreIncidentRequest $request, CreateIncidentAction $action): JsonResponse
    {
        $this->authorize('create', Incident::class);

        /** @var User $citizen */
        $citizen = $request->user();
        $incident = $action->execute(
            $request->validated(),
            $request->file('before_photo'),
            $citizen,
            (string) $request->header('X-Idempotency-Key'),
        );

        return (new IncidentResource($incident->load('media')))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(Incident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        return (new IncidentResource($incident->load([
            'category',
            'crew.parish',
            'citizen',
            'parish',
            'media',
            'auditLogs.user',
        ])))->response();
    }

    public function assign(
        AssignIncidentRequest $request,
        Incident $incident,
        AssignManualCrewAction $action,
    ): JsonResponse {
        $this->authorize('assign', $incident);

        $crew = Crew::findOrFail((int) $request->validated('crew_id'));
        $incident = $action->execute($incident, $crew);

        return (new IncidentResource($incident->load(['crew', 'category'])))->response();
    }

    public function start(
        StartIncidentRequest $request,
        Incident $incident,
        StartIncidentAction $action,
    ): JsonResponse {
        $this->authorize('start', $incident);

        $incident = $action->execute(
            $incident,
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
        );

        return (new IncidentResource($incident->load(['crew', 'category', 'media'])))->response();
    }

    public function resolve(
        ResolveIncidentRequest $request,
        Incident $incident,
        ResolveIncidentAction $action,
    ): JsonResponse {
        $this->authorize('resolve', $incident);

        $incident = $action->execute(
            $incident,
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
            $request->file('after_photo'),
        );

        return (new IncidentResource($incident->load(['crew', 'category', 'media'])))->response();
    }

    public function reject(
        RejectIncidentRequest $request,
        Incident $incident,
        RejectIncidentAction $action,
    ): JsonResponse {
        $this->authorize('reject', $incident);

        $incident = $action->execute($incident, (string) $request->validated('reason'));

        return (new IncidentResource($incident->load(['crew', 'category'])))->response();
    }
}
