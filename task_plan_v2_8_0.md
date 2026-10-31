# v2.8.0 — Mandatory sign-in + plan-gated desktop access

## Locked decisions
- Public installer `requireSignin` → `true` (hard-mandatory, no "use without an account").
- A signed-in user whose plan doesn't grant app access sees a "pick a plan" gate, not the app.
- Org-level BYOK kill switch + per-plan override.
- 2.8.0 is the gate; 2.9.0 (separate release) is the admin provider console.

## What already exists (do NOT reinvent)
- `ForcedSigninPage` + `DenSigninGate` + `DenSignInSurface` (fullscreen variant) — the sign-in UI.
- `den-signin-surface.tsx` "Create account" (sign-up) path.
- `Plan` + `features`/`limits` + `plan_ai_provider`/`plan_ai_model` pivots.
- `GatewayService::limitProvidersToPlan()` + `desktopVisibility()`.
- `ProxyEntitlementService` (template for the new block).
- `MeController::desktopConfig()` (delivery channel).
- `AdminProviderController` + `AdminPlanController` (admin surfaces).
- `desktop-distribution.mjs` `PUBLIC_DESKTOP_DISTRIBUTION.requireSignin` — the one line to flip.
- `main.mjs` `FORCE_DESKTOP_REQUIRE_SIGNIN` (reads `DESKTOP_DISTRIBUTION.requireSignin || env`).

## Plan (in order)
### 1. Distributino + bootstrap plumbing
- [ ] Flip `PUBLIC_DESKTOP_DISTRIBUTION.requireSignin: false → true` in `desktop-distribution.mjs`.
- [ ] Update any test/fixture that asserts the public flavor default.

### 2. Feature registry
- [ ] Add `accountRequiredForDesktopAccess` to `packages/features/src/registry.ts` (off by default, cloud+self_hosted, since 2026-10).
- [ ] Run `pnpm features:sync` (regenerates Helm + Den contract/SDK; needs disposable MySQL).

### 3. Platform entitlement model
- [ ] `Plan::FEATURE_DESKTOP_ACCESS = 'desktop_access'` (a plan that grants it lets the app open).
- [ ] `Organization::desktopAccessGranted(): bool` — true when the current plan has the feature, OR the flag is off (off = every plan grants, backward-compatible).
- [ ] `MeController::desktopConfig()` adds a `desktopAccess` block: `{ required, granted, planSlug }` (required is always true on the public flavor after the flip; granted is the entitlement).
- [ ] `desktopConfigSchema` in `packages/types/src/den/desktop-policies.ts` adds `desktopAccess: z.object({ required: z.boolean().optional(), granted: z.boolean().optional(), planSlug: z.string().optional() }).optional()`.
- [ ] `DesktopSwitch` case `AccountRequiredForDesktopAccess` (the kill-switch / operator toggle).

### 4. Desktop consumer
- [ ] `desktop-config-provider.tsx` adds `DESKTOP_CONFIG_ITEMS` entry + a `useDesktopAccessGrant()` hook.
- [ ] New gate component `PlanRequiredGate.tsx` in `domains/cloud/`: renders when `requireSignin && !granted`, shows "pick a plan" CTA, blocks the shell.
- [ ] `DenSigninGate` in `app-root.tsx`: when the user is signed in but `desktopAccess.granted === false`, render `PlanRequiredGate` instead of `children`.
- [ ] `i18n` keys (en + ar, with 6-form plurals where needed).

### 5. Tests
- [ ] `desktop-distribution.test.mjs`: public flavor now requires sign-in.
- [ ] `bun test` app gate test: `ForcedSigninPage` vs `PlanRequiredGate` render states.
- [ ] Laravel `PlatformDesktopAccessTest` (new): `/v1/me/desktop-config` returns the block; kill-switch off → `granted: true` for every plan; kill-switch on + plan without feature → `granted: false`.
- [ ] `pnpm typecheck` clean.

### 6. Bookkeeping
- [ ] Bump version to `2.8.0` everywhere (8 files, per the v2.7.0 pattern).
- [ ] `review.mjs` `LOCAL_RELEASE_VERSION` → `2.8.0`.
- [ ] Add v2.8.0 entry to `changelog/release-tracker-2026-10-28.md` on top.
- [ ] `php artisan test` green, `pnpm typecheck` green, `node scripts/i18n-audit.mjs --plurals` green.
- [ ] Build the installer (`pnpm build:electron` + `electron-builder`).

## Non-goals (2.8.0)
- Admin provider console → 2.9.0.
- Org-level BYOK kill switch → 2.9.0 (needs the admin UI).
- Provider hard-filter on the desktop → 2.9.0 (needs the new entitlement block).

## Open / risk
- Flipping `requireSignin` on the public flavor means every shipped public installer now forces sign-in. Self-hosted operators who build their own public-flavor installer with an older `desktop-bootstrap.json` will start seeing the gate — that's the point, but the deploy must be accompanied by a notice in the admin changelog.
- Two-stage semantics (locked): sign-in is unconditional on the public flavor (no flag, no escape hatch). The plan-level gate is behind `accountRequiredForDesktopAccess` (off by default). A deployment that has not enabled the flag forces sign-in but treats every plan as granting access. The operator can flip the flag on later without a deploy, adding the plan check on top.
