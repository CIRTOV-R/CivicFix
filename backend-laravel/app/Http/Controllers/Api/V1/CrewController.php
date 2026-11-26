<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Incidents\UpdateCrewLocationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCrewLocationRequest;
use App\Http\Resources\Api\V1\CrewResource;
use App\Models\Crew;
use Illuminate\Http\JsonResponse;

final class CrewController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Crew::class);

        return CrewResource::collection(Crew::query()->with('parish')->orderBy('name')->get())->response();
    }

    public function updateLocation(
        UpdateCrewLocationRequest $request,
        Crew $crew,
        UpdateCrewLocationAction $action,
    ): JsonResponse {
        $this->authorize('updateLocation', $crew);

        $crew = $action->execute(
            $crew,
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
        );

        return (new CrewResource($crew->load('parish')))->response();
    }
}
