<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingSubtype = ! Schema::hasColumn('leave_requests', 'subtype');
        $missingAttachmentPath = ! Schema::hasColumn('leave_requests', 'attachment_path');
        $missingSupervisorStatus = ! Schema::hasColumn('leave_requests', 'supervisor_status');
        $missingSupervisorReviewer = ! Schema::hasColumn('leave_requests', 'supervisor_reviewed_by');
        $missingSupervisorReviewedAt = ! Schema::hasColumn('leave_requests', 'supervisor_reviewed_at');

        if (! $missingSubtype
            && ! $missingAttachmentPath
            && ! $missingSupervisorStatus
            && ! $missingSupervisorReviewer
            && ! $missingSupervisorReviewedAt) {
            return;
        }

        Schema::table('leave_requests', function (Blueprint $table) use (
            $missingSubtype,
            $missingAttachmentPath,
            $missingSupervisorStatus,
            $missingSupervisorReviewer,
            $missingSupervisorReviewedAt,
        ) {
            if ($missingSubtype) {
                $table->string('subtype', 40)->nullable()->after('type');
            }

            if ($missingAttachmentPath) {
                $table->string('attachment_path')->nullable()->after('details');
            }

            if ($missingSupervisorStatus) {
                $table->string('supervisor_status', 20)->default('pending')->after('status');
            }

            if ($missingSupervisorReviewer) {
                $table->foreignId('supervisor_reviewed_by')->nullable()
                    ->after('reviewed_by')->constrained('users')->nullOnDelete();
            }

            if ($missingSupervisorReviewedAt) {
                $table->timestamp('supervisor_reviewed_at')->nullable()->after('supervisor_reviewed_by');
            }
        });
    }

    public function down(): void
    {
        // A rollback must not remove columns that may already predate this repair.
    }
};
