<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Database dumps: the readiness page has to be able to say "no dump exists"
 * and "the newest dump is too old", and the retention window has to keep the
 * disk from filling. The dump command itself needs mysqldump, so these tests
 * cover the parts that do not: discovery, freshness and pruning.
 */
class BackupServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/idexal-backups-'.uniqid('', true);
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);

        parent::tearDown();
    }

    public function test_no_dump_is_reported_as_stale(): void
    {
        $service = new BackupService($this->directory);

        $this->assertNull($service->latest());
        $this->assertTrue($service->isStale());
    }

    public function test_the_newest_dump_is_found_with_its_size(): void
    {
        file_put_contents($this->directory.'/db-20260101-000000.sql.gz', 'old');
        file_put_contents($this->directory.'/db-20260102-000000.sql.gz', 'newer-dump');
        touch($this->directory.'/db-20260101-000000.sql.gz', now()->subDays(3)->timestamp);

        $latest = (new BackupService($this->directory))->latest();

        $this->assertNotNull($latest);
        $this->assertSame($this->directory.'/db-20260102-000000.sql.gz', $latest['path']);
        $this->assertSame(strlen('newer-dump'), $latest['size']);
        $this->assertFalse((new BackupService($this->directory))->isStale());
    }

    public function test_a_dump_older_than_the_window_is_stale(): void
    {
        $path = $this->directory.'/db-20260101-000000.sql.gz';
        file_put_contents($path, 'dump');
        touch($path, Carbon::now()->subHours(BackupService::STALE_HOURS + 2)->timestamp);

        $this->assertTrue((new BackupService($this->directory))->isStale());
    }

    public function test_pruning_keeps_the_newest_dumps(): void
    {
        foreach ([1, 2, 3, 4, 5] as $day) {
            $path = $this->directory."/db-2026010{$day}-000000.sql.gz";
            file_put_contents($path, 'dump');
            touch($path, now()->subDays(6 - $day)->timestamp);
        }

        $removed = (new BackupService($this->directory))->prune(2);

        $this->assertSame(3, $removed);
        $this->assertCount(2, glob($this->directory.'/db-*.sql.gz'));
        $this->assertFileExists($this->directory.'/db-20260105-000000.sql.gz');
    }
}
