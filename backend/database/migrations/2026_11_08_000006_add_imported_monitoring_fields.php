<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_history', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('uploaded_by')->constrained('users')->nullOnDelete();
            $table->string('status')->default('completed')->after('file_name');
            $table->string('storage_path')->nullable()->after('status');
        });

        Schema::table('portfolios', function (Blueprint $table) {
            $table->string('on_hold')->nullable();
            $table->string('has_task')->nullable();
            $table->date('date_endorsed')->nullable();
            $table->string('expired')->nullable();
            $table->string('monthly_amort')->nullable();
            $table->string('dpd')->nullable();
            $table->text('particulars')->nullable();
            $table->string('upcoming_task')->nullable();
            $table->string('imported_area')->nullable()->index();
            $table->string('source_agent_name')->nullable();
            $table->string('location')->nullable()->index();
            $table->string('assignment_status')->default('unmatched')->index();
            $table->text('match_notes')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->foreignId('last_seen_import_id')->nullable()->constrained('import_history')->nullOnDelete();
        });

        Schema::table('fieldwork_schedules', function (Blueprint $table) {
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fieldwork_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['start_time', 'end_time']);
        });

        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_seen_import_id');
            $table->dropColumn([
                'on_hold',
                'has_task',
                'date_endorsed',
                'expired',
                'monthly_amort',
                'dpd',
                'particulars',
                'upcoming_task',
                'imported_area',
                'source_agent_name',
                'location',
                'assignment_status',
                'match_notes',
                'is_current',
            ]);
        });

        Schema::table('import_history', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['status', 'storage_path']);
        });
    }
};
