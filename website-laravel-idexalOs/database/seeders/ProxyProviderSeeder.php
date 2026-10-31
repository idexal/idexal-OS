<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\ProxyProvider;
use Illuminate\Database\Seeder;

class ProxyProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'slug' => 'webshare-static',
                'name' => 'Webshare Static Residential',
                'driver' => 'webshare',
                'protocol' => 'http',
                'website_url' => 'https://www.webshare.io/residential-proxy',
                'docs_url' => 'https://help.webshare.io/',
                'description' => 'Buy a static residential proxy from Webshare and paste the host, port, username, and password here.',
                'countries' => ['us', 'gb', 'de', 'fr', 'ca', 'au'],
                'priority' => 10,
                'plans' => ['free', 'starter', 'pro', 'business'],
            ],
            [
                'slug' => 'webshare-rotating',
                'name' => 'Webshare Rotating Residential',
                'driver' => 'webshare',
                'protocol' => 'http',
                'website_url' => 'https://www.webshare.io/residential-proxy',
                'docs_url' => 'https://help.webshare.io/',
                'description' => 'Rotating residential endpoints from Webshare. Use the endpoint and credentials from your Webshare dashboard.',
                'countries' => ['global'],
                'priority' => 20,
                'plans' => ['starter', 'pro', 'business'],
            ],
            [
                'slug' => 'oxylabs',
                'name' => 'Oxylabs Residential',
                'driver' => 'oxylabs',
                'protocol' => 'http',
                'website_url' => 'https://oxylabs.io/products/residential-proxy-pool',
                'docs_url' => 'https://developers.oxylabs.io/',
                'description' => 'Residential and static residential endpoints from Oxylabs.',
                'countries' => ['global'],
                'priority' => 30,
                'plans' => ['pro', 'business'],
            ],
            [
                'slug' => 'bright-data',
                'name' => 'Bright Data',
                'driver' => 'brightdata',
                'protocol' => 'http',
                'website_url' => 'https://brightdata.com/proxy-types/residential-proxies',
                'docs_url' => 'https://docs.brightdata.com/',
                'description' => 'Residential, ISP, and datacenter endpoints from Bright Data.',
                'countries' => ['global'],
                'priority' => 40,
                'plans' => ['pro', 'business'],
            ],
            [
                'slug' => 'smartproxy',
                'name' => 'Smartproxy',
                'driver' => 'smartproxy',
                'protocol' => 'http',
                'website_url' => 'https://smartproxy.com/proxies/residential-proxies',
                'docs_url' => 'https://help.smartproxy.com/',
                'description' => 'Residential and static endpoints from Smartproxy.',
                'countries' => ['global'],
                'priority' => 50,
                'plans' => ['starter', 'pro', 'business'],
            ],
        ];

        foreach ($providers as $definition) {
            $planSlugs = $definition['plans'];
            unset($definition['plans']);

            $provider = ProxyProvider::query()->firstOrCreate(
                ['slug' => $definition['slug']],
                $definition + [
                    'config' => [],
                    'is_active' => true,
                    'is_public' => true,
                ],
            );

            $planIds = Plan::query()->whereIn('slug', $planSlugs)->pluck('id');
            $provider->plans()->syncWithoutDetaching($planIds);
        }
    }
}
