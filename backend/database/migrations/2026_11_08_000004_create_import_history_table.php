<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_history', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('uploaded_by')->default('Admin');
            $table->integer('total_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('duplicate_rows')->default(0);
            $table->integer('invalid_rows')->default(0);
            $table->integer('skipped_rows')->default(0);
            $table->json('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_history');
    }
};
