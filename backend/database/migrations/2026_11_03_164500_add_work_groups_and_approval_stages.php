<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('leader_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
            $table->index(['department_id', 'is_active']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('work_group_id')->nullable()->after('department_id')
                ->constrained('work_groups')->nullOnDelete();
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('work_group_id')->nullable()->after('department_id')
                ->constrained('work_groups')->nullOnDelete();
            $table->string('requester_role', 20)->default('employee')->after('work_group_id');
            $table->string('group_status', 20)->default('not_required')->after('status');
            $table->foreignId('group_reviewed_by')->nullable()->after('supervisor_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('group_reviewed_at')->nullable()->after('group_reviewed_by');
            $table->string('director_status', 20)->default('not_required')->after('group_reviewed_at');
            $table->foreignId('director_reviewed_by')->nullable()->after('director_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('director_reviewed_at')->nullable()->after('director_reviewed_by');
        });

        DB::table('leave_requests')->update([
            'department_id' => DB::raw('(SELECT department_id FROM users WHERE users.id = leave_requests.user_id)'),
            'work_group_id' => DB::raw('(SELECT work_group_id FROM users WHERE users.id = leave_requests.user_id)'),
            'requester_role' => DB::raw('(SELECT role FROM users WHERE users.id = leave_requests.user_id)'),
        ]);

        Schema::create('leave_request_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('stage', 20);
            $table->string('decision', 20);
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at');

            $table->index(['leave_request_id', 'reviewed_at']);
        });

        DB::table('leave_requests')
            ->whereIn('status', ['pending_admin'])
            ->update(['status' => 'pending_hr']);

        DB::table('leave_requests')
            ->where('status', 'pending')
            ->where('supervisor_status', 'approved')
            ->update(['status' => 'pending_hr']);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_reviews');

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('director_reviewed_by');
            $table->dropConstrainedForeignId('group_reviewed_by');
            $table->dropConstrainedForeignId('work_group_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn([
                'requester_role',
                'group_status',
                'group_reviewed_at',
                'director_status',
                'director_reviewed_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_group_id');
        });

        Schema::dropIfExists('work_groups');
    }
};
