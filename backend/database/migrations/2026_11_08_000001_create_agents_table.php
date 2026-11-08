<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->unique();
            $table->string('agent_name');
            $table->string('contact_number')->nullable();
            $table->string('assigned_cluster')->nullable();
            $table->string('assigned_area')->nullable();
            $table->string('status')->default('active');
            $table->date('date_created')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
