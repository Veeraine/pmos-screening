<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Makes a daily .sql.gz backup of the database. Runs every hour but only backs up once a day,
 * so a day isn't missed if the computer is switched on late. Settings are in config/backup.php.
 */
#[Signature('pmos:backup {--force : Make a backup even if one was already made today}')]
#[Description('Back up the database (once per day unless --force is used)')]
class BackupDatabase extends Command
{
    /**
     * The last line mysqldump writes when a dump finishes properly.
     */
    private const COMPLETED_MARKER = '-- Dump completed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $folder = config('backup.folder');

        if (! $this->option('force') && $this->hasBackupFromToday($folder)) {
            $this->info('A backup from today already exists. Use --force to make another.');

            return self::SUCCESS;
        }

        try {
            $backupFile = $this->makeBackup($folder);
        } catch (Throwable $exception) {
            Log::error('Database backup failed.', ['error' => $exception->getMessage()]);
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $deleted = $this->deleteOldBackups($folder);

        Log::info('Database backup completed.', ['file' => $backupFile, 'old_backups_deleted' => $deleted]);
        $this->info("Backup saved: {$backupFile}");

        return self::SUCCESS;
    }

    /**
     * Dumps the database with mysqldump, checks it finished, then compresses it.
     */
    private function makeBackup(string $folder): string
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Backups only support MySQL or MariaDB databases.');
        }

        File::ensureDirectoryExists($folder);

        $timestamp = now()->format('Y-m-d_His');
        $sqlFile = "{$folder}/pmos-{$timestamp}.sql";
        $backupFile = "{$sqlFile}.gz";
        $credentialsFile = $this->writeCredentialsFile($connection);

        try {
            $result = Process::timeout(600)->run([
                config('backup.mysqldump_path'),
                "--defaults-extra-file={$credentialsFile}",
                '--single-transaction',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                "--result-file={$sqlFile}",
                $connection['database'],
            ]);

            if ($result->failed()) {
                throw new RuntimeException(trim($result->errorOutput()) ?: 'mysqldump exited with code '.$result->exitCode().'.');
            }

            if (! $this->dumpIsComplete($sqlFile)) {
                throw new RuntimeException('The dump file is incomplete.');
            }

            $this->compress($sqlFile, $backupFile);
        } catch (Throwable $exception) {
            File::delete([$sqlFile, $backupFile]);

            throw $exception;
        } finally {
            File::delete($credentialsFile);
        }

        File::delete($sqlFile);

        return $backupFile;
    }

    /**
     * Puts the DB login in a temp file for mysqldump so the password doesn't show in the process list.
     *
     * @param  array<string, mixed>  $connection
     */
    private function writeCredentialsFile(array $connection): string
    {
        $quote = fn (mixed $value): string => '"'.addcslashes((string) $value, '"\\').'"';

        $path = tempnam(sys_get_temp_dir(), 'pmos-backup-');

        File::put($path, implode(PHP_EOL, [
            '[client]',
            'user='.$quote($connection['username']),
            'password='.$quote($connection['password'] ?? ''),
            'host='.$quote($connection['host']),
            'port='.(int) $connection['port'],
            '',
        ]));

        return $path;
    }

    /**
     * Whether the dump ends with mysqldump's "Dump completed" line.
     */
    private function dumpIsComplete(string $sqlFile): bool
    {
        if (! File::exists($sqlFile) || File::size($sqlFile) === 0) {
            return false;
        }

        $handle = fopen($sqlFile, 'rb');
        fseek($handle, max(0, File::size($sqlFile) - 200));
        $ending = stream_get_contents($handle);
        fclose($handle);

        return str_contains($ending, self::COMPLETED_MARKER);
    }

    /**
     * Compresses the dump to .gz bit by bit, so big databases don't use much memory.
     */
    private function compress(string $sqlFile, string $backupFile): void
    {
        $input = fopen($sqlFile, 'rb');
        $output = gzopen($backupFile, 'wb6');

        while (! feof($input)) {
            gzwrite($output, fread($input, 1024 * 512));
        }

        fclose($input);
        gzclose($output);
    }

    /**
     * Checks if a backup was already made today.
     */
    private function hasBackupFromToday(string $folder): bool
    {
        return File::glob("{$folder}/pmos-".now()->format('Y-m-d').'_*.sql.gz') !== [];
    }

    /**
     * Deletes old backups but always keeps the newest one.
     */
    private function deleteOldBackups(string $folder): int
    {
        $backups = File::glob("{$folder}/pmos-*.sql.gz");
        sort($backups);
        array_pop($backups);

        $oldestDateToKeep = now()->subDays(config('backup.keep_days'))->format('Y-m-d');
        $deleted = 0;

        foreach ($backups as $backup) {
            if (preg_match('/pmos-(\d{4}-\d{2}-\d{2})_/', basename($backup), $matches) && $matches[1] < $oldestDateToKeep) {
                File::delete($backup);
                $deleted++;
            }
        }

        return $deleted;
    }
}
