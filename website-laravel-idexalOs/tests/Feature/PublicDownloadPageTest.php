<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDownloadPageTest extends TestCase
{
    use RefreshDatabase;

    private ?string $manifestBackup = null;

    protected function tearDown(): void
    {
        $path = public_path('desktop/releases.json');
        if ($this->manifestBackup === null) {
            @unlink($path);
        } else {
            file_put_contents($path, $this->manifestBackup);
        }

        parent::tearDown();
    }

    public function test_the_download_page_lists_published_platform_builds(): void
    {
        $path = public_path('desktop/releases.json');
        if (is_file($path)) {
            $this->manifestBackup = (string) file_get_contents($path);
        }
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode([
            'latest' => '2.2.0',
            'publishedAt' => '2026-10-22T22:00:00Z',
            'releases' => [[
                'version' => '2.2.0',
                'platform' => 'windows',
                'arch' => 'x64',
                'file' => 'idexal-os-setup-x64-2.2.0.exe',
                'size' => 1024,
                'sha512' => str_repeat('a', 88),
                'notes' => 'Test release',
                'publishedAt' => '2026-10-22T22:00:00Z',
            ]],
        ], JSON_THROW_ON_ERROR));

        $response = $this->get('/download')->assertOk();

        $response->assertSee('v2.2.0');
        $response->assertSee('idexal-os-setup-x64-2.2.0.exe');
        $response->assertSee('Windows');
        $response->assertSee(__('ui.download.coming_soon'));

        // Release notes are the platform's release log: the manifest already
        // carried them, and dropping them left the download page without one.
        $response->assertSee('Test release');
        $response->assertSee('data-release-notes', false);
    }

    public function test_the_download_page_renders_without_a_manifest(): void
    {
        @unlink(public_path('desktop/releases.json'));

        $this->get('/download')
            ->assertOk()
            ->assertSee(__('ui.download.coming_soon'));
    }

    public function test_the_download_page_explains_the_first_launch_gate(): void
    {
        @unlink(public_path('desktop/releases.json'));

        $response = $this->get('/download')->assertOk();

        // A new install is never anonymous, and that has to be said before
        // someone downloads rather than discovered after.
        $response->assertSeeText(__('ui.download.first_launch_title'));
        $response->assertSeeText(__('ui.download.first_launch_body'));
        $response->assertSeeText(__('ui.download.install_three'));
    }
}
