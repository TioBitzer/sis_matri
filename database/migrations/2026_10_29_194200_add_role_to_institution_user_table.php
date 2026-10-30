<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_user', function (Blueprint $table): void {
            // Nunca conceder privilegios administrativos por defecto.
            $table->string('role', 32)->default('member');
        });
    }

    public function down(): void
    {
        Schema::table('institution_user', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
