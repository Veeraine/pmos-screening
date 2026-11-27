<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(14, 30, 5));

        $this->folder = storage_path('framework/testing/backups');
        File::deleteDirectory($this->folder);

        config()->set('backup.folder', $this->folder);
        config()->set('backup.keep_days', 30);
        config()->set('backup.mysqldump_path', 'mysqldump');
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'pmos',
            'username' => 'root',
            'password' => 'Secret-Pass-1',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    /**
     * Fake mysqldump: write the given SQL to the --result-file it was asked for.
     */
    private function fakeMysqldump(string $sql = "CREATE TABLE patients (id int);\n-- Dump completed on 2026-10-15 14:30:05\n", int $exitCode = 0): void
    {
        Process::fake(function (PendingProcess $process) use ($sql, $exitCode) {
            foreach ($process->command as $argument) {
                if (str_starts_with($argument, '--result-file=')) {
                    File::put(substr($argument, strlen('--result-file=')), $sql);
                }
            }

            return Process::result(output: '', errorOutput: $exitCode ? 'Access denied for user' : '', exitCode: $exitCode);
        });
    }

    public function test_it_saves_a_compressed_backup_of_the_database(): void
    {
        $this->fakeMysqldump();

        $this->artisan('pmos:backup')->assertSuccessful();

        $backup = "{$this->folder}/pmos-2026-10-15_143005.sql.gz";

        $this->assertFileExists($backup);
        $this->assertStringContainsString('CREATE TABLE patients', gzdecode(File::get($backup)));
        $this->assertFileDoesNotExist("{$this->folder}/pmos-2026-10-15_143005.sql");
    }

    public function test_the_password_is_never_put_on_the_command_line(): void
    {
        $this->fakeMysqldump();

        $this->artisan('pmos:backup')->assertSuccessful();

        Process::assertRan(function (PendingProcess $process, ProcessResult $result): bool {
            $commandLine = implode(' ', $process->command);

            return $process->command[0] === 'mysqldump'
                && str_starts_with($process->command[1], '--defaults-extra-file=')
                && str_contains($commandLine, '--single-transaction')
                && end($process->command) === 'pmos'
                && ! str_contains($commandLine, 'Secret-Pass-1');
        });
    }

    public function test_it_skips_if_a_backup_from_today_already_exists(): void
    {
        $this->fakeMysqldump();
        File::ensureDirectoryExists($this->folder);
        File::put("{$this->folder}/pmos-2026-10-15_020000.sql.gz", 'earlier backup');

        $this->artisan('pmos:backup')
            ->expectsOutput('A backup from today already exists. Use --force to make another.')
            ->assertSuccessful();

        Process::assertNothingRan();
    }

    public function test_force_makes_another_backup_on_the_same_day(): void
    {
        $this->fakeMysqldump();
        File::ensureDirectoryExists($this->folder);
        File::put("{$this->folder}/pmos-2026-10-15_020000.sql.gz", 'earlier backup');

        $this->artisan('pmos:backup --force')->assertSuccessful();

        $this->assertCount(2, File::glob("{$this->folder}/pmos-*.sql.gz"));
    }

    public function test_a_failed_mysqldump_fails_the_command_and_leaves_no_backup(): void
    {
        $this->fakeMysqldump(sql: '', exitCode: 2);

        $this->artisan('pmos:backup')
            ->expectsOutput('Backup failed: Access denied for user')
            ->assertFailed();

        $this->assertSame([], File::glob("{$this->folder}/pmos-*"));
    }

    public function test_an_incomplete_dump_is_not_kept_as_a_backup(): void
    {
        $this->fakeMysqldump(sql: "CREATE TABLE patients (id int);\n");

        $this->artisan('pmos:backup')
            ->expectsOutput('Backup failed: The dump file is incomplete.')
            ->assertFailed();

        $this->assertSame([], File::glob("{$this->folder}/pmos-*"));
    }

    public function test_backups_older_than_the_days_to_keep_are_deleted(): void
    {
        $this->fakeMysqldump();
        File::ensureDirectoryExists($this->folder);
        File::put("{$this->folder}/pmos-2026-09-14_020000.sql.gz", 'too old');
        File::put("{$this->folder}/pmos-2026-09-15_020000.sql.gz", 'exactly 30 days old');
        File::put("{$this->folder}/pmos-2026-10-14_020000.sql.gz", 'yesterday');

        $this->artisan('pmos:backup')->assertSuccessful();

        $this->assertSame(
            ['pmos-2026-09-15_020000.sql.gz', 'pmos-2026-10-14_020000.sql.gz', 'pmos-2026-10-15_143005.sql.gz'],
            array_map('basename', File::glob("{$this->folder}/pmos-*.sql.gz")),
        );
    }

    public function test_the_newest_backup_is_kept_even_if_it_is_old(): void
    {
        $this->fakeMysqldump(sql: '', exitCode: 2);
        File::ensureDirectoryExists($this->folder);
        File::put("{$this->folder}/pmos-2026-01-01_020000.sql.gz", 'very old but the only backup');

        $this->artisan('pmos:backup')->assertFailed();

        $this->assertFileExists("{$this->folder}/pmos-2026-01-01_020000.sql.gz");
    }

    public function test_only_mysql_databases_can_be_backed_up(): void
    {
        $this->fakeMysqldump();
        config()->set('database.default', 'sqlite');

        $this->artisan('pmos:backup')
            ->expectsOutput('Backup failed: Backups only support MySQL or MariaDB databases.')
            ->assertFailed();
    }

    public function test_the_backup_is_scheduled_to_be_checked_every_hour(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains($event->command, 'pmos:backup'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }
}
