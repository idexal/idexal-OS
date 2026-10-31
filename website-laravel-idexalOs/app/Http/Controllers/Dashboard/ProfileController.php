<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function edit(Request $request): View
    {
        return view('dashboard.profile', [
            'user' => $request->user(),
            'organization' => $this->context->organization(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique(User::class)->ignore($user->getKey())],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'locale' => ['required', Rule::in(['ar', 'en'])],
            'timezone' => ['required', 'timezone'],
        ]);

        $emailChanged = strtolower((string) $user->email) !== strtolower($validated['email']);

        $user->forceFill([
            ...$validated,
            'email' => strtolower($validated['email']),
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        AuditLog::record('profile.updated', $this->context->organization(), $user, $user->name);

        return redirect()->route('dashboard.profile')->with('status', __('ui.common.saved'));
    }

    public function password(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->forceFill(['password' => $validated['password']])->save();
        AuditLog::record('profile.password_updated', $this->context->organization(), $request->user());

        return redirect()->route('dashboard.profile')->with('status', __('ui.common.saved'));
    }
}
