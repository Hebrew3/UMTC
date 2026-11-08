<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('senior_agent')->index();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('senior_agent_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('senior_agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['senior_agent_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('senior_agent_assignments');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
