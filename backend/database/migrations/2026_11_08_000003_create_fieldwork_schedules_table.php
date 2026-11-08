<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fieldwork_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('portfolio_id')->constrained('portfolios')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->date('schedule_date');
            $table->string('status')->default('pending');
            $table->string('location');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fieldwork_schedules');
    }
};
