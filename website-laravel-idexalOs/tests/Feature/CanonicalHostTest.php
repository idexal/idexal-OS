<?php

namespace Tests\Feature;

use App\Http\Middleware\PreferCanonicalHost;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class CanonicalHostTest extends TestCase
{
    public function test_the_official_host_is_canonical_and_the_api_host_is_additional(): void
    {
        $this->assertSame('os.idexal.com', config('idexal.domains.canonical'));
        $this->assertContains('api.idexal.com', config('idexal.domains.additional'));
        $this->assertSame(config('idexal.domains.canonical'), config('idexal.brand.domain'));
    }

    public function test_browser_pages_on_an_additional_host_redirect_to_the_canonical_host(): void
    {
        $this->get('https://api.idexal.com/pricing?ref=api')
            ->assertStatus(301)
            ->assertRedirect('https://os.idexal.com/pricing?ref=api');
    }

    public function test_requests_with_a_body_on_an_additional_host_use_a_temporary_redirect(): void
    {
        $this->post('https://api.idexal.com/login')
            ->assertStatus(302)
            ->assertRedirect('https://os.idexal.com/login');
    }

    public function test_machine_api_paths_stay_on_the_host_they_were_requested_from(): void
    {
        $this->getJson('https://api.idexal.com/api/runtime-config')->assertOk();
        $this->getJson('https://api.idexal.com/v1/health')->assertOk();
    }

    public function test_the_health_endpoint_reports_the_release_version(): void
    {
        $this->assertSame('3.14.1', config('idexal.version'));

        $this->getJson('https://api.idexal.com/v1/health')
            ->assertOk()
            ->assertJsonPath('version', '3.14.1');
    }

    /**
     * The version the API reports and the newest entry on /changelog are one
     * fact. They drifted before: the site claimed a release the history did
     * not contain, so this pins them together.
     */
    public function test_the_reported_version_is_the_newest_release_in_the_history(): void
    {
        $newest = (string) (config('releases.history')[0]['version'] ?? '');

        $this->assertSame($newest, config('idexal.version'));
    }

    public function test_the_canonical_host_is_never_redirected(): void
    {
        $this->assertNotSame(301, $this->statusFor('https://os.idexal.com/pricing'));
    }

    public function test_unknown_hosts_are_left_alone(): void
    {
        $this->assertNotSame(301, $this->statusFor('https://idexal.com/pricing'));
    }

    /**
     * Ask the middleware for its decision only, so these cases never reach a
     * controller that needs a database.
     */
    private function statusFor(string $url): int
    {
        $response = (new PreferCanonicalHost)->handle(
            Request::create($url),
            fn (): Response => response('ok'),
        );

        return $response->getStatusCode();
    }
}
