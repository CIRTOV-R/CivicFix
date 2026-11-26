<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\MediaCollection;
use App\Enums\RoleName;
use App\Jobs\AssignOptimalCrewJob;
use App\Models\Category;
use App\Models\Crew;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class IncidentTest extends TestCase
{
    public function test_citizen_can_report_incident_with_idempotency_key(): void
    {
        Queue::fake();

        $category = Category::factory()->create();
        $citizen = User::factory()->withRole(RoleName::CIUDADANO)->create();
        Sanctum::actingAs($citizen);

        $response = $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/incidents', [
                'title' => 'Bache profundo en vía pública',
                'description' => 'Obstruye el tránsito en ambos sentidos.',
                'category_id' => $category->id,
                'latitude' => 8.2950,
                'longitude' => -62.7420,
                'address_reference' => 'Frente al mercado',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', IncidentStatus::REPORTADO->value);

        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseHas('incidents', ['citizen_id' => $citizen->id]);
        $this->assertDatabaseHas('audit_logs', [
            'target_table' => 'incidents',
            'action_type' => 'INCIDENT_CREATED',
        ]);
        Queue::assertPushed(AssignOptimalCrewJob::class);
    }

    public function test_duplicate_idempotency_key_returns_original_response(): void
    {
        Queue::fake();

        $category = Category::factory()->create();
        $citizen = User::factory()->withRole(RoleName::CIUDADANO)->create();
        Sanctum::actingAs($citizen);

        $key = (string) Str::uuid();
        $payload = [
            'title' => 'Reporte original',
            'description' => 'Descripción original.',
            'category_id' => $category->id,
            'latitude' => 8.2950,
            'longitude' => -62.7420,
        ];

        $first = $this->withHeader('X-Idempotency-Key', $key)
            ->postJson('/api/v1/incidents', $payload)
            ->assertCreated();

        $second = $this->withHeader('X-Idempotency-Key', $key)
            ->postJson('/api/v1/incidents', array_merge($payload, ['title' => 'Reporte duplicado']))
            ->assertCreated();

        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id'),
        );
        $this->assertSame('Reporte original', $second->json('data.title'));
        $this->assertDatabaseCount('incidents', 1);
    }

    public function test_idempotency_key_is_required(): void
    {
        $citizen = User::factory()->withRole(RoleName::CIUDADANO)->create();
        Sanctum::actingAs($citizen);

        $this->postJson('/api/v1/incidents', [
            'title' => 'Reporte sin clave',
            'description' => 'Descripción.',
            'category_id' => Category::factory()->create()->id,
            'latitude' => 8.2950,
            'longitude' => -62.7420,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'La cabecera X-Idempotency-Key debe ser un UUID válido.');
    }

    public function test_citizen_cannot_view_incident_from_another_citizen(): void
    {
        $owner = User::factory()->withRole(RoleName::CIUDADANO)->create();
        $other = User::factory()->withRole(RoleName::CIUDADANO)->create();
        $incident = Incident::factory()->for($owner, 'citizen')->create();

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/incidents/'.$incident->id)->assertForbidden();
    }

    public function test_assigned_crew_can_start_incident(): void
    {
        $crew = Crew::factory()->create();
        $crewUser = User::factory()->withRole(RoleName::CUADRILLA)->create(['crew_id' => $crew->id]);
        $incident = Incident::factory()->forCrew($crew)->assigned()->create();

        Sanctum::actingAs($crewUser);

        $this->postJson('/api/v1/incidents/'.$incident->id.'/start', [
            'latitude' => 8.2950,
            'longitude' => -62.7420,
        ])->assertOk()
            ->assertJsonPath('data.status', IncidentStatus::EN_PROCESO->value);
    }

    public function test_resolve_requires_after_photo_and_is_blocked_outside_geofence(): void
    {
        Storage::fake('public');

        $crew = Crew::factory()->create();
        $crewUser = User::factory()->withRole(RoleName::CUADRILLA)->create(['crew_id' => $crew->id]);
        $incident = Incident::factory()->forCrew($crew)->inProgress()->create([
            'latitude' => 8.2950,
            'longitude' => -62.7420,
        ]);

        Sanctum::actingAs($crewUser);

        $this->postJson('/api/v1/incidents/'.$incident->id.'/resolve', [
            'resolution_notes' => 'Reparación completada.',
            'after_photo' => UploadedFile::fake()->image('after.jpg', 120, 120),
            'latitude' => 10.5000,
            'longitude' => -65.0000,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'El cierre requiere estar a 50 metros o menos del sitio del reporte.');
    }

    public function test_resolve_succeeds_inside_geofence_and_attaches_evidence(): void
    {
        Storage::fake('public');

        $crew = Crew::factory()->create();
        $crewUser = User::factory()->withRole(RoleName::CUADRILLA)->create(['crew_id' => $crew->id]);
        $incident = Incident::factory()->forCrew($crew)->inProgress()->create([
            'latitude' => 8.2950,
            'longitude' => -62.7420,
        ]);

        Sanctum::actingAs($crewUser);

        $this->postJson('/api/v1/incidents/'.$incident->id.'/resolve', [
            'resolution_notes' => 'Reparación completada.',
            'after_photo' => UploadedFile::fake()->image('after.jpg', 120, 120),
            'latitude' => 8.29501,
            'longitude' => -62.74201,
        ])->assertOk()
            ->assertJsonPath('data.status', IncidentStatus::RESUELTO->value);

        $incident->refresh();
        $this->assertSame(1, $incident->getMedia(MediaCollection::AFTER_REPAIR->value)->count());
        $this->assertNotNull($incident->resolved_at);
    }

    public function test_supervisor_can_reject_incident(): void
    {
        $supervisor = User::factory()->withRole(RoleName::SUPERVISOR)->create();
        $incident = Incident::factory()->create();

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/incidents/'.$incident->id.'/reject', [
            'reason' => 'Reporte duplicado.',
        ])->assertOk()
            ->assertJsonPath('data.status', IncidentStatus::RECHAZADO->value);
    }

    public function test_supervisor_can_manually_assign_crew(): void
    {
        $supervisor = User::factory()->withRole(RoleName::SUPERVISOR)->create();
        $crew = Crew::factory()->create();
        $incident = Incident::factory()->create();

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/incidents/'.$incident->id.'/assign', [
            'crew_id' => $crew->id,
        ])->assertOk()
            ->assertJsonPath('data.status', IncidentStatus::ASIGNADO->value)
            ->assertJsonPath('data.crew.id', $crew->id);
    }
}
