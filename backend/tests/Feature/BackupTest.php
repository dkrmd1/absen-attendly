<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LocalBackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_backup_page_and_create_backup_manually(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = $this->mock(LocalBackupManager::class);
        $manager->shouldReceive('backups')->once()->andReturn([]);
        $manager->shouldReceive('create')->once()->andReturn([
            'filename' => 'attendly-backup-20261106-150000-a1b2c3d4.enc',
            'size' => 1024,
            'created_at' => now(),
            'files_included' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('Buat backup sekarang')
            ->assertSee('Penyimpanan lokal sementara')
            ->assertSee('AES-256-GCM')
            ->assertSee('attendly:backup-restore');

        $this->actingAs($admin)
            ->post(route('admin.backups.store'))
            ->assertRedirect(route('admin.backups.index'))
            ->assertSessionHas('success');
    }

    public function test_non_admin_users_cannot_access_backup_page_or_download(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'hr']))
            ->get(route('admin.backups.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'employee']))
            ->post(route('admin.backups.store'))
            ->assertForbidden();
    }

    public function test_backup_creation_errors_are_shown_to_the_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = $this->mock(LocalBackupManager::class);
        $manager->shouldReceive('create')
            ->once()
            ->andThrow(new RuntimeException('MySQL backup binary is unavailable.'));

        $this->actingAs($admin)
            ->post(route('admin.backups.store'))
            ->assertRedirect(route('admin.backups.index'))
            ->assertSessionHasErrors([
                'backup' => 'MySQL backup binary is unavailable.',
            ]);
    }

    public function test_restore_command_requires_explicit_confirmation(): void
    {
        $manager = $this->mock(LocalBackupManager::class);
        $manager->shouldNotReceive('restore');

        $this->artisan('attendly:backup-restore', [
            'filename' => 'attendly-backup-20261106-150000-a1b2c3d4.enc',
        ])
            ->expectsOutputToContain('Pemulihan akan menimpa')
            ->assertExitCode(1);
    }

    public function test_restore_command_passes_confirmed_backup_to_manager(): void
    {
        $filename = 'attendly-backup-20261106-150000-a1b2c3d4.enc';
        $manager = $this->mock(LocalBackupManager::class);
        $manager->shouldReceive('restore')->once()->with($filename);

        $this->artisan('attendly:backup-restore', [
            'filename' => $filename,
            '--confirm' => true,
        ])
            ->expectsOutputToContain('Database berhasil dipulihkan')
            ->assertExitCode(0);
    }

    public function test_backup_encryption_round_trips_large_files_and_detects_tampering(): void
    {
        $manager = new LocalBackupManager;
        $source = tempnam(sys_get_temp_dir(), 'attendly-plain-');
        $encrypted = sys_get_temp_dir().DIRECTORY_SEPARATOR.'attendly-encrypted-'.bin2hex(random_bytes(8));
        $decrypted = sys_get_temp_dir().DIRECTORY_SEPARATOR.'attendly-restored-'.bin2hex(random_bytes(8));
        $tampered = sys_get_temp_dir().DIRECTORY_SEPARATOR.'attendly-tampered-'.bin2hex(random_bytes(8));
        $contents = random_bytes(150000);
        file_put_contents($source, $contents);

        try {
            $encrypt = new \ReflectionMethod($manager, 'encryptFile');
            $encrypt->invoke($manager, $source, $encrypted);
            $ciphertext = file_get_contents($encrypted);
            $this->assertIsString($ciphertext);
            $this->assertStringStartsWith("ATTENDLY-BACKUP-V1\n", $ciphertext);
            $this->assertStringNotContainsString($contents, $ciphertext);

            $decrypt = new \ReflectionMethod($manager, 'decryptFile');
            $decrypt->invoke($manager, $encrypted, $decrypted);
            $this->assertSame($contents, file_get_contents($decrypted));

            $ciphertext[strlen($ciphertext) - 8] = chr(ord($ciphertext[strlen($ciphertext) - 8]) ^ 1);
            file_put_contents($tampered, $ciphertext);

            $invalidOutput = sys_get_temp_dir().DIRECTORY_SEPARATOR.'attendly-invalid-'.bin2hex(random_bytes(8));
            try {
                $decrypt->invoke($manager, $tampered, $invalidOutput);
                $this->fail('Tampered backup should not decrypt.');
            } catch (\Throwable $exception) {
                $failure = $exception instanceof \ReflectionException ? $exception : ($exception->getPrevious() ?? $exception);
                $this->assertInstanceOf(RuntimeException::class, $failure);
            } finally {
                if (is_file($invalidOutput)) {
                    unlink($invalidOutput);
                }
            }
        } finally {
            foreach ([$source, $encrypted, $decrypted, $tampered] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function test_backup_archive_contains_database_manifest_and_local_uploads(): void
    {
        $privateRoot = storage_path('app/private');
        $publicRoot = storage_path('app/public');
        File::ensureDirectoryExists($privateRoot);
        File::ensureDirectoryExists($publicRoot);
        $suffix = bin2hex(random_bytes(6));
        $privatePath = $privateRoot.DIRECTORY_SEPARATOR.'backup-test-'.$suffix.'.txt';
        $publicPath = $publicRoot.DIRECTORY_SEPARATOR.'backup-test-'.$suffix.'.txt';
        $databaseDump = tempnam(sys_get_temp_dir(), 'attendly-database-');
        $archivePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'attendly-archive-'.bin2hex(random_bytes(8)).'.zip';
        file_put_contents($privatePath, 'private upload test');
        file_put_contents($publicPath, 'public upload test');
        file_put_contents($databaseDump, 'CREATE TABLE backup_test (id INT);');

        try {
            $manager = new LocalBackupManager;
            $archive = new \ReflectionMethod($manager, 'archiveBackup');
            $archive->invoke($manager, $archivePath, $databaseDump);

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($archivePath) === true);
            $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame('CREATE TABLE backup_test (id INT);', $zip->getFromName('database.sql'));
            $this->assertSame('private upload test', $zip->getFromName('storage/private/'.basename($privatePath)));
            $this->assertSame('public upload test', $zip->getFromName('storage/public/'.basename($publicPath)));
            $this->assertContains('storage/private/'.basename($privatePath), $manifest['files']);
            $this->assertSame(hash_file('sha256', $databaseDump), $manifest['database_sha256']);
            $zip->close();
        } finally {
            foreach ([$privatePath, $publicPath, $databaseDump, $archivePath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function test_retention_removes_only_backups_older_than_seven_days(): void
    {
        Storage::fake('backups');
        config(['backups.retention_days' => 7]);
        $oldFilename = 'attendly-backup-20261001-020000-a1b2c3d4.enc';
        $recentFilename = 'attendly-backup-20261106-020000-a1b2c3d5.enc';
        Storage::disk('backups')->put($oldFilename, 'old');
        Storage::disk('backups')->put($recentFilename, 'recent');
        touch(Storage::disk('backups')->path($oldFilename), now()->subDays(8)->getTimestamp());
        touch(Storage::disk('backups')->path($recentFilename), now()->subDay()->getTimestamp());

        $deleted = (new LocalBackupManager)->pruneExpired();

        $this->assertSame(1, $deleted);
        Storage::disk('backups')->assertMissing($oldFilename);
        Storage::disk('backups')->assertExists($recentFilename);
    }

    public function test_backup_list_is_empty_before_the_first_backup_exists(): void
    {
        Storage::fake('backups');

        $this->assertSame([], (new LocalBackupManager)->backups());
    }
}
