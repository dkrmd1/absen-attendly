<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_period_minutes')->default(0);
            $table->json('work_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('work_shift_id')
                ->nullable()
                ->after('department_id')
                ->constrained('work_shifts')
                ->nullOnDelete();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('work_shift_id')
                ->nullable()
                ->after('department_id')
                ->constrained('work_shifts')
                ->nullOnDelete();
            $table->string('shift_name')->nullable();
            $table->time('shift_start_time')->nullable();
            $table->time('shift_end_time')->nullable();
            $table->unsignedSmallInteger('shift_grace_period_minutes')->nullable();
            $table->json('shift_work_days')->nullable();
            $table->boolean('is_scheduled_workday')->nullable();
            $table->unsignedSmallInteger('late_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_shift_id');
            $table->dropColumn([
                'shift_name',
                'shift_start_time',
                'shift_end_time',
                'shift_grace_period_minutes',
                'shift_work_days',
                'is_scheduled_workday',
                'late_minutes',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_shift_id');
        });

        Schema::dropIfExists('work_shifts');
    }
};
