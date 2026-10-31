@php
    $siteUrl = locale_url(null);
    $publisherId = $siteUrl.'#organization';

    $organization = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $publisherId,
        'name' => config('idexal.brand.name'),
        'legalName' => config('idexal.brand.legal_name'),
        'url' => $siteUrl,
        'logo' => asset('brand/logo-light.png'),
        'email' => config('idexal.brand.support_email'),
        'sameAs' => [config('idexal.brand.github')],
    ];

    $website = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => config('idexal.brand.name'),
        'alternateName' => config('idexal.brand.tagline'),
        'url' => $siteUrl,
        'inLanguage' => str_replace('_', '-', app()->getLocale()),
        'publisher' => ['@id' => $publisherId],
    ];
@endphp
<script type="application/ld+json">{!! json_ld($organization) !!}</script>
<script type="application/ld+json">{!! json_ld($website) !!}</script>
@stack('schema')
