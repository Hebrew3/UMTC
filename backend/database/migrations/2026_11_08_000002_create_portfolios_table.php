<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->unique();
            $table->string('account_name');
            $table->string('ob')->nullable();
            $table->integer('days_handled')->nullable();
            $table->date('exp_date')->nullable();
            $table->string('product_line')->nullable();
            $table->string('client')->nullable();
            $table->string('service_type')->nullable();
            $table->string('classification')->nullable();
            $table->text('address');
            $table->string('contact')->nullable();
            $table->string('latest_status')->default('ACTIVE');
            $table->string('cluster')->nullable();
            $table->string('region')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('barangay')->nullable();
            $table->string('verification_status')->default('VERIFIED');
            $table->foreignId('assigned_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
