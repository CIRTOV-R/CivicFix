<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action_type', 50)->index();
            $table->string('target_table', 50);
            $table->unsignedBigInteger('target_id');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['target_table', 'target_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION civicfix_forbid_audit_mutation() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs is immutable (attempted %)', TG_OP;
END;
$$ LANGUAGE plpgsql;
SQL);
            DB::statement(<<<'SQL'
CREATE TRIGGER audit_logs_immutable
BEFORE UPDATE OR DELETE ON audit_logs
FOR EACH ROW EXECUTE FUNCTION civicfix_forbid_audit_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_immutable ON audit_logs');
            DB::statement('DROP FUNCTION IF EXISTS civicfix_forbid_audit_mutation()');
        }

        Schema::dropIfExists('audit_logs');
    }
};
