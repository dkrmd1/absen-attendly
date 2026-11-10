<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LocalBackupManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(LocalBackupManager $backups): View
    {
        $files = array_map(fn (array $backup): array => [
            ...$backup,
            'modified_at' => Carbon::createFromTimestamp($backup['modified_at']),
        ], $backups->backups());

        return view('admin.backups.index', ['backups' => $files]);
    }

    public function store(LocalBackupManager $backups): RedirectResponse
    {
        set_time_limit(0);

        try {
            $backup = $backups->create();
        } catch (RuntimeException $exception) {
            return redirect()->route('admin.backups.index')
                ->withErrors(['backup' => $exception->getMessage()]);
        }

        return redirect()->route('admin.backups.index')
            ->with('success', 'Backup terenkripsi berhasil dibuat: '.$backup['filename']);
    }

    public function download(string $filename, LocalBackupManager $backups): BinaryFileResponse
    {
        $path = $backups->backupPath($filename);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
