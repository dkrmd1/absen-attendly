<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceSelfieRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_deletes_selfies_older_than_ninety_days(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00'));

        $user = User::factory()->create();
        $expiredPath = 'attendance-selfies/expired.jpg';
        $recentPath = 'attendance-selfies/recent.jpg';
        Storage::disk('local')->put($expiredPath, 'expired');
        Storage::disk('local')->put($recentPath, 'recent');

        $expired = $user->attendances()->create([
            'work_date' => today()->subDays(91),
            'check_in_at' => now()->subDays(91),
            'check_in_selfie_path' => $expiredPath,
            'selfie_review_status' => 'submitted',
        ]);
        $recent = $user->attendances()->create([
            'work_date' => today()->subDays(89),
            'check_in_at' => now()->subDays(89),
            'check_in_selfie_path' => $recentPath,
            'selfie_review_status' => 'submitted',
        ]);

        $this->artisan('attendly:prune-attendance-selfies')
            ->expectsOutputToContain('Selfie kedaluwarsa dihapus: 1.')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing($expiredPath);
        Storage::disk('local')->assertExists($recentPath);
        $this->assertDatabaseHas('attendances', [
            'id' => $expired->id,
            'check_in_selfie_path' => null,
            'selfie_review_status' => 'expired',
        ]);
        $this->assertDatabaseHas('attendances', [
            'id' => $recent->id,
            'check_in_selfie_path' => $recentPath,
            'selfie_review_status' => 'submitted',
        ]);

        Carbon::setTestNow();
    }
}
