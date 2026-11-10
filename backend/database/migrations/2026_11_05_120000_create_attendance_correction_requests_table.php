<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_group_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('original_check_in_at');
            $table->dateTime('original_check_out_at')->nullable();
            $table->dateTime('requested_check_in_at');
            $table->dateTime('requested_check_out_at')->nullable();
            $table->text('reason');
            $table->string('status', 30);
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('applied_at')->nullable();
            $table->text('application_note')->nullable();
            $table->timestamps();

            $table->index(['attendance_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('attendance_correction_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correction_request_id')
                ->constrained('attendance_correction_requests')
                ->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('stage', 20);
            $table->string('decision', 20);
            $table->text('note')->nullable();
            $table->dateTime('reviewed_at');

            $table->index(
                ['correction_request_id', 'reviewed_at'],
                'att_corr_reviews_request_time_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_reviews');
        Schema::dropIfExists('attendance_correction_requests');
    }
};
