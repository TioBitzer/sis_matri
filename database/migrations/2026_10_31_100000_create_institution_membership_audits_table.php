<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_membership_audits', function (Blueprint $table): void {
            $table->id();
            // No se usan FK: el historial mantiene IDs aun si se eliminan las entidades.
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('target_user_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_type', 16); // user | console
            $table->string('action', 32); // created | updated | deactivated | bootstrap_admin
            $table->string('old_role', 32)->nullable();
            $table->string('new_role', 32);
            $table->boolean('old_is_active')->nullable();
            $table->boolean('new_is_active');
            $table->unsignedBigInteger('old_person_id')->nullable();
            $table->unsignedBigInteger('new_person_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // Sin updated_at: eventos insert-only en el flujo de la aplicacion.
            $table->index(['institution_id', 'id'], 'ima_institution_id_index');
            $table->index(['membership_id', 'id'], 'ima_membership_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_membership_audits');
    }
};
