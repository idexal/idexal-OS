<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Database backups.
 *
 * The deployment backups copy the application files, not the rows: a restore
 * without the database rebuilds an empty platform. This service writes a
 * compressed dump, keeps a short retention window, and exposes the newest dump
 * so the readiness page can tell an operator when the last one is too old.
 */
class BackupService
{
    /** Dumps older than this many hours are reported as stale. */
    public const STALE_HOURS = 26;

    /** A test or a migration can point the service at another folder. */
    public function __construct(private readonly ?string $directoryOverride = null) {}

    public function directory(): string
    {
        return $this->directoryOverride ?? storage_path('app/backups');
    }

    /**
     * The newest dump, or null when none exists.
     *
     * @return array{path: string, size: int, createdAt: Carbon}|null
     */
    public function latest(): ?array
    {
        $files = $this->files();

        if ($files === []) {
            return null;
        }

        $path = $files[0];

        return [
            'path' => $path,
            'size' => (int) filesize($path),
            'createdAt' => Carbon::createFromTimestamp((int) filemtime($path)),
        ];
    }

    public function isStale(): bool
    {
        $latest = $this->latest();

        return $latest === null || $latest['createdAt']->diffInHours(now()) >= self::STALE_HOURS;
    }

    /**
     * Write a dump and prune the ones outside the retention window.
     *
     * @return array{path: string, size: int, pruned: int}
     */
    public function run(?int $keep = 14): array
    {
        $keep = max(1, $keep ?? 14);
        $directory = $this->directory();

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Cannot create the backup directory: '.$directory);
        }

        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}", []);

        if (($config['driver'] ?? '') !== 'mysql') {
            throw new \RuntimeException('Database backups support the mysql driver only.');
        }

        $dump = (string) config('backup.mysqldump', 'mysqldump');
        $path = $directory.'/db-'.now()->format('Ymd-His').'.sql.gz';

        // The password travels in the environment, never in the command line
        // where any user on the host could read it from the process list.
        $command = sprintf(
            '%s --host=%s --port=%s --user=%s --single-transaction --quick --routines --events %s | gzip -9 > %s',
            escapeshellarg($dump),
            escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($config['port'] ?? '3306')),
            escapeshellarg((string) ($config['username'] ?? '')),
            escapeshellarg((string) ($config['database'] ?? '')),
            escapeshellarg($path),
        );

        $env = ['MYSQL_PWD' => (string) ($config['password'] ?? '')];
        $process = Process::fromShellCommandline($command, base_path(), $env, null, 900);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($path) || filesize($path) === 0) {
            @unlink($path);

            throw new ProcessFailedException($process);
        }

        $pruned = $this->prune($keep);

        return ['path' => $path, 'size' => (int) filesize($path), 'pruned' => $pruned];
    }

    /**
     * Delete dumps beyond the retention window, newest first.
     */
    public function prune(int $keep): int
    {
        $files = $this->files();
        $removed = 0;

        foreach (array_slice($files, $keep) as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Dump files, newest first.
     *
     * @return list<string>
     */
    private function files(): array
    {
        $files = glob($this->directory().'/db-*.sql.gz') ?: [];

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return array_values($files);
    }
}
