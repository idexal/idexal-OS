<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\SettingsService;
use App\Services\SocialLoginService;
use App\Support\DesktopSync;
use App\Support\SettingsChanges;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One page that switches social sign-in providers on and off and holds their
 * credentials. Secrets are write-only: a stored value is never sent back to the
 * browser, and leaving a secret field blank keeps the stored one.
 */
class AdminSocialLoginController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SocialLoginService $social,
    ) {}

    public function edit(): View
    {
        $providers = [];

        foreach ($this->social->providers() as $key => $provider) {
            $providers[$key] = $provider + [
                'enabled' => $this->social->booleanSetting($key),
                'client_id' => $this->social->clientId($key),
                'has_secret' => $this->social->clientSecret($key) !== '',
                'redirect_url' => $this->social->redirectUrl($key),
            ];
        }

        return view('admin.social-login', [
            'providers' => $providers,
            'liveProviders' => array_keys($this->social->enabledProviders()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];

        foreach (array_keys($this->social->providers()) as $key) {
            $rules['social_'.$key.'_enabled'] = ['nullable', 'boolean'];
            $rules['social_'.$key.'_client_id'] = ['nullable', 'string', 'max:255'];
            $rules['social_'.$key.'_secret'] = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);

        $values = [];
        $errors = [];

        foreach ($this->social->providers() as $key => $provider) {
            $enabled = $request->boolean('social_'.$key.'_enabled');
            $clientId = trim((string) ($validated['social_'.$key.'_client_id'] ?? ''));

            $values[$this->social->enabledKey($key)] = $enabled;
            $values[$this->social->clientIdKey($key)] = $clientId;

            if (filled($validated['social_'.$key.'_secret'] ?? null)) {
                $values[$this->social->secretKey($key)] = $validated['social_'.$key.'_secret'];
            }

            if ($request->boolean('clear_social_'.$key.'_secret')) {
                $values[$this->social->secretKey($key)] = null;
            }

            if ($enabled && ($clientId === '' || ! $this->hasSecret($key, $values))) {
                $errors['social_'.$key.'_client_id'] = __('ui.admin.social_login.incomplete', ['provider' => $provider['label']]);
            }
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        $secretKeys = [];

        foreach (array_keys($this->social->providers()) as $key) {
            $secretKeys[] = $this->social->secretKey($key);
        }

        $before = SettingsChanges::snapshot(array_keys($values));

        $this->settings->setMany($values, 'social');

        $changes = SettingsChanges::between($before, $values, $secretKeys);

        if ($changes === []) {
            return redirect()->route('admin.social-login')->with('status', __('ui.common.saved'));
        }

        AuditLog::record(
            'admin.social_login.updated',
            null,
            null,
            implode(', ', array_keys($this->social->enabledProviders())) ?: __('ui.admin.social_login.none_enabled'),
            ['settings' => $changes],
        );
        DesktopSync::announce('social_login_updated');

        return redirect()->route('admin.social-login')->with('status', __('ui.common.saved'));
    }

    /**
     * Whether a secret will exist after this save: the submitted value, or the
     * stored one when the field was left blank and not explicitly cleared.
     *
     * @param  array<string, mixed>  $values
     */
    private function hasSecret(string $key, array $values): bool
    {
        $secretKey = $this->social->secretKey($key);

        if (array_key_exists($secretKey, $values)) {
            return filled($values[$secretKey]);
        }

        return $this->social->clientSecret($key) !== '';
    }
}
