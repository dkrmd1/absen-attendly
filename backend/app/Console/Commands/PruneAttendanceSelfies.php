<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneAttendanceSelfies extends Command
{
    protected $signature = 'attendly:prune-attendance-selfies';

    protected $description = 'Hapus selfie absensi yang sudah melewati masa simpan 90 hari';

    public function handle(): int
    {
        $cutoff = now()->subDays(90);
        $deleted = 0;
        $failed = false;

        Attendance::query()
            ->where(function ($query) use ($cutoff) {
                $query->where(function ($checkIn) use ($cutoff) {
                    $checkIn->whereNotNull('check_in_selfie_path')
                        ->where('check_in_at', '<', $cutoff);
                })->orWhere(function ($checkOut) use ($cutoff) {
                    $checkOut->whereNotNull('check_out_selfie_path')
                        ->where('check_out_at', '<', $cutoff);
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($attendances) use (&$deleted, &$failed) {
                foreach ($attendances as $attendance) {
                    $updates = [];
                    foreach (['check_in_selfie_path', 'check_out_selfie_path'] as $column) {
                        $path = $attendance->{$column};
                        $timestamp = $column === 'check_in_selfie_path'
                            ? $attendance->check_in_at
                            : $attendance->check_out_at;
                        if (! $path || ! $timestamp || $timestamp->gte(now()->subDays(90))) {
                            continue;
                        }
                        if (! Storage::disk('local')->delete($path)) {
                            $this->error("Gagal menghapus {$column} absensi #{$attendance->id}.");
                            $failed = true;

                            continue;
                        }
                        $updates[$column] = null;
                    }

                    if ($updates) {
                        if (array_key_exists('check_in_selfie_path', $updates)) {
                            $updates['selfie_review_status'] = 'expired';
                        }
                        $attendance->update($updates);
                        $deleted++;
                    }
                }
            });

        $this->info("Selfie kedaluwarsa dihapus: {$deleted}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
