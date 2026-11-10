<?php

namespace App\Console\Commands;

use App\Services\AttendanceNotificationService;
use Illuminate\Console\Command;

class NotifyMissingAttendance extends Command
{
    protected $signature = 'attendly:notify-missing-attendance';

    protected $description = 'Kirim ringkasan staff terjadwal yang belum check-in';

    public function handle(AttendanceNotificationService $notifications): int
    {
        $sent = $notifications->notifyMissingCheckIns();
        $this->info("Ringkasan ketidakhadiran dikirim ke {$sent} atasan.");

        return self::SUCCESS;
    }
}
