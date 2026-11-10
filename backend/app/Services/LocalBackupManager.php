<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class LocalBackupManager
{
    private const FORMAT = "ATTENDLY-BACKUP-V1\n";

    private const CHUNK_SIZE = 65536;

    public function create(): array
    {
        $this->encryptionKey();
        $disk = $this->disk();
        $disk->makeDirectory('');

        $workingDirectory = storage_path('framework/cache/attendly-backups/'.bin2hex(random_bytes(12)));
        File::ensureDirectoryExists($workingDirectory, 0700, true);

        $databaseDump = $workingDirectory.DIRECTORY_SEPARATOR.'database.sql';
        $archivePath = $workingDirectory.DIRECTORY_SEPARATOR.'backup.zip';
        $encryptedPath = $workingDirectory.DIRECTORY_SEPARATOR.'backup.enc';
        $databaseConfig = $workingDirectory.DIRECTORY_SEPARATOR.'mysql.cnf';

        try {
            $this->dumpDatabase($databaseDump, $databaseConfig);
            $fileManifest = $this->archiveBackup($archivePath, $databaseDump);
            $filename = 'attendly-backup-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.enc';
            $destination = $disk->path($filename);

            $this->encryptFile($archivePath, $encryptedPath);

            if (! rename($encryptedPath, $destination)) {
                throw new RuntimeException('Unable to publish the completed backup file.');
            }

            $this->pruneExpired();

            return [
                'filename' => $filename,
                'size' => filesize($destination),
                'created_at' => now(),
                'files_included' => count($fileManifest),
            ];
        } finally {
            $this->removeWorkingDirectory($workingDirectory);
        }
    }

    /**
     * @return list<array{filename: string, size: int, modified_at: int}>
     */
    public function backups(): array
    {
        $disk = $this->disk();
        $files = [];

        if (! is_dir($disk->path(''))) {
            return [];
        }

        foreach ($disk->files() as $path) {
            $filename = basename($path);

            if (! preg_match('/^attendly-backup-\d{8}-\d{6}-[a-f0-9]{8}\.enc$/', $filename)) {
                continue;
            }

            $files[] = [
                'filename' => $filename,
                'size' => $disk->size($path),
                'modified_at' => $disk->lastModified($path),
            ];
        }

        usort($files, fn (array $left, array $right): int => $right['modified_at'] <=> $left['modified_at']);

        return $files;
    }

    public function backupPath(string $filename): string
    {
        abort_unless(
            preg_match('/^attendly-backup-\d{8}-\d{6}-[a-f0-9]{8}\.enc$/', $filename) === 1,
            404,
        );

        $path = $this->disk()->path($filename);
        abort_unless(is_file($path), 404);

        return $path;
    }

    public function pruneExpired(): int
    {
        $cutoff = now()->subDays((int) config('backups.retention_days', 7))->getTimestamp();
        $disk = $this->disk();
        $deleted = 0;

        foreach ($this->backups() as $backup) {
            if ($backup['modified_at'] >= $cutoff) {
                continue;
            }

            if (! $disk->delete($backup['filename'])) {
                throw new RuntimeException('Unable to delete an expired local backup.');
            }

            $deleted++;
        }

        return $deleted;
    }

    public function restore(string $filename): void
    {
        if (preg_match('/^attendly-backup-\d{8}-\d{6}-[a-f0-9]{8}\.enc$/', $filename) !== 1) {
            throw new RuntimeException('The selected backup filename is invalid.');
        }

        $encryptedPath = $this->backupPath($filename);
        $workingDirectory = storage_path('framework/cache/attendly-restore/'.bin2hex(random_bytes(12)));
        File::ensureDirectoryExists($workingDirectory, 0700, true);

        $archivePath = $workingDirectory.DIRECTORY_SEPARATOR.'backup.zip';
        $databaseConfig = $workingDirectory.DIRECTORY_SEPARATOR.'mysql.cnf';
        $extractDirectory = $workingDirectory.DIRECTORY_SEPARATOR.'extracted';
        File::ensureDirectoryExists($extractDirectory, 0700, true);

        try {
            $this->decryptFile($encryptedPath, $archivePath);
            $zip = new ZipArchive;
            $result = $zip->open($archivePath);

            if ($result !== true) {
                throw new RuntimeException('The encrypted backup does not contain a valid archive.');
            }

            try {
                $manifestContents = $zip->getFromName('manifest.json');
                $manifest = is_string($manifestContents) ? json_decode($manifestContents, true) : null;

                if (
                    ! is_array($manifest)
                    || ($manifest['format'] ?? null) !== 1
                    || ! isset($manifest['database_sha256'])
                ) {
                    throw new RuntimeException('The backup manifest is invalid or incomplete.');
                }

                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $entry = $zip->getNameIndex($index);

                    if (
                        ! is_string($entry)
                        || str_starts_with($entry, '/')
                        || str_contains($entry, '../')
                        || str_contains($entry, '..\\')
                        || str_contains($entry, ':')
                    ) {
                        throw new RuntimeException('The backup archive contains an unsafe file path.');
                    }
                }

                if (! $zip->extractTo($extractDirectory)) {
                    throw new RuntimeException('Unable to extract the verified backup archive.');
                }
            } finally {
                $zip->close();
            }

            $databaseDump = $extractDirectory.DIRECTORY_SEPARATOR.'database.sql';

            $databaseHash = is_file($databaseDump) ? hash_file('sha256', $databaseDump) : false;

            if (
                ! is_string($databaseHash)
                || ! is_string($manifest['database_sha256'])
                || ! hash_equals($manifest['database_sha256'], $databaseHash)
            ) {
                throw new RuntimeException('The database backup failed its integrity check.');
            }

            $this->restoreDatabase($databaseDump, $databaseConfig);
            $this->restoreStorage($extractDirectory);
        } finally {
            $this->removeWorkingDirectory($workingDirectory);
        }
    }

    private function archiveBackup(string $archivePath, string $databaseDump): array
    {
        $zip = new ZipArchive;
        $result = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new RuntimeException('Unable to create the backup archive.');
        }

        $includedFiles = [];

        try {
            if (! $zip->addFile($databaseDump, 'database.sql')) {
                throw new RuntimeException('Unable to add the database dump to the backup.');
            }

            foreach (['private' => storage_path('app/private'), 'public' => storage_path('app/public')] as $name => $root) {
                if (! is_dir($root)) {
                    continue;
                }

                if (! $zip->addEmptyDir('storage/'.$name)) {
                    throw new RuntimeException('Unable to add a storage directory to the backup.');
                }
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY,
                );

                foreach ($iterator as $file) {
                    if (! $file->isFile() || $file->isLink()) {
                        continue;
                    }

                    $relativePath = substr($file->getPathname(), strlen($root) + 1);
                    $archiveName = 'storage/'.$name.'/'.str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

                    if (! $zip->addFile($file->getPathname(), $archiveName)) {
                        throw new RuntimeException('Unable to add an application file to the backup.');
                    }

                    $includedFiles[] = $archiveName;
                }
            }

            $manifest = [
                'format' => 1,
                'created_at' => now()->toIso8601String(),
                'database_driver' => config('database.default'),
                'database_sha256' => hash_file('sha256', $databaseDump),
                'files' => $includedFiles,
            ];

            if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))) {
                throw new RuntimeException('Unable to add backup metadata to the archive.');
            }
        } finally {
            if (! $zip->close()) {
                throw new RuntimeException('Unable to finalize the backup archive.');
            }
        }

        return $includedFiles;
    }

    private function dumpDatabase(string $dumpPath, string $databaseConfig): void
    {
        $database = config('database.connections.'.config('database.default'));

        if (! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Local backups currently require a MySQL or MariaDB database.');
        }

        $this->writeMysqlConfig($databaseConfig, $database);
        $process = new Process([
            (string) config('backups.mysql_dump_binary'),
            '--defaults-extra-file='.$databaseConfig,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--quick',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '--result-file='.$dumpPath,
            (string) $database['database'],
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($dumpPath) || filesize($dumpPath) === 0) {
            throw new RuntimeException('Database backup failed. Check the MySQL backup binary and database permissions.');
        }
    }

    private function restoreDatabase(string $dumpPath, string $databaseConfig): void
    {
        $database = config('database.connections.'.config('database.default'));

        if (! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Restore currently requires a MySQL or MariaDB database.');
        }

        $this->writeMysqlConfig($databaseConfig, $database);
        $process = new Process([
            (string) config('backups.mysql_binary'),
            '--defaults-extra-file='.$databaseConfig,
            '--default-character-set=utf8mb4',
            (string) $database['database'],
        ]);
        $process->setTimeout(600);
        $input = fopen($dumpPath, 'rb');

        if ($input === false) {
            throw new RuntimeException('Unable to open the database dump for restoration.');
        }

        try {
            $process->setInput($input);
            $process->run();
        } finally {
            fclose($input);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Database restore failed. Check MySQL permissions and the application log.');
        }
    }

    /**
     * @param  array<string, mixed>  $database
     */
    private function writeMysqlConfig(string $path, array $database): void
    {
        $contents = "[client]\n";

        foreach ([
            'user' => $database['username'] ?? '',
            'password' => $database['password'] ?? '',
            'host' => $database['host'] ?? '127.0.0.1',
            'port' => (string) ($database['port'] ?? 3306),
        ] as $key => $value) {
            $value = str_replace(
                ['\\', '"', "\n", "\r"],
                ['\\\\', '\\"', '\\n', '\\r'],
                (string) $value,
            );
            $contents .= $key.'="'.$value."\"\n";
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to create a temporary MySQL client configuration.');
        }

        if (PHP_OS_FAMILY !== 'Windows' && ! chmod($path, 0600)) {
            throw new RuntimeException('Unable to secure the temporary MySQL client configuration.');
        }
    }

    private function restoreStorage(string $extractDirectory): void
    {
        foreach (['private', 'public'] as $name) {
            $source = $extractDirectory.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.$name;

            if (! is_dir($source)) {
                continue;
            }

            $destination = storage_path('app/'.$name);

            if (! File::copyDirectory($source, $destination)) {
                throw new RuntimeException('Database restored, but an uploaded file could not be restored.');
            }
        }
    }

    private function encryptFile(string $sourcePath, string $destinationPath): void
    {
        $source = fopen($sourcePath, 'rb');
        $destination = fopen($destinationPath, 'xb');

        if ($source === false || $destination === false) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($destination)) {
                fclose($destination);
            }

            throw new RuntimeException('Unable to open the temporary backup encryption files.');
        }

        try {
            $this->writeAll($destination, self::FORMAT);
            $key = $this->encryptionKey();
            $index = 0;

            while (! feof($source)) {
                $plaintext = fread($source, self::CHUNK_SIZE);

                if ($plaintext === false) {
                    throw new RuntimeException('Unable to read the backup archive.');
                }

                if ($plaintext === '') {
                    if (! feof($source)) {
                        throw new RuntimeException('Unable to continue reading the backup archive.');
                    }

                    continue;
                }

                $nonce = random_bytes(12);
                $tag = '';
                $aad = self::FORMAT.pack('N', $index);
                $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad, 16);

                if (! is_string($ciphertext)) {
                    throw new RuntimeException('Unable to encrypt the backup archive.');
                }

                $this->writeAll($destination, pack('N', strlen($ciphertext)).$nonce.$tag.$ciphertext);
                $index++;
            }

            $this->writeAll($destination, pack('N', 0));
        } finally {
            fclose($source);
            fclose($destination);
        }
    }

    private function decryptFile(string $sourcePath, string $destinationPath): void
    {
        $source = fopen($sourcePath, 'rb');
        $destination = fopen($destinationPath, 'xb');

        if ($source === false || $destination === false) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($destination)) {
                fclose($destination);
            }

            throw new RuntimeException('Unable to open the backup for verification.');
        }

        try {
            if ($this->readExact($source, strlen(self::FORMAT)) !== self::FORMAT) {
                throw new RuntimeException('The selected file is not a supported Attendly backup.');
            }

            $key = $this->encryptionKey();
            $index = 0;

            while (true) {
                $lengthBytes = $this->readExact($source, 4);
                $length = unpack('Nlength', $lengthBytes)['length'];

                if ($length === 0) {
                    if (fread($source, 1) !== '') {
                        throw new RuntimeException('The encrypted backup contains unexpected trailing data.');
                    }

                    break;
                }

                if ($length > self::CHUNK_SIZE) {
                    throw new RuntimeException('The encrypted backup contains an invalid chunk.');
                }

                $nonce = $this->readExact($source, 12);
                $tag = $this->readExact($source, 16);
                $ciphertext = $this->readExact($source, $length);
                $aad = self::FORMAT.pack('N', $index);
                $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad);

                if (! is_string($plaintext)) {
                    throw new RuntimeException('Backup verification failed. The file or encryption key may be invalid.');
                }

                $this->writeAll($destination, $plaintext);
                $index++;
            }

            if ($index === 0) {
                throw new RuntimeException('The encrypted backup is empty.');
            }
        } finally {
            fclose($source);
            fclose($destination);
        }
    }

    private function encryptionKey(): string
    {
        $configuredKey = (string) config('app.key');

        if ($configuredKey === '') {
            throw new RuntimeException('APP_KEY must be configured before creating or restoring backups.');
        }

        if (str_starts_with($configuredKey, 'base64:')) {
            $decoded = base64_decode(substr($configuredKey, 7), true);

            if ($decoded === false) {
                throw new RuntimeException('APP_KEY has an invalid base64 format.');
            }

            $configuredKey = $decoded;
        }

        return hash_hmac('sha256', 'attendly-backup-encryption-v1', $configuredKey, true);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk('backups');
    }

    private function removeWorkingDirectory(string $path): void
    {
        File::deleteDirectory($path);

        if (is_dir($path)) {
            throw new RuntimeException('Unable to remove temporary backup files. Check the application storage permissions.');
        }
    }

    /**
     * @param  resource  $stream
     */
    private function writeAll($stream, string $contents): void
    {
        $offset = 0;
        $length = strlen($contents);

        while ($offset < $length) {
            $written = fwrite($stream, substr($contents, $offset));

            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write the backup file.');
            }

            $offset += $written;
        }
    }

    /**
     * @param  resource  $stream
     */
    private function readExact($stream, int $length): string
    {
        $contents = '';

        while (strlen($contents) < $length && ! feof($stream)) {
            $chunk = fread($stream, $length - strlen($contents));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $contents .= $chunk;
        }

        if (strlen($contents) !== $length) {
            throw new RuntimeException('The encrypted backup file is truncated.');
        }

        return $contents;
    }
}
