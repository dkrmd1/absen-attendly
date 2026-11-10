<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('director_id')
                ->nullable()
                ->after('supervisor_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['director_id', 'is_active']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('ceo_status', 20)->default('not_required')->after('director_reviewed_at');
            $table->foreignId('ceo_reviewed_by')->nullable()->after('ceo_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('ceo_reviewed_at')->nullable()->after('ceo_reviewed_by');
            $table->string('commissioner_status', 20)->default('not_required')->after('ceo_reviewed_at');
            $table->foreignId('commissioner_reviewed_by')->nullable()->after('commissioner_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('commissioner_reviewed_at')->nullable()->after('commissioner_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commissioner_reviewed_by');
            $table->dropConstrainedForeignId('ceo_reviewed_by');
            $table->dropColumn([
                'ceo_status',
                'ceo_reviewed_at',
                'commissioner_status',
                'commissioner_reviewed_at',
            ]);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex(['director_id', 'is_active']);
            $table->dropConstrainedForeignId('director_id');
        });
    }
};
