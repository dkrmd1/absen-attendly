<?php

namespace App\Console\Commands;

use App\Services\LocalBackupManager;
use Illuminate\Console\Command;
use RuntimeException;

class RestoreBackup extends Command
{
    protected $signature = 'attendly:backup-restore {filename : Nama file backup terenkripsi} {--confirm : Konfirmasi bahwa database aktif akan ditimpa}';

    protected $description = 'Pulihkan database dan file dari backup Attendly terenkripsi';

    public function handle(LocalBackupManager $backups): int
    {
        if (! $this->option('confirm')) {
            $this->error('Pemulihan akan menimpa data database. Jalankan ulang dengan --confirm setelah memastikan backup yang benar.');

            return self::FAILURE;
        }

        $this->warn('Database aktif akan ditimpa. Pastikan aplikasi sedang tidak menerima perubahan data.');

        try {
            $backups->restore((string) $this->argument('filename'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Database berhasil dipulihkan. File unggahan dari backup telah digabungkan ke penyimpanan lokal.');

        return self::SUCCESS;
    }
}
