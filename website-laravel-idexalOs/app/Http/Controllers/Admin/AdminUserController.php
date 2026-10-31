<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\DesktopSession;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The operator's full control over the people on the platform: list, filter,
 * inspect, create, edit, suspend, verify, reset, revoke and delete.
 *
 * Every destructive action is guarded. The two that matter are self-harm (an
 * operator may not suspend, demote or delete their own account, so a single
 * mistaken click cannot lock the deployment) and the last-admin case (the
 * platform must always keep one admin, so the operator who demotes or deletes
 * the final admin is refused). Deleting a user who still owns an organization
 * is refused too, because the organization's `owner_id` would dangle.
 */
class AdminUserController extends Controller
{
    /**
     * @return array<string, string>
     */
    private function roleLabels(): array
    {
        return [
            User::ROLE_USER => __('ui.roles.user'),
            User::ROLE_DEVELOPER => __('ui.roles.developer'),
            User::ROLE_ADMIN => __('ui.roles.admin'),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $users = $this->query($request)
            ->orderBy(...$this->order($filters['sort']))
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => $this->roleLabels(),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'stats' => $this->stats(),
            ...$filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => $this->roleLabels(),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'orgRoles' => $this->orgRoleLabels(),
            'locales' => $this->locales(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique(User::class, 'email')],
            'role' => ['required', Rule::in([User::ROLE_USER, User::ROLE_DEVELOPER, User::ROLE_ADMIN])],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED])],
            'locale' => ['nullable', Rule::in($this->locales())],
            'timezone' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:8', 'max:190'],
            'organization_id' => ['nullable', 'string', Rule::exists(Organization::class, 'id')],
            'org_role' => ['nullable', Rule::in(array_keys($this->orgRoleLabels()))],
            'email_verified' => ['nullable', 'boolean'],
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'role' => $validated['role'],
            'status' => $validated['status'],
            'locale' => $validated['locale'] ?? config('idexal.locales.default', 'en'),
            // `users.timezone` is NOT NULL with a default only at the column
            // level, so an explicit null would violate it; UTC is the honest
            // fallback for an account created without one.
            'timezone' => $validated['timezone'] ?? 'UTC',
            'phone' => $validated['phone'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            // An operator-created account with no password cannot sign in until
            // it completes a reset, which is the invite flow.
            'password' => $validated['password'] ?? str()->random(40),
            'email_verified_at' => $request->boolean('email_verified') ? now() : null,
        ]);
        $user->save();

        if (filled($validated['organization_id'] ?? null)) {
            $this->attachMembership(
                $user,
                (string) $validated['organization_id'],
                (string) ($validated['org_role'] ?? Membership::ROLE_MEMBER),
            );
        }

        AuditLog::record('admin.user.created', null, $user, $user->email, [
            'role' => $user->role,
            'status' => $user->status,
        ]);

        if (! $request->boolean('email_verified')) {
            $this->sendPasswordResetLink($user);
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', __('ui.admin.users.created'));
    }

    public function show(Request $request, User $user): View
    {
        $user->load([
            'memberships' => fn ($query) => $query->with('organization')->orderBy('created_at'),
            'ownedOrganizations',
            'socialAccounts',
        ]);

        $keys = ApiKey::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $sessions = DesktopSession::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_seen_at')
            ->limit(50)
            ->get();

        $audit = AuditLog::query()
            ->where('user_id', $user->getKey())
            ->orWhere(fn (Builder $query) => $query
                ->where('subject_type', User::class)
                ->where('subject_id', $user->getKey()))
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        return view('admin.users.show', [
            'user' => $user,
            'roles' => $this->roleLabels(),
            'orgRoles' => $this->orgRoleLabels(),
            'locales' => $this->locales(),
            'apiKeys' => $keys,
            'desktopSessions' => $sessions,
            'auditEntries' => $audit,
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'adminCount' => $this->adminCount(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique(User::class, 'email')->ignore($user->getKey())],
            'role' => ['required', Rule::in([User::ROLE_USER, User::ROLE_DEVELOPER, User::ROLE_ADMIN])],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED])],
            'locale' => ['nullable', Rule::in($this->locales())],
            'timezone' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
        ]);

        $actor = $request->user();
        $isSelf = $actor instanceof User && $actor->getKey() === $user->getKey();

        if ($isSelf && $validated['status'] !== User::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['status' => __('ui.errors.not_allowed')]);
        }

        if ($isSelf && $validated['role'] !== User::ROLE_ADMIN) {
            throw ValidationException::withMessages(['role' => __('ui.errors.not_allowed')]);
        }

        $this->guardLastAdmin($user, $validated['role'], $validated['status']);

        $emailChanged = strtolower((string) $user->email) !== strtolower($validated['email']);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'role' => $validated['role'],
            'status' => $validated['status'],
            'locale' => ($validated['locale'] ?? null) ?: ($user->locale ?: config('idexal.locales.default', 'en')),
            'timezone' => ($validated['timezone'] ?? null) ?: ($user->timezone ?: 'UTC'),
            'phone' => $validated['phone'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($validated['status'] === User::STATUS_SUSPENDED) {
            $this->revokeAccess($user);
        }

        AuditLog::record('admin.user.updated', null, $user, $user->email, [
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', __('ui.common.saved'));
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED])],
        ]);

        $this->guardSelf($request, $user, 'status');
        $this->guardLastAdmin($user, $user->role, $validated['status']);

        $user->forceFill(['status' => $validated['status']])->save();

        if ($validated['status'] === User::STATUS_SUSPENDED) {
            $this->revokeAccess($user);
        }

        AuditLog::record('admin.user.status', null, $user, $user->email, [
            'status' => $validated['status'],
        ]);

        return back()->with('status', __('ui.admin.users.status_updated'));
    }

    public function verifyEmail(Request $request, User $user): RedirectResponse
    {
        $user->forceFill(['email_verified_at' => now()])->save();

        AuditLog::record('admin.user.verified', null, $user, $user->email);

        return back()->with('status', __('ui.admin.users.verified_now'));
    }

    public function sendResetLink(Request $request, User $user): RedirectResponse
    {
        $sent = $this->sendPasswordResetLink($user);

        AuditLog::record('admin.user.reset_sent', null, $user, $user->email);

        return back()->with(
            $sent ? 'status' : 'error',
            $sent ? __('ui.admin.users.reset_sent') : __('ui.admin.users.reset_failed'),
        );
    }

    public function revokeApiKeys(Request $request, User $user): RedirectResponse
    {
        $count = $this->revokeApiKeysFor($user);

        AuditLog::record('admin.user.keys_revoked', null, $user, $user->email, ['count' => $count]);

        return back()->with('status', __('ui.admin.users.keys_revoked', ['count' => $count]));
    }

    public function revokeSessions(Request $request, User $user): RedirectResponse
    {
        $count = $this->revokeSessionsFor($user);

        AuditLog::record('admin.user.sessions_revoked', null, $user, $user->email, ['count' => $count]);

        return back()->with('status', __('ui.admin.users.sessions_revoked', ['count' => $count]));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'activate', 'delete', 'verify', 'reset'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string', Rule::exists(User::class, 'id')],
        ]);

        $actor = $request->user();
        $ids = array_values(array_unique(array_map('strval', $validated['ids'])));
        $action = (string) $validated['action'];

        $users = User::query()->whereIn('id', $ids)->get();

        $skipped = [];
        $changed = 0;

        foreach ($users as $user) {
            if (! $this->bulkAllowed($action, $actor, $user)) {
                $skipped[] = $user->email;

                continue;
            }

            match ($action) {
                'suspend' => $this->applyStatus($user, User::STATUS_SUSPENDED),
                'activate' => $this->applyStatus($user, User::STATUS_ACTIVE),
                'delete' => $this->applyDelete($user),
                'verify' => $user->forceFill(['email_verified_at' => now()])->save(),
                'reset' => $this->sendPasswordResetLink($user),
                default => null,
            };

            $changed++;
        }

        AuditLog::record('admin.user.bulk', null, null, $action, [
            'requested' => count($ids),
            'changed' => $changed,
            'skipped' => $skipped,
        ]);

        $message = __('ui.admin.users.bulk_done', ['count' => $changed]);

        return back()
            ->with($skipped === [] ? 'status' : 'error', $skipped === []
                ? $message
                : $message.' '.__('ui.admin.users.bulk_skipped', ['count' => count($skipped)]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->guardSelf($request, $user, 'delete');

        if ($user->role === User::ROLE_ADMIN && $this->adminCount() <= 1) {
            throw ValidationException::withMessages(['delete' => __('ui.admin.users.last_admin')]);
        }

        if ($user->ownedOrganizations()->exists()) {
            throw ValidationException::withMessages(['delete' => __('ui.admin.users.owns_organizations')]);
        }

        $this->revokeAccess($user);

        AuditLog::record('admin.user.deleted', null, $user, $user->email);
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('ui.admin.users.deleted'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->query($request)->orderBy(...$this->order($filters['sort']))->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'name', 'email', 'role', 'status', 'email_verified', 'locale', 'timezone',
                'organizations', 'api_keys', 'desktop_sessions', 'created_at',
            ]);

            foreach ($rows as $user) {
                fputcsv($out, [
                    $user->getKey(),
                    $user->name,
                    $user->email,
                    $user->role,
                    $user->status,
                    $user->email_verified_at !== null ? 'yes' : 'no',
                    $user->locale,
                    $user->timezone,
                    // `memberships_count` comes from `withCount`; the relation
                    // itself is not loaded, and strict mode forbids lazy loads.
                    $user->memberships_count ?? 0,
                    $user->api_keys_count ?? 0,
                    $user->desktop_sessions_count ?? 0,
                    $user->created_at?->toIso8601String(),
                ]);
            }

            fclose($out);
        }, 'users-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ---------------------------------------------------------------------
    // Query building
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $sort = (string) $request->query('sort', 'newest');
        $allowedSorts = ['newest', 'oldest', 'name', 'email', 'role'];

        return [
            'search' => trim((string) $request->query('q', '')),
            'role' => (string) $request->query('role', ''),
            'status' => (string) $request->query('status', ''),
            'verified' => (string) $request->query('verified', ''),
            'organization' => (string) $request->query('organization', ''),
            'activity' => (string) $request->query('activity', ''),
            'sort' => in_array($sort, $allowedSorts, true) ? $sort : 'newest',
        ];
    }

    private function query(Request $request): Builder
    {
        $f = $this->filters($request);

        return User::query()
            ->with(['organizations' => fn ($query) => $query->wherePivot('status', 'active')])
            ->withCount(['memberships', 'apiKeys', 'desktopSessions'])
            ->when($f['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', '%'.$f['search'].'%')
                ->orWhere('email', 'like', '%'.$f['search'].'%')
                ->orWhere('phone', 'like', '%'.$f['search'].'%')))
            ->when(in_array($f['role'], [User::ROLE_USER, User::ROLE_DEVELOPER, User::ROLE_ADMIN], true), fn (Builder $query) => $query->where('role', $f['role']))
            ->when(in_array($f['status'], [User::STATUS_ACTIVE, User::STATUS_SUSPENDED], true), fn (Builder $query) => $query->where('status', $f['status']))
            ->when($f['verified'] === 'yes', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->when($f['verified'] === 'no', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->when($f['organization'] !== '', fn (Builder $query) => $query->whereHas(
                'memberships',
                fn (Builder $inner) => $inner->where('organization_id', $f['organization'])
            ))
            ->when($f['activity'] === 'keys', fn (Builder $query) => $query->has('apiKeys'))
            ->when($f['activity'] === 'sessions', fn (Builder $query) => $query->has('desktopSessions'))
            ->when($f['activity'] === 'none', fn (Builder $query) => $query->doesntHave('apiKeys')->doesntHave('desktopSessions'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function order(string $sort): array
    {
        return match ($sort) {
            'oldest' => ['created_at', 'asc'],
            'name' => ['name', 'asc'],
            'email' => ['email', 'asc'],
            'role' => ['role', 'asc'],
            default => ['created_at', 'desc'],
        };
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        return [
            'total' => User::query()->count(),
            'active' => User::query()->where('status', User::STATUS_ACTIVE)->count(),
            'suspended' => User::query()->where('status', User::STATUS_SUSPENDED)->count(),
            'admins' => $this->adminCount(),
            'unverified' => User::query()->whereNull('email_verified_at')->count(),
            'new_this_month' => User::query()->where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    private function adminCount(): int
    {
        return User::query()->where('role', User::ROLE_ADMIN)->count();
    }

    // ---------------------------------------------------------------------
    // Actions
    // ---------------------------------------------------------------------

    private function applyStatus(User $user, string $status): void
    {
        $user->forceFill(['status' => $status])->save();

        if ($status === User::STATUS_SUSPENDED) {
            $this->revokeAccess($user);
        }
    }

    private function applyDelete(User $user): void
    {
        $this->revokeAccess($user);
        $user->delete();
    }

    private function revokeAccess(User $user): void
    {
        $this->revokeApiKeysFor($user);
        $this->revokeSessionsFor($user);
    }

    private function revokeApiKeysFor(User $user): int
    {
        return ApiKey::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function revokeSessionsFor(User $user): int
    {
        return DesktopSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function sendPasswordResetLink(User $user): bool
    {
        return Password::broker()->sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT;
    }

    private function attachMembership(User $user, string $organizationId, string $role): void
    {
        Membership::query()->updateOrCreate(
            ['organization_id' => $organizationId, 'user_id' => $user->getKey()],
            [
                'role' => $role,
                'status' => Membership::STATUS_ACTIVE,
                'joined_at' => now(),
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function orgRoleLabels(): array
    {
        return [
            Membership::ROLE_OWNER => __('ui.roles.owner'),
            Membership::ROLE_ADMIN => __('ui.roles.admin'),
            Membership::ROLE_DEVELOPER => __('ui.roles.developer'),
            Membership::ROLE_MEMBER => __('ui.roles.member'),
            Membership::ROLE_BILLING => __('ui.roles.billing'),
        ];
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        $supported = config('idexal.locales.supported', ['en']);

        return is_array($supported) ? array_values(array_map('strval', $supported)) : ['en'];
    }

    // ---------------------------------------------------------------------
    // Guards
    // ---------------------------------------------------------------------

    private function guardSelf(Request $request, User $user, string $field): void
    {
        if ($request->user() instanceof User && $request->user()->getKey() === $user->getKey()) {
            throw ValidationException::withMessages([$field => __('ui.admin.users.cannot_self')]);
        }
    }

    /**
     * The platform must always keep one admin: refuse to move the last admin
     * away from the admin role or off the active status.
     */
    private function guardLastAdmin(User $user, string $nextRole, string $nextStatus): void
    {
        if ($user->role !== User::ROLE_ADMIN) {
            return;
        }

        $demoting = $nextRole !== User::ROLE_ADMIN;
        $suspending = $nextStatus !== User::STATUS_ACTIVE;

        if (($demoting || $suspending) && $this->adminCount() <= 1) {
            throw ValidationException::withMessages([$demoting ? 'role' : 'status' => __('ui.admin.users.last_admin')]);
        }
    }

    private function bulkAllowed(string $action, mixed $actor, User $user): bool
    {
        $isSelf = $actor instanceof User && $actor->getKey() === $user->getKey();

        if (in_array($action, ['suspend', 'delete'], true) && $isSelf) {
            return false;
        }

        if (in_array($action, ['suspend', 'delete'], true) && $user->role === User::ROLE_ADMIN && $this->adminCount() <= 1) {
            return false;
        }

        if ($action === 'delete' && $user->ownedOrganizations()->exists()) {
            return false;
        }

        return true;
    }
}
