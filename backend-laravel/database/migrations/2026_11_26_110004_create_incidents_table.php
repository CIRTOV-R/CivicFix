<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('citizen_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('crew_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('parish_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->string('address_reference', 255)->nullable();
            $table->string('priority', 20)->default('MEDIA');
            $table->string('status', 30)->default('REPORTADO');
            $table->text('rejection_reason')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('sla_due_at');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['status', 'priority', 'sla_due_at'], 'idx_incidents_sla_priority');
            $table->index('citizen_id');
            $table->index('crew_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE incidents ADD COLUMN location geography(Point, 4326)');
            DB::statement('CREATE INDEX idx_incidents_location_gist ON incidents USING GIST (location)');
            DB::statement('CREATE INDEX idx_incidents_status_btree ON incidents (status)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
