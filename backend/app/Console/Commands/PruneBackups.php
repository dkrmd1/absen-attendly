<?php

namespace App\Console\Commands;

use App\Services\LocalBackupManager;
use Illuminate\Console\Command;

class PruneBackups extends Command
{
    protected $signature = 'attendly:prune-backups';

    protected $description = 'Hapus backup lokal terenkripsi yang telah melewati masa retensi';

    public function handle(LocalBackupManager $backups): int
    {
        $deleted = $backups->pruneExpired();
        $this->info("Backup kedaluwarsa dihapus: {$deleted}.");

        return self::SUCCESS;
    }
}
