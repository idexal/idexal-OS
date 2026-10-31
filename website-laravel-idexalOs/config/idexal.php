<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform version
    |--------------------------------------------------------------------------
    |
    | The number reported by the machine API health endpoint. It mirrors the
    | shipped release version so the web platform and the desktop app never
    | disagree. Override per host with IDEXAL_VERSION.
    |
    */

    'version' => env('IDEXAL_VERSION', '3.14.1'),

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | The public site publishes English and Arabic and offers both to every
    | visitor anywhere in the world. English is the anonymous default so a
    | first-time visitor is never assumed to speak one region's language; a
    | person can switch, and the choice is remembered. This value must stay
    | independent of app.locale, which the request middleware rewrites to the
    | resolved language for the current request.
    |
    */

    'locales' => [
        'default' => env('IDEXAL_DEFAULT_LOCALE', 'en'),
        'supported' => ['en', 'ar'],
    ],

    'brand' => [
        'name' => 'idexal Os',
        'legal_name' => 'idexal Os',
        'tagline' => 'The AI workspace without limits',
        'domain' => env('IDEXAL_CANONICAL_HOST', 'os.idexal.com'),
        'support_email' => env('IDEXAL_SUPPORT_EMAIL', 'os@idexal.com'),
        'info_email' => env('IDEXAL_INFO_EMAIL', 'info@idexal.com'),
        'github' => 'https://github.com/idexal/idexalOs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hostnames
    |--------------------------------------------------------------------------
    |
    | The platform is self-contained and answers on more than one hostname,
    | but only the canonical host is official. Every generated link, redirect
    | and desktop handoff resolves there; additional hosts answer the machine
    | API so a client can be pointed at them without becoming the brand URL.
    |
    */

    'domains' => [
        'canonical' => env('IDEXAL_CANONICAL_HOST', 'os.idexal.com'),
        'additional' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('IDEXAL_ADDITIONAL_HOSTS', 'api.idexal.com')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Extension marketplace
    |--------------------------------------------------------------------------
    |
    | The bundled catalog ships inside the application and is the single source
    | of truth, so the marketplace is fully installable with no network calls.
    | Live rows from the public catalog repository are an opt-in enrichment.
    |
    */

    'marketplace' => [
        'remote' => filter_var(env('IDEXAL_MARKETPLACE_REMOTE', false), FILTER_VALIDATE_BOOL),
        'repository' => env('IDEXAL_MARKETPLACE_REPOSITORY', 'idexal/extensions-catalog'),
        // Skills libraries read beside the catalog repo. Each is one directory
        // per skill, so the team's full library is installable without copying
        // it into the catalog repository.
        'skill_repositories' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('IDEXAL_MARKETPLACE_SKILL_REPOSITORIES', 'idexal/idexal-skills')),
        ))),
    ],

    'desktop' => [
        'scheme' => env('IDEXAL_DESKTOP_SCHEME', 'idexalos'),
        'handoff_ttl_minutes' => (int) env('IDEXAL_HANDOFF_TTL', 10),
        'token_days' => (int) env('IDEXAL_DESKTOP_TOKEN_DAYS', 365),
    ],

    'registration' => [
        'enabled' => filter_var(env('IDEXAL_ALLOW_REGISTRATION', true), FILTER_VALIDATE_BOOL),
        'require_email_verification' => filter_var(env('IDEXAL_REQUIRE_EMAIL_VERIFICATION', false), FILTER_VALIDATE_BOOL),
    ],

    'billing' => [
        'currency' => env('IDEXAL_CURRENCY', 'USD'),
        'invoice_prefix' => env('IDEXAL_INVOICE_PREFIX', 'IXO'),
        'tax_rate' => (float) env('IDEXAL_TAX_RATE', 0),
        'grace_days' => (int) env('IDEXAL_GRACE_DAYS', 3),
        'trial_days' => (int) env('IDEXAL_TRIAL_DAYS', 14),
    ],

    'gateway' => [
        'enabled' => filter_var(env('IDEXAL_GATEWAY_ENABLED', true), FILTER_VALIDATE_BOOL),
        'timeout' => (int) env('IDEXAL_GATEWAY_TIMEOUT', 180),
        'stream_timeout' => (int) env('IDEXAL_GATEWAY_STREAM_TIMEOUT', 600),
        'log_retention_days' => (int) env('IDEXAL_GATEWAY_LOG_RETENTION_DAYS', 90),
        'rate_limit_per_minute' => (int) env('IDEXAL_GATEWAY_RATE_LIMIT', 120),
        'max_body_kb' => (int) env('IDEXAL_GATEWAY_MAX_BODY_KB', 1024),
        // Above this many JSON Schema nodes, JSON Schema Auto-Fit strips the
        // annotation-only keywords when the operator enabled the feature.
        'json_schema_node_cap' => (int) env('IDEXAL_GATEWAY_JSON_SCHEMA_NODE_CAP', 2000),
        // Windows and some containers ship no usable OpenSSL store; set this
        // to a PEM bundle instead of disabling verification.
        'ca_bundle' => env('IDEXAL_CA_BUNDLE'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'admin' => [
        'name' => env('ADMIN_NAME', 'Platform Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@idexal.com'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
