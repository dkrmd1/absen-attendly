<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('subtype', 40)->nullable()->after('type');
            $table->string('attachment_path')->nullable()->after('details');
            $table->string('supervisor_status', 20)->default('pending')->after('status');
            $table->foreignId('supervisor_reviewed_by')->nullable()
                ->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_reviewed_at')->nullable()->after('supervisor_reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_reviewed_by');
            $table->dropColumn([
                'subtype',
                'attachment_path',
                'supervisor_status',
                'supervisor_reviewed_at',
            ]);
        });
    }
};
