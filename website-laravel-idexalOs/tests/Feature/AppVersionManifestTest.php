<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AppVersionManifestTest extends TestCase
{
    use RefreshDatabase;

    private ?string $tempPublic = null;

    protected function tearDown(): void
    {
        if ($this->tempPublic !== null) {
            File::deleteDirectory($this->tempPublic);
        }
        parent::tearDown();
    }

    public function test_app_version_comes_from_the_published_release_manifest(): void
    {
        $this->useReleaseManifest(['2.1.0', '2.2.1', '2.2.0']);

        $this->getJson('/v1/app-version')
            ->assertOk()
            ->assertJson([
                'latestAppVersion' => '2.2.1',
                'publishedDesktopVersions' => ['2.2.1', '2.2.0', '2.1.0'],
            ]);
    }

    public function test_an_operator_override_newer_than_the_manifest_still_wins(): void
    {
        $this->useReleaseManifest(['2.2.1']);
        Setting::put('desktop_latest_version', '2.2.2', false, 'general');

        $this->getJson('/v1/app-version')
            ->assertOk()
            ->assertJsonPath('latestAppVersion', '2.2.2');
    }

    public function test_missing_manifest_falls_back_to_the_setting(): void
    {
        Setting::put('desktop_latest_version', '2.1.5', false, 'general');

        $this->getJson('/v1/app-version')
            ->assertOk()
            ->assertJsonPath('latestAppVersion', '2.1.5')
            ->assertJsonPath('publishedDesktopVersions', ['2.1.5']);
    }

    /**
     * @param  list<string>  $versions
     */
    private function useReleaseManifest(array $versions): void
    {
        $this->tempPublic = sys_get_temp_dir().'/idexal-public-'.uniqid('', true);
        File::ensureDirectoryExists($this->tempPublic.'/desktop');
        File::put(
            $this->tempPublic.'/desktop/releases.json',
            (string) json_encode([
                'latest' => $versions[0],
                'releases' => array_map(
                    static fn (string $version): array => ['version' => $version, 'platform' => 'windows', 'arch' => 'x64'],
                    $versions,
                ),
            ]),
        );
        $this->app->usePublicPath($this->tempPublic);
    }
}
