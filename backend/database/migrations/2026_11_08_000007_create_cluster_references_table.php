<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_references', function (Blueprint $table) {
            $table->id();
            $table->string('cluster')->index();
            $table->string('location')->nullable();
            $table->string('province')->nullable();
            $table->string('region')->nullable();
            $table->string('coverage')->nullable();
            $table->string('assigned_agent_name')->nullable()->index();
            $table->string('zip')->nullable();
            $table->foreignId('import_id')->constrained('import_history')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['cluster', 'location', 'province'], 'cluster_reference_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_references');
    }
};
