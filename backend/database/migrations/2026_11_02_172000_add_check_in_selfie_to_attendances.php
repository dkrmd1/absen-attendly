<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('check_in_selfie_path')->nullable();
            $table->string('selfie_review_status', 30)->default('not_required');
            $table->foreignId('selfie_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selfie_reviewed_at')->nullable();
            $table->text('selfie_review_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selfie_reviewed_by');
            $table->dropColumn([
                'check_in_selfie_path',
                'selfie_review_status',
                'selfie_reviewed_at',
                'selfie_review_note',
            ]);
        });
    }
};
