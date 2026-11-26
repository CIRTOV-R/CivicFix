<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crews', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('specialty', 50)->index();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parish_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_phone', 30);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('current_capacity')->default(5);
            $table->unsignedInteger('current_load')->default(0);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('last_location_update')->nullable();
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE crews ADD COLUMN location geography(Point, 4326)');
            DB::statement('CREATE INDEX idx_crews_location_gist ON crews USING GIST (location)');
            DB::statement('CREATE INDEX idx_crews_specialty_active ON crews (specialty, is_active)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crews');
    }
};
