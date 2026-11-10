<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('radius_meters')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('base_location_id')
                ->nullable()
                ->after('role')
                ->constrained('work_locations')
                ->nullOnDelete();
        });

        Schema::create('work_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_location_id')
                ->nullable()
                ->constrained('work_locations')
                ->nullOnDelete();
            $table->foreignId('leave_request_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_online')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'start_date', 'end_date']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('work_location_id')
                ->nullable()
                ->constrained('work_locations')
                ->nullOnDelete();
            $table->foreignId('work_assignment_id')
                ->nullable()
                ->constrained('work_assignments')
                ->nullOnDelete();
            $table->string('attendance_mode', 30)->default('onsite');
            $table->string('verification_status', 30)->default('recorded');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('work_assignment_id');
            $table->dropConstrainedForeignId('work_location_id');
            $table->dropColumn([
                'attendance_mode',
                'verification_status',
                'reviewed_at',
                'review_note',
            ]);
        });

        Schema::dropIfExists('work_assignments');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('base_location_id');
        });

        Schema::dropIfExists('work_locations');
    }
};
