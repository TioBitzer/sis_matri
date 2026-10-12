<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_units', function (Blueprint $table) {
            $table->unsignedInteger('credits')->nullable()->after('unit_type');
            $table->unsignedInteger('hours')->nullable()->after('credits');
        });
    }

    public function down(): void
    {
        Schema::table('academic_units', function (Blueprint $table) {
            $table->dropColumn(['credits', 'hours']);
        });
    }
};