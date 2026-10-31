<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_cached_settings_store_attributes_and_reflect_updates(): void
    {
        Setting::put('proxy_enabled', false);

        $settings = app(SettingsService::class);
        $this->assertFalse($settings->boolean('proxy_enabled', true));

        $cached = Cache::get(Setting::cacheKey('proxy_enabled'));
        $this->assertIsArray($cached);

        Setting::put('proxy_enabled', true);

        $this->assertTrue($settings->boolean('proxy_enabled', false));
    }
}
