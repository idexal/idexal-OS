# Release Changelog Tracker

Internal preparation file for release summaries. Not published until the deploy completes.

## v3.10.0

#### Commit

The working-tree changes below (installer wizard branding, licence, wizard copy, bundled docs), on top of the v3.9.0 commit and its tracker note. A release that fixes what a person sees *before* the app opens: the setup wizard had been left behind by the rebrand and still showed the previous logo and palette with a hardcoded "Version 1.0.0", and its welcome page still promised the app "can run without an account" — false since the mandatory gate arrived. The wizard art is now generated from the committed mark, the version can no longer go stale, and the copy matches the product. Windows and Linux are built from this release; macOS follows.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

The installer looks like the app again, and tells the truth about first launch.

#### One-line summary

The installer was the last surface still showing the old logo: the NSIS sidebar and header are bitmaps baked into their own files, so the rebrand never reached them, and they carried the retired teal palette plus a hardcoded "Version 1.0.0"; the welcome page still offered anonymous use. The art is regenerated from the committed mark by a script that ships with the repo, the version pin is removed, the copy is corrected, and Windows and Linux packages are built from this release.

#### Platform changes

- **Explicit model and provider assignment (new).** The platform's rule was that a model or provider with no plan row serves *every* plan, so an operator could not make an assignment mandatory. `POST /admin/providers/assignment` writes `gateway_require_explicit_assignment` (default off), and `GatewayService::limitModelsToPlan` / `limitProvidersToPlan` plus `ProviderEntitlementService::providersForPlan` all read it: with it on, nothing reaches a plan — models, providers, and the combos derived from those models — until the operator assigns it. The desktop block and the gateway read one switch, so they cannot disagree. The button confirms before flipping, because turning it on with nothing assigned empties every catalog by design.
- **Members' own keys are off by default (new).** `byok_enabled` now defaults to off, so a desktop offers the platform's published providers and plans only and has no own-keys surface until the operator turns it on. `MeController::customProvidersAllowed`, `ProviderEntitlementService::byokAllowed` and the provider console all read that one default, and the block the desktop reads now consults the switch at all — it previously did not, so the block could tell the desktop own-keys were allowed while the API said no.
- New `3_10_0` entry in `config/releases.php`, `lang/en/changelog.php` and `lang/ar/changelog.php`, so `/changelog` shows this release in both languages.

#### Release pipeline

- **Linux desktop packages are now produced at all.** `publish-public-release.yml` built the desktop app for macOS and Windows only, and for Linux shipped just the server tarball — so no Linux desktop build ever reached the release. A new `linux-desktop` job packages `AppImage`, `deb`, `rpm` and a portable `tar.gz` on `ubuntu-latest` and renames them to the public convention; a missing target fails the job rather than publishing an incomplete set.
- **Windows arm64.** A new `windows-arm64` job builds the arm64 NSIS installer, setting `TAURI_ENV_TARGET_TRIPLE=aarch64-pc-windows-msvc` so the arm64 engine is fetched rather than the x64 one being embedded. It is marked `continue-on-error` and commented as not-yet-exercised, so a first-run failure cannot hold back the x64, macOS and Linux assets; the marker should be removed once a tagged release has produced the installer.
- `publish` now waits on both new jobs, and the header comment lists the new asset names.

#### Desktop / app changes

- **The gate blocks instead of redirecting.** `DenSigninGate` returned the whole `Routes` tree and only navigated away in an effect, so a deep link or a restored history entry rendered a workspace screen with no session. `gateBlocksAppRoutes` (pure, tested) now makes it return only the sign-in surface, and the protected routes are never mounted. Verified live in the running app: `#/session`, `#/welcome`, `#/settings` and `#/session/abc` all land on the gate, with no sidebar and no composer.
- **Both ways in are visible on the gate.** The fullscreen surface had one action — a button labelled "Sign in" that silently opened the sign-up tab — so the subscription path was invisible. It now shows "Sign in" and "Create account" as two explicit actions, with a line saying a new account starts a subscription and chooses a plan.
- **The platform surface opens by itself.** The gate opens the embedded platform window on first mount (once per mount) instead of waiting for a click. Verified live: the window was closed, the gate reloaded with no click, and `os.idexal.com/login` reopened by itself.
- **The wizard art is regenerated.** `apps/desktop/build/installerSidebar.bmp` (164×314) and `installerHeader.bmp` (150×57) are rebuilt from `resources/icons/icon.png` and the brand tokens (ink-950 `#080a08`, lime `#7dff00`): the current rounded app icon, "idexal Os" in Segoe UI Semibold, the lime accent rule, and no version number.
- **A committed generator, so it cannot drift again.** `apps/desktop/scripts/generate-installer-branding.py` rebuilds both bitmaps from the repository's icon and mark; `pnpm --filter @idexal/desktop branding` runs it. The output is 32-bit BMP, byte-for-byte the same format the previous art used (both files come out at exactly their historic sizes, 206,038 B and 34,254 B).
- **The stale version is gone.** `build/license.txt` no longer says "Version 1.0.0"; the version is shown by the wizard title (`${VERSION}`) and the app's About screen.
- **The welcome copy matches the product.** `build/installer.nsh` no longer says the app "can run without an account; sign-in is only needed for optional cloud features"; it now says the app opens with an account — sign in, or start a subscription and choose a plan — and opens once either is done.
- **Bundled documentation corrected.** `packages/docs/desktop-app/why-sign-in.mdx` said "Signing in is optional" with a "Works signed out" table; it now states the published app requires an account on first launch, notes that a build from source does not carry the gate, and keeps the local-vs-cloud table as what the account selects. `packages/docs/cloud/get-started.mdx` no longer says the desktop app "requires no Cloud sign-in".
- **Version.** `3.10.0` in `apps/app`, `apps/desktop`, `apps/desktop/server`, `apps/server`, the free-release manifest, the sidecar manifest, `config/idexal.php`, `scripts/release/review.mjs` and `CanonicalHostTest`.
- **macOS is not built in this release** (owner decision: Windows and Linux first). Its art is unchanged, and `build/dmg-background.png` is untouched.

#### Verified before deploy

- **End-to-end against a running platform (local, real account, real plan).** The Laravel platform was started locally and a real account was created with a plan that grants desktop access and an active subscription. The whole desktop journey was then driven over HTTP with a real session, and every step answered:
  1. `GET /?desktopAuth=1&desktopEmbed=1` → the sign-in form (no app without an account).
  2. `POST /login` with the real credentials → signed in.
  3. → the page titled **"Authorize the desktop app"**.
  4. `POST /desktop/authorize` with the chosen organization → a handoff is issued.
  5. The handoff page carries `idexalos://den-auth?grant=…` **and** the automatic hand-back script (`window.location.href =`), which is what makes the embedded flow finish without a click.
  6. `POST /v1/auth/desktop-handoff/exchange` with that grant → **200**, returning `user`, `token`, `organization` and `connectEnabled` — a token of 49 characters and the workspace. The desktop's own exchange call therefore succeeds against a live server.
- This is the desktop↔platform link proven end to end at the platform boundary. What it cannot prove on its own is the packaged app receiving the deep link on a user's machine, which needs the platform deployed with this code.
- New `apps/desktop/test/installer-branding.test.mjs` (5 tests) is the regression guard: the sidebar and header are the MUI2 sizes and contain the brand lime; **no installer art contains the retired teal** (`#1b7662` / `#23d3a6`); `license.txt` pins no version; and the welcome text no longer claims the app can run without an account.
- The regenerated bitmaps were inspected as images before being used, not just size-checked.
- Full Laravel suite green at `534 tests / 3175 assertions`; Pint clean; `review.mjs` permits `3.10.0`; Electron bridge check passes; bundle `build:electron` rc=0.
- New `GatewayExplicitAssignmentTest` (6 tests) covers the assignment policy in both directions: by default an unassigned model is offered; requiring an assignment removes every unassigned model and the desktop block agrees; an assigned model reaches only the plan it is assigned to (both the provider and the model must be assigned in strict mode); a provider without an assignment is absent from `providerEntitlements`; the console toggle writes the setting and audits it; and a member cannot change the policy.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installers `3.10.0`:

- Windows x64: `idexal-os-setup-3.10.0-x64.exe`, **374,431,287 bytes**, sha256 `aaac0ddd039b1b3f678fa88a2bf235f58642dc65ea78fc9fe715d37e4be8e7c6` (blockmap 378,658 B). `build:electron rc=0`, `electron-builder rc=0`, signed with signtool, blockmap written. The first packaging attempt failed (`rc=-1`) after a leftover packaging process from an earlier run collided in the same output directory; the partial payload was removed and the packaging re-run alone succeeded.
- Verified inside the packaged app: `resources/app.asar` `package.json` reports `@idexal/desktop` `3.10.0`; the packaged renderer (`resources/app-dist/assets/app-DH2_ed_S.js`) contains the corrected gate copy, so the two visible ways in are in the shipped bundle; and the bundled `idexal-os-docs/desktop-app/why-sign-in.mdx` no longer contains the word "optional".
- The wizard art is regenerated and was on disk before packaging started, but it was **not** visually inspected inside the built exe: a byte scan of the 374 MB installer for the brand lime finds the pattern 15–17 times and the retired teal 27–29 times, which is what random 3-byte patterns do at that size, so the scan proves nothing either way. The evidence for the art is the generator, the guard test, and the file timestamps.
- Linux AppImage / deb / rpm / tar.gz: **(not built here)** — produced by the new `linux-desktop` job; this machine has no Linux host. See Known gaps.
- Windows arm64: **(not built here)** — produced by the new `windows-arm64` job.

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `3.10.0`.
2. ⬜ No new migration this release; rebuild the config, route and view caches.
3. ⬜ Run the Windows `3.10.0` setup and confirm the wizard shows the current logo (no teal), no "Version 1.0.0", and the account wording on the welcome page.
4. ⬜ Install a Linux package and confirm it launches and shows the same first-launch gate.
5. ⬜ Publish `3.10.0` to the release center and the public GitHub release; `/v1/app-version` lists `3.10.0` first.
6. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- **v3.9.0 has no installer of its own.** Its packaging run was started, then stopped once it was clear it would ship the retired wizard art; its gate work ships in `3.10.0` instead. Recorded here rather than left to guesswork.
- The generator needs Python with Pillow on the machine that regenerates the art. The committed outputs are what the build uses, so a build never depends on Python — only a re-brand does.
- **Linux and Windows arm64 packages are produced only by the release pipeline.** This machine has no Linux host (no Docker, no WSL) and the engine sidecar is fetched per target — the packaging script cannot prepare a Linux engine from Windows — so the Linux `AppImage`/`deb`/`rpm`/`tar.gz` and the arm64 installer exist as CI jobs, not as local artifacts. They were added to `publish-public-release.yml` in this release and have not been exercised by a tagged run yet.
- macOS packaging (dmg/zip, notarization) is deferred by the owner; its background art is unchanged and its mark already matches.
- The in-app sign-in window shows the platform's own pages; a provider that refuses to run in an app window is served by the existing browser fallback (unchanged from 3.9.0).
- `POST /v1/mcp/token` and `GET /v1/billing/web` still need a capability the platform does not advertise.
- The Den API contract/SDK step still needs MySQL 8.4 (this machine runs MariaDB 10.4), so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration before the platform deploy — the same note as v2.9.0–v3.9.0.

## v3.9.0

#### Commit

The working-tree changes below (the mandatory gate completed inside the app), on top of the v3.8.0 commit and its tracker note. A release that keeps a promise the gate had been making badly: the first screen told people to finish signing in "in their browser", and a new account that signed *up* rather than in was dropped on the dashboard inside the sign-in window with no way back to the app — so the very flow the product is built on ("no anonymous use") could not be finished by creating an account. The gate now shows the platform in a window of the app's own, and both entry paths — sign in, and sign up (with a plan where required) — end in the app being connected. The desktop's sign-in window is a top-level child window, not an iframe, because the platform sends `X-Frame-Options: SAMEORIGIN` on every response; that header is left exactly as it is.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

The first screen is now part of the app: sign in or pick a plan without leaving it.

#### One-line summary

The mandatory first-launch gate asked people to finish in a browser, and a brand-new account that signed up was left on the dashboard with no way to connect the app. The gate now opens the platform's sign-in and plan pages in a window of the app itself and hands the connection back automatically; signing up carries straight through to the app, including the plan chooser when a plan is required.

#### Platform changes

- **The embed flag.** `HomeController::index` stores `desktop_embed` when the desktop entry carries `?desktopEmbed=1`, and clears it when it does not, so the flag always states the current flow.
- **Automatic hand-back.** `DesktopAuthorizationController::complete` passes `autoRedirect` (true only for an embedded entry), and `resources/views/desktop/complete.blade.php` navigates to the `idexalos://den-auth?grant=...` link on load when it is set. The link stays on the page as the fallback, so a browser flow is unchanged.
- **Signing up finishes the connection.** `RegisteredUserController::store` honours the same `desktop_auth` flag that `AuthenticatedSessionController` already honours: a registration that started from the gate is redirected to `desktop.authorize` (which issues the handoff) instead of the dashboard.
- **A required plan is chosen first, then the app connects.** With `feature_require_plan_selection` on, that registration goes to the plan chooser (`dashboard.billing`) instead. `BillingController::index` hands the desktop connection on as soon as the organization holds a subscription, and consumes the flag so an ordinary later visit to billing is never redirected. This also covers a return from a hosted checkout, where the plan is active by the time billing loads.
- **The flag is consumed once.** `DesktopAuthorizationController::store` forgets `desktop_auth` after issuing the handoff.
- **The download page says what happens on first launch.** `resources/views/public/download.blade.php` gains a "What happens on first launch" note, and the third install step now says the first screen connects the account inside the app; both strings are in English and Arabic. A test asserts the note and that step render.
- Copy is in English and Arabic (both changelog languages; the app's two new status lines are in English and fall back for the rest, matching how `den.*` strings already work).

#### Desktop / app changes

- **The in-app sign-in window.** `apps/desktop/electron/main.mjs` gains `openEmbeddedAuthWindow`, a 520×780 child `BrowserWindow` in a persistent partition (`persist:idexalos-auth`), which intercepts `will-navigate`, `will-redirect` and `setWindowOpenHandler` for this app's own `den-auth` grant and hands it to the existing deep-link channel (`queueDeepLinks`). One window at most; a second request focuses it.
- **Why a window, not a frame.** The platform sends `X-Frame-Options: SAMEORIGIN` on every response (`app/Http/Middleware/SecurityHeaders.php:19`). Framing rules do not apply to top-level navigation, so a child window shows the pages without weakening that header.
- **New bridge command `openEmbeddedAuth`.** Declared in `packages/types/src/desktop-ipc.ts`, implemented in `desktopCommandHandlers`, and added to `ENTERPRISE_PREACTIVATION_COMMANDS` in `desktop-distribution.mjs` — the gate runs before the app is activated, so the command must be callable pre-activation.
- **App side.** `buildDenAuthUrl` takes a `desktopEmbed` option; `openDesktopEmbeddedAuth` (new, in `app/lib/desktop.ts`) opens the window and returns a boolean, never throwing; `tryOpenEmbeddedAuthUrl` opens the window and falls back to the system browser, so the gate can always be passed. `ForcedSigninPage` and the enterprise activation gate call it; the status line is now window-neutral ("the window that just opened").
- **Pure logic is tested off Electron.** `embeddedAuthHandoffUrl` and the version-robust `embeddedAuthNavigationUrl` moved to their own module (`apps/desktop/electron/embedded-auth.mjs`) so they are covered by `node --test` without booting Electron.

#### Verified before deploy

- Laravel: the full suite plus the new file (see the run recorded below), Pint clean, blade compiles.
- New `DesktopEmbedGateTest` (8 tests) covers: the entry records the embed flag and clears it on a later plain entry; `/desktop/complete` renders the auto-hand-back only for an embedded flow and keeps the click-through link for a browser flow; signing up from the gate redirects to the authorize step with the flag intact; with a plan required it redirects to the plan chooser instead; the chooser hands the connection on once a subscription exists and consumes the flag; and an ordinary visit to billing is never redirected.
- New `apps/desktop/electron/embedded-auth.test.mjs` (11 tests) covers the hand-off matcher and the navigation-URL reader: this app's grant is returned; the platform's own pages, another scheme, a different path, a missing grant, empty and unparseable input, surrounding whitespace, and a custom self-hosted scheme are all ignored; and the target URL is read from the positional argument or the event, whichever the Electron version supplies.
- New `apps/app/tests/embedded-auth-gate.test.ts` (4 tests) covers the app side: the embed flag is on the URL only when asked, and the bridge reports failure rather than throwing when there is no embedded window.
- `PublicDownloadPageTest` gains a case that the first-launch note and the updated install step render.
- `desktop-distribution.test.mjs` gains a test that `openEmbeddedAuth` is on the pre-activation allowlist (and that an ordinary command is not).
- `CanonicalHostTest` updated to assert `3.9.0`; `node scripts/release/review.mjs` permits `3.9.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `3.9.0`:

- (to be filled after publish)

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `3.9.0`.
2. ⬜ No new migration this release; confirm nothing is pending and rebuild the config, route and view caches.
3. ⬜ From a fresh install, press sign in and confirm the platform opens inside the app, and that completing it connects the app without a browser.
4. ⬜ From a fresh install, create a new account from the gate and confirm it ends by connecting the app (and, with a plan required, that the plans are shown first and the app connects once a plan is active).
5. ⬜ Confirm a browser hand-off (no `desktopEmbed`) still shows the click-through link and does not auto-navigate.
6. ⬜ Publish the desktop installer `3.9.0` to the release center and the public GitHub release; `/v1/app-version` lists `3.9.0` first.
7. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- The in-app sign-in window shows the platform's own pages. A provider that refuses to run inside an app window (some federated sign-ins do) is not intercepted — the browser fallback is what a person uses then; the window does not spoof a browser identity.
- The plan chooser's hosted-checkout return relies on the billing page seeing an active subscription; a gateway that returns to a page other than billing would not hand off until billing is next opened.
- The separate "your plan does not grant desktop access" screen (`PlanRequiredGate`) still opens the plans page in the system browser rather than the app window, because a session created by a browser hand-off lives in the browser, not the app window's partition.
- Email verification stays a platform policy: while `require_email_verification` is on, a new account is verified before the gate completes, and verification itself does not carry the desktop flag.
- `POST /v1/mcp/token` and `GET /v1/billing/web` still need a capability the platform does not advertise.
- `features:sync` regenerates the Helm chart; the Den API contract/SDK step still needs MySQL 8.4 (this machine runs MariaDB 10.4), so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration before the platform deploy — the same note as v2.9.0–v3.8.0.
- Same parity gap as 2.5.x–3.8.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v3.8.0

#### Commit

The working-tree changes below (one screen to control every provider), on top of the v3.7.0 commit and its tracker note. A platform release that closes the last gap between what the operator was promised over providers and what the admin panel actually offered: the proxy manager next door showed a Plans column and a state badge, while the providers screen showed neither and hid the on/off action inside the edit form. There was also no way to cancel members' own keys for the whole deployment. Both are now on one screen. The desktop has no source change and is rebuilt for version parity only. The new control is the platform-wide `byok_enabled` setting, which is on by default so nothing changes until an operator turns it off.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

One screen to control every provider, and to cancel members' own keys everywhere.

#### One-line summary

The operator was promised control of every provider "exactly like the proxy manager", and the proxy manager was ahead: it lists a Plans column and a state row action, while the providers screen listed neither and kept the enable/disable inside the edit form — so answering "which plans get this provider?" meant opening every plan in turn. The providers screen now shows each provider's plans as badges (or "all plans" when it serves every plan), carries an enable/disable action on the row, and adds the one control that was missing entirely: a platform-wide switch that cancels members' own API keys for the whole deployment, outranking both the plan feature and the per-workspace switch.

#### Platform changes

- **The plans a provider serves are visible where the provider is.** `AdminProviderController::index()` eager-loads `plans`, and the card shows them as badges linked to the plan that grants them; a provider with no plan rows is labelled "All plans", which is the platform's own rule (no assignment serves everyone) said in words rather than left blank.
- **On/off from the list.** `POST /admin/providers/{provider}/toggle` flips `is_active`, audits it as `admin.provider.toggled`, and announces the change so connected desktops refetch. Because the gateway reads `is_active` on every build of the desktop catalog, switching a provider off takes every one of its models out of every desktop — verified end to end in a test that reads `/v1/inference-providers` before and after.
- **The platform-wide BYOK switch.** `POST /admin/providers/byok` writes the `byok_enabled` setting (default on). `MeController::customProvidersAllowed()` now checks it **first**, before the plan feature and before the workspace switch, so while it is off no plan and no workspace setting can bring members' own keys back — the operator's way to require their published plans everywhere. It is audited and announced like any other entitlement change.
- Copy is in English and Arabic, including the badge, the button, the confirmation and the per-provider on/off messages.

#### Desktop / server changes

- No contract or enforcement change: `packages/types`, `apps/server` and the desktop config are untouched. The switch rides the existing `allowCustomProviders` key the app already gates on, so no client change is needed.

#### App changes

- No app source change. The desktop build is re-run only for version parity: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `3.8.0`.

#### Verified before deploy

- Laravel: `519 tests / 3120 assertions` (Feature + Unit), Pint clean.
- New coverage: `AdminProviderConsoleTest` (6 tests / 25 assertions) — the list shows a provider's plans and "all plans"; the operator can turn a provider off and on from the list with an audit line; **a provider switched off disappears from `/v1/inference-providers` for a real member key** (read before and after); the platform BYOK switch is reachable from the console and turns `allowCustomProviders` off for a member; **the platform switch outranks a plan that explicitly grants own-keys**; and a member cannot reach the console or toggle a provider.
- `CanonicalHostTest` updated to assert `3.8.0`.
- `node scripts/release/review.mjs` permits `3.8.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `3.8.0`:

- Built from the same tree, alone: `build:electron rc=0`, `electron-builder rc=0`; artifact `idexal-os-setup-3.8.0-x64.exe`, **374,416,092 bytes**, sha256 `5dc30351821417711f875bbbd3fb6b151511f63b07c5b50e36b29788e4e23c51` (blockmap 378,524 B).
- Published to the release center and the public GitHub release: (to be filled after publish)

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `3.8.0`.
2. ⬜ No new migration this release; confirm nothing is pending and rebuild the config, route and view caches.
3. ⬜ Open `/admin/providers` and confirm every provider shows its plans, or "All plans" when it has none.
4. ⬜ Turn one provider off from the list and confirm its models leave `/v1/inference-providers` for a member key; turn it back on and confirm they return.
5. ⬜ Turn the platform BYOK switch off and confirm `allowCustomProviders` is false for a member whose plan grants own-keys, then turn it back on.
6. ⬜ Publish the desktop installer `3.8.0` to the release center and the public GitHub release; `/v1/app-version` lists `3.8.0` first.
7. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- The platform BYOK switch is a **setting**, not a plan feature, so it cannot itself be varied per plan; its purpose is the opposite — to outrank plans. Per-plan variation stays where it was: `Plan::FEATURE_CUSTOM_PROVIDERS`.
- Turning a provider off is immediate for the catalog, but a chat already running against its model finishes on the connection it already has; a model list refresh is what a member sees change.
- The providers screen still edits a provider's details (keys, models, backup chain) on its own pages; the console adds visibility and the on/off action rather than merging those screens.
- Teams remain unsupported, so a per-team provider allocation cannot be expressed.
- `POST /v1/mcp/token` and `GET /v1/billing/web` still need a capability the platform does not advertise.
- `features:sync` regenerates the Helm chart; the Den API contract/SDK step still needs MySQL 8.4 (this machine runs MariaDB 10.4), so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration before the platform deploy — the same note as v2.9.0–v3.7.0.
- Same parity gap as 2.5.x–3.7.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v3.7.0

#### Commit

The working-tree changes below (who inside a workspace may use each plugin), on top of the v3.6.0 commit and its tracker note. A platform release that makes plugin access real instead of a stub. The desktop's Library already had an access screen; it called two endpoints this platform never served, and v3.3.0 answered them with a derived org-wide grant — honest, but it meant nobody could actually be named. Now there is a table, the grant decides what the library lists, and the one target this server cannot honour (teams) is refused with a reason. No feature flag: a plugin with no grants behaves exactly as before, so nothing changes until an operator names someone.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

You decide who inside a workspace may use each plugin.

#### One-line summary

Plugin access was a read-only fiction: the library reported "the whole workspace" because that was the only truth it could express, and `POST`/`DELETE /v1/plugins/{id}/access` were never served. They are now, against a real table, and the grant **gates delivery** — a member who is not entitled to a plugin does not see it in the library at all. A plugin with no grants still names nobody and stays available to everyone, which is exactly the behaviour before this existed, so an operator's existing installs keep working.

#### Platform changes

- **The grant is stored.** `plugin_access_grants` holds one row per workspace, plugin and target: an org-wide row, or a row naming one membership (`organization_user`). The role is one the desktop knows (`viewer`, `editor`, `manager`, `owner`).
- **The grant decides what the app shows.** `installedExtensions()` now rejects an installed plugin the member is not entitled to: no grants for the plugin means available to the workspace, an org-wide grant says so explicitly, and a grant for the member's membership narrows it. This is what makes the writes meaningful rather than decorative — a grant that changed nothing would be a lie in the UI, the same rule this project applied to the usage allowance.
- **The three calls exist.** `GET /v1/plugins/{id}/access` returns the real grants, `POST` creates one for `orgWide` or `orgMembershipId` (returning the `{item: …}` the client parses), and `DELETE /v1/plugins/{id}/access/{grantId}` revokes it. `GET /v1/plugins?includeAccess=true` carries each plugin's real list.
- **What cannot be honoured is named.** A `teamId` target is refused with 422 `teams_unsupported` and a translated reason, rather than being stored as a per-member grant the operator did not ask for. An unknown membership is a 404 `unknown_membership`.
- **Managing access is an operator action,** behind the same `canManageApiKeys()` gate the workspace's API keys and desktop devices already use; a member without it gets 403.

#### Desktop / server changes

- No contract or enforcement change: `packages/types`, `apps/server` and the desktop config are untouched. The endpoints and shapes already existed in the client.

#### App changes

- No app source change. The desktop build is re-run only for version parity: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `3.7.0`.

#### Verified before deploy

- Laravel: `513 tests / 3095 assertions` (Feature + Unit), Pint clean.
- New coverage: `PluginLibraryApiTest` grows to 15 tests / 66 assertions. The five new ones matter most: **a per-member grant narrows the plugin — the granted member still sees it in `/v1/me/library` and a second member of the same workspace does not**; revoking the grant restores the org-wide answer; an org-wide grant is reported explicitly and never denies anyone; a team target is refused with `teams_unsupported` and stores nothing; and a grant for a membership that is not in the workspace is refused with `unknown_membership` and stores nothing.
- `CanonicalHostTest` updated to assert `3.7.0`.
- `node scripts/release/review.mjs` permits `3.7.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `3.7.0`:

- Built from the same tree: artifact `idexal-os-setup-3.7.0-x64.exe`, **374,416,124 bytes**, sha256 `5e83fc1206e18269b80fd08bb09f803c5cca862569863d56597204641086e875` (blockmap 378,478 B).
- It was produced by the packaging run that began as 3.6.0 (see that entry above): the version files were moved to 3.7.0 while it was still working, so it wrote 3.7.0. The renderer bundle inside was built from the 3.6.0 source, and the only difference between 3.6.0 and 3.7.0 is version strings, so the artifact is a valid 3.7.0 — but that is luck, not process, and the two entries above record it as the mistake it was.
- Published to the release center and the public GitHub release: (to be filled after publish)

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `3.7.0`.
2. ⬜ Run the new migration `2026_11_10_000000_create_plugin_access_grants_table` and confirm nothing is pending; it creates one empty table, so every installed plugin stays available to its workspace until an operator names someone.
3. ⬜ With an operator key, grant one plugin to a single member and confirm that member still sees it in `/v1/me/library` while another member of the same workspace does not.
4. ⬜ Revoke the grant and confirm the plugin is available to the workspace again.
5. ⬜ Confirm a `teamId` target answers 422 `teams_unsupported` and that a member without management rights answers 403.
6. ⬜ Publish the desktop installer `3.7.0` to the release center and the public GitHub release; `/v1/app-version` lists `3.7.0` first.
7. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- **Teams are still not supported**, so a per-team grant cannot be expressed; the desktop is told so plainly instead of being given a wrong answer. Adding teams is a product decision, not an integration gap.
- A grant names a membership, so a member who leaves and rejoins needs re-granting; the row is deleted with the membership it named.
- Access is enforced where the library is listed; a plugin already running inside a session keeps running until the session is reloaded, exactly as other entitlement changes behave.
- The allowance from v3.6.0 remains a soft budget by design; the hard stop stays the plan's request and token limits.
- `POST /v1/mcp/token` and `GET /v1/billing/web` still need a capability the platform does not advertise.
- `features:sync` regenerates the Helm chart; the Den API contract/SDK step still needs MySQL 8.4 (this machine runs MariaDB 10.4), so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration before the platform deploy — the same note as v2.9.0–v3.6.0.
- Same parity gap as 2.5.x–3.6.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v3.6.0

#### Commit

The working-tree changes below (a plan can carry a monthly spend allowance, and a member can ask for more), on top of the v3.5.0 commit and its tracker note. A platform release that closes the last item on the integration ledger: the desktop's "ask for more" restore flow was unreachable because it keys on a usage bucket this platform never sent. Now it sends a real one, and the whole loop — see the allowance, exhaust it, ask, approve or decline, see it grow — works end to end. The desktop has no source change and is rebuilt for version parity only. No feature flag: a bucket is only sent when the plan sets an allowance, so a platform that sets none behaves exactly as before.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

A plan can carry a monthly spend allowance, and a member can ask for more.

#### One-line summary

This was the one genuine gap left after the API ledger closed: when a member's plan ran out, the desktop showed the numbers and offered nothing, because its "ask for more" button keys on a usage *bucket* and this platform returned `buckets: []`. The platform now expresses its allowance as a real bucket — base from the plan, extension from what an operator approved for the same period, spend from the period's own counter — so the member can ask, the operator can answer on the gateway screen, and the allowance the member then sees grew by exactly what was approved. It is reported as a soft budget because the hard stop on this platform remains the plan's request and token limits; claiming a block the platform does not enforce would be a lie in the UI.

#### Platform changes

- **The allowance is a plan limit, in money.** `Plan::LIMIT_MONTHLY_COST_USD` (`monthly_cost_usd`) is a new limit with its own admin field on the plan form, kept as a decimal rather than a count. A plan without it sends no bucket, so nothing changes on a platform that does not adopt it.
- **The bucket is built from figures that already exist.** `UsageAllowanceService::bucketFor()` reads the plan's allowance, the period's `UsageCounter.cost` for spend, and the sum of *approved* extensions for that period — one `month` bucket with `baseAllowanceMicroUsd`, `extensionMicroUsd`, `allowanceMicroUsd`, `usedMicroUsd`, `remainingMicroUsd`, the real `resetAt`, `allowRequestReset` true and `hardLimit` false. `canRequestReset` is true only when the allowance is exhausted *and* no ask is already waiting.
- **A member can ask.** `POST /v1/gateway/usage-limit-reset-requests` records one open ask per member and bucket, notifies the organization's managers, writes a `member.usage_limit_request.created` audit line, and answers in the `GatewayUsageResetRequest` shape the desktop parses. It refuses (404) a bucket the plan does not grant and (409) an ask made while the allowance still stands.
- **An operator answers.** `usage_limit_reset_requests` stores the ask with the period it concerns, so an approval extends *that* period and never a later one. The gateway screen gains a "Spend increase requests" panel showing the member, the reason, and the period's used-against-allowance, with approve (an amount in USD) and deny; both are audited and both tell the member, and a denial says they may ask again.

#### Desktop / server changes

- No contract, enforcement or delivery change: `packages/types`, `apps/server` and the desktop config are untouched. The bucket and the request already existed in the shared contract; this release makes the platform answer them.

#### App changes

- No app source change. The desktop build is re-run only for version parity: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `3.6.0`.

#### Verified before deploy

- Laravel: `508 tests / 3069 assertions` (Feature + Unit), Pint clean.
- New coverage: `UsageAllowanceRequestTest` (10 tests / 61 assertions) — a plan without an allowance sends no bucket; a plan with one sends a real bucket with the right base, spend and soft-budget flag; the ask is allowed only when exhausted and lands as `pending` with a notification and an audit line; asking twice keeps one row; an operator approval grows the bucket by exactly the approved amount and tells the member; a denial grants nothing and tells them; the queue renders with the member and reason; an unknown bucket is refused; an ask while the allowance remains is refused; and a member cannot reach the operator queue.
- **Two defects were caught by these tests before commit:** `canRequestReset` ignored an already-pending ask (so the app would offer the button twice), and the maintenance `user()` helper the controller called does not exist on the base controller.
- **A live render check confirmed the whole loop against real data:** the gateway screen showed the spend-increase panel with a real pending ask, the member's name and email, the period, the reason, `$10.00 / $10.00`, and working approve (pre-filled with the current allowance) and deny forms.
- `CanonicalHostTest` updated to assert `3.6.0`.
- `node scripts/release/review.mjs` permits `3.6.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `3.6.0`:

- **No installer of its own.** The packaging run started for it finished after the version files had already moved on, so it wrote `idexal-os-setup-3.7.0-x64.exe` (recorded under 3.7.0 below). This is the same operator error the v3.4.0 note records — one packaging run at a time, with the version files frozen until it finishes. A 3.6.0 artifact, if wanted, is a rebuild of commit `4a04515`.

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `3.6.0`.
2. ⬜ Run the new migration `2026_11_09_000000_create_usage_limit_reset_requests_table` and confirm nothing is pending; it creates one empty table and changes nothing else.
3. ⬜ Give one plan a monthly spend allowance and confirm `/v1/gateway/usage-limits/me` returns one `month` bucket for a member on it, and none for a member whose plan has no allowance.
4. ⬜ Exhaust it (or set a low allowance), confirm the desktop offers "ask for more", post the ask, and confirm a manager is notified.
5. ⬜ Approve it from `/admin/gateway` and confirm the member's bucket allowance grew by the approved amount.
6. ⬜ Publish the desktop installer `3.6.0` to the release center and the public GitHub release; `/v1/app-version` lists `3.6.0` first.
7. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- The allowance is a **soft budget**: the hard stop remains the plan's request and token limits, and the bucket says `hardLimit: false` so the app never claims a block that is not enforced. Enforcing the cost allowance as a hard stop at the gateway is a separate decision, and it is deliberately not made here.
- The extension is granted per member request, so several members of one workspace asking in one period each add their own extension; the workspace-level view is the sum. A single workspace-wide raise is not offered.
- Requests are not paginated on the admin panel (the 50 oldest pending are shown) and there is no expiry sweep for stale asks; a period's asks simply stop mattering when the period ends.
- Plugin **writes** (per-team grant and revoke) remain unimplemented: this platform has no per-team access model.
- `POST /v1/mcp/token` and `GET /v1/billing/web` still need a capability the platform does not advertise, so the honest answer stays "not available on this server".
- `features:sync` regenerates the Helm chart; the Den API contract/SDK step still needs MySQL 8.4 (this machine runs MariaDB 10.4), so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration before the platform deploy — the same note as v2.9.0–v3.5.0.
- Same parity gap as 2.5.x–3.5.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v3.5.0

#### Commit

The working-tree changes below (a plugin row says what the plugin is made of), on top of the v3.4.0 commit and its tracker note. A platform release: `GET /v1/me/library` now reports each plugin's component kinds and count instead of leaving the fields the desktop renders absent. No feature flag, no schema change, and no desktop source change — the platform was simply not filling in fields the client already reads.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

Every plugin row says what the plugin is made of.

#### One-line summary

`parseDenLibraryItems()` reads `componentKinds` and `componentCount` on every plugin row, and the Library renders them ("3 skills · 1 connection"). The platform never sent either, so every installed plugin read as having no components at all — a visible emptiness built from a field the platform already had the data for, because a plugin's `includes` list is right there in the catalogue. The row now derives the kinds from that list and counts it.

#### Platform changes

- **`library()` fills the component fields.** For each installed plugin it resolves the catalogue's `includes` members, collects their distinct `type`s as `componentKinds`, and reports `componentCount` as the member count. A plugin that declares no `includes` counts itself, so a row still says something true rather than nothing.
- **It cannot overstate.** The kinds come from the plugin's own catalogue definition, so a row can never name a component the plugin does not contain.

#### Desktop / server changes

- No contract or enforcement change: `packages/types`, `apps/server` and the desktop config are untouched.

#### App changes

- No app source change. The desktop build is re-run only for version parity: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `3.5.0`.

#### Verified before deploy

- Laravel: `498 tests / 3008 assertions` (Feature + Unit), Pint clean.
- New coverage: `PluginLibraryApiTest` grows to 10 tests / 40 assertions — a Library row reports a non-empty `componentKinds` and a `componentCount` of at least one, alongside the existing plugin-list, access, batch-resolve and single/batch-agreement cases.
- `CanonicalHostTest` updated to assert `3.5.0`.
- `node scripts/release/review.mjs` permits `3.5.0`.
- **Two earlier tracker notes in this file were corrected rather than left standing:** the 3.3.0 installer was recorded as never built when it had in fact built (374,415,902 bytes, sha256 `5dbde6e9…d837d`), and the 3.4.0 installer was recorded as pending when the packaging step had actually been started against an output directory a concurrent build still held, so no artifact of its own exists. Both entries now state what really happened.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `3.5.0`:

- (to be filled after publish)

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `3.5.0`.
2. ⬜ No new migration this release; confirm nothing is pending and rebuild the config, route and view caches.
3. ⬜ With a member key, confirm `/v1/me/library` rows carry `componentKinds` and `componentCount`.
4. ⬜ Open the Library on a desktop and confirm a plugin row shows what it is made of.
5. ⬜ Publish the desktop installer `3.5.0` to the release center and the public GitHub release; `/v1/app-version` lists `3.5.0` first.
6. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- **The one substantive item left is the quota-increase path**, and it is a feature, not a missing endpoint: when a member's plan request/token allowance runs out, the desktop shows the numbers (`PlanUsageSection`) but offers no action, because the "ask for more" affordance keys on a usage bucket and this platform returns `buckets: []`. Making it work honestly needs a *real* allowance the bucket can express — a monthly cost allowance on the plan (`Plan` limit + admin field), a bucket built from `GatewayRequest.cost` for the period, `canRequestReset`, a `usage_limit_reset_requests` table, an operator queue to approve it, and the approval raising the period's extension. Expressing requests/tokens in the money-denominated bucket fields instead would print a requests quota as dollars, which the UI would show as a lie, so that shortcut is refused on purpose.
- Plugin **writes** (grant and revoke) remain unimplemented: this platform's access is org-wide and derived, and there is no per-team model, so a grant cannot be stored meaningfully yet.
- `POST /v1/mcp/token` and `GET /v1/billing/web` still need a capability the platform does not advertise (an MCP server; the `idexalOsWeb` contract), so the honest answer stays "not available on this server" rather than a stub.
- `features:sync` regenerates the Helm chart; the Den API contract/SDK step still needs MySQL 8.4 (this machine runs MariaDB 10.4), so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration before the platform deploy — the same note as v2.9.0–v3.4.0.
- Same parity gap as 2.5.x–3.4.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v3.4.0

#### Commit

The working-tree changes below (the Library resolves a page in one request, and the integration surface is accounted for), on top of the v3.3.0 commit and its tracker note. A platform release: one more endpoint the desktop calls is now served, and the remaining surface was audited call by call so that the ones that stay 404 have a recorded reason rather than being an open question. The desktop has no source change and is rebuilt for version parity only. No feature flag: a read of data the platform already holds.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

The Library loads in one request, and the last of the integration is accounted for.

#### One-line summary

The desktop resolves plugins one at a time per plugin, which is why the Library made a request per installed plugin; the batched endpoint it prefers did not exist here. It does now, and it shares its implementation with the single-plugin resolver so the two cannot drift. Beyond that, every endpoint the desktop's client uses was called against the platform with a real member key: what is left answering 404 is either hidden behind a feature the platform reports as off or something the platform genuinely does not run, and each of those now has a recorded reason.

#### Platform changes

- **A page of plugins in one request.** `GET /v1/resolved-plugins?pluginIds=a,b,c` returns `{plugins: {<id>: {items: [...]}}}` and, for an id that is not in the catalogue, `failures: {<id>: {status: 404, error: "plugin_not_found"}}` — so one unknown plugin never fails the batch, which is exactly what the client's `pluginResolvedFromBatch()` expects. Ids are de-duplicated and capped at 100; an empty list is a 422.
- **One implementation, two endpoints.** `pluginResolvedItems()` now builds a plugin's configuration members, and both `GET /v1/plugins/{id}/resolved` and the new batched endpoint call it, so the single and batch answers cannot disagree. A test asserts the two payloads are byte-identical for the same plugin.
- **`ui.errors.not_found` added** in both locales for the failure entry; the repository's own translation guard (`TranslationKeysTest`, `TranslationUsageTest`) rejected the first draft that used a key which did not exist, which is how that was caught.

#### Desktop / server changes

- No contract or enforcement change: `packages/types`, `apps/server` and the desktop config are untouched.

#### App changes

- No app source change. The desktop build is re-run only for version parity: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `3.4.0`.

#### Verified before deploy

- Laravel: `497 tests / 3001 assertions` (Feature + Unit), Pint clean.
- New coverage: `PluginLibraryApiTest` grows to 9 tests / 33 assertions — the batched resolver answers a page of plugins keyed by the id the caller asked for; one unknown plugin is reported under `failures` without failing the batch; an empty id list is a 422; and the single and batched resolvers return identical payloads for the same plugin.
- **The integration surface was audited, not assumed.** A probe authenticated as a freshly-registered member called each endpoint the desktop's client uses. Before this release: `/v1/plugins`, `/v1/plugins/{id}/access` and `/v1/resolved-plugins` answered **404**; after it, all three answer **200** (verified again with a real key). What remains is recorded with its reason in the Known gaps below.
- `CanonicalHostTest` updated to assert `3.4.0`.
- `node scripts/release/review.mjs` permits `3.4.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `3.4.0`:

- **Not built.** Its `build:electron` step finished (`rc=0`), but the packaging step was started while the v3.3.0 packaging run still held the shared `dist-electron/win-unpacked` directory, so no artifact of its own was produced. That was an operator mistake: two electron-builder runs must never be started against one output directory. v3.5.0 follows immediately from the same tree and is built on its own, so the release center carries 3.3.0 and 3.5.0; if a 3.4.0 artifact is ever wanted it is a rebuild of this commit, not a different build.

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `3.4.0`.
2. ⬜ No new migration this release; confirm nothing is pending and rebuild the config, route and view caches.
3. ⬜ With a member key, confirm `/v1/plugins`, `/v1/plugins/{id}/access` and `/v1/resolved-plugins` answer 200, and that an unknown id inside the batch is reported under `failures` while the batch still answers 200.
4. ⬜ Open the Library on a desktop and confirm the plugin page resolves.
5. ⬜ Publish the desktop installer `3.4.0` to the release center and the public GitHub release; `/v1/app-version` lists `3.4.0` first.
6. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

**Every remaining endpoint the desktop calls now has a recorded reason it is not served here.** This is the audit result, not a to-do list of unknowns:

- `POST /v1/gateway/usage-limit-reset-requests` — **not reachable, so not a gap.** The desktop only offers "request more" on a usage *bucket* whose `canRequestReset` is true; this platform's quota model is plan request/token allowances, and `GET /v1/gateway/usage-limits/me` returns `buckets: []` with `planUsage` present. Implementing the endpoint would be dead code. Making it reachable means teaching this platform USD-budget buckets with reset approval — a real feature, and the next substantive one.
- `GET /v1/org/workbot-settings` — **not reachable.** Its only consumer is the Calendar data hook, gated by `automationsEnabled` (false here) and the `automationCalendar` feature (absent). Workbot is not part of this platform.
- `GET /v1/resolved-plugins` — **done in this release.**
- `POST /v1/mcp-connections/discover` — the desktop now degrades honestly (v3.3.0); serving it needs an MCP discovery implementation.
- `POST /v1/mcp/token` and `GET /v1/billing/web` — both need a capability this platform does not advertise (an MCP server, and the `idexalOsWeb` contract). The desktop already negotiates on the second; the first is the honest "not available on this server" case rather than a stub token pointing at a resource that does not exist.
- Plugin **writes** (per-team or per-member grant and revoke) remain unimplemented because teams do not exist on this platform; reading is enough for the Library to load.
- `features:sync` regenerates the Helm chart; the Den API contract/SDK step still needs MySQL 8.4 (this machine runs MariaDB 10.4), so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration before the platform deploy — the same note as v2.9.0–v3.3.0.
- Same parity gap as 2.5.x–3.3.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v3.3.0

#### Commit

The working-tree changes below (the desktop and the platform agree on what the server offers), on top of the v3.2.0 commit and its tracker note. A platform + desktop release with no change to what either does when both are current: the platform gains two plugin endpoints the desktop's Library has always asked for, and the desktop learns to read 404/405/501 from an optional endpoint as "this server does not offer that" instead of as an error. No feature flag: the new endpoints are reads of data the platform already holds, and the desktop's change is the fail-closed pattern already used elsewhere in the same client.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

The Library and the desktop agree on what your server offers.

#### One-line summary

The desktop asks its control plane for a large, specific API. A self-hosted platform implements a deliberate subset of it, and the rest answered 404 — which the desktop mostly surfaced as an error, so a screen the user could see failed on a call the platform was never asked to implement. Two things were fixed, from both sides: the platform now serves the plugin list and each plugin's access list (the Library's access view), and the desktop now treats "not found / not allowed / not implemented" on an optional endpoint as an honest "this server does not offer that", with the same fail-closed shape the client already used for two other endpoints.

#### Platform changes

- **The plugin endpoints exist.** `GET /v1/plugins` lists the plugins installed for the workspace with the shape the desktop parses, optionally including each plugin's access list (`includeAccess=true`); `GET /v1/plugins/{id}/access` returns one plugin's access list. Both read the marketplace catalogue the way `library()` and `pluginResolved()` already do, so there is one source of truth and no new table.
- **Access is reported as it really is.** This platform's access model is organization-wide — an installed plugin is available to every member — and teams do not exist here. Rather than an empty list, which the Library would read as "nobody has access", each plugin reports one org-wide `viewer` grant. An unknown or uninstalled plugin is a 404, exactly as the client expects.
- A real gap analysis drove this: a probe authenticated as a freshly-registered member and called every endpoint the desktop's client uses. Eleven answered 404. Five of those (`/v1/apps`, `/v1/automations`, `/v1/automation-runners/presence`, `/v1/cloud/instance`, `/v1/billing/web`) are already masked by a feature the platform reports as off or by a capability the platform does not advertise, so they are not user-visible; the plugin ones were, and are now served.

#### Desktop / server changes

- **An optional endpoint that is missing is not a failure.** A shared `CONTROL_PLANE_ABSENT_STATUSES` (`404`, `405`, `501`) names the statuses a control plane answers when it does not implement an endpoint, and `discoverMcpConnectionRequirements()` now substitutes a `status: "unsupported"` discovery instead of throwing. That shape is what the UI already knows how to explain, so the Add-a-server checklist says the server cannot be checked here rather than showing a raw error.
- No contract or enforcement change: `packages/types`, `apps/server` and the desktop config are untouched. The desktop already tolerated these statuses on `getInventoryRevision()` and `listOrgGatewayProviders()`; this extends the same rule to a third call.

#### App changes

- The MCP discovery call degrades honestly on a control plane without discovery, as described above.
- Version parity in every other app surface: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `3.3.0`.

#### Verified before deploy

- Laravel: `493 tests / 2985 assertions` (Feature + Unit), Pint clean.
- New coverage: `PluginLibraryApiTest` (5 tests / 17 assertions) — the plugin list includes an org-wide access grant keyed by the prefixed id; access is omitted unless `includeAccess` is asked for; one plugin's access list loads; an unknown plugin is a 404; and a request without a key is refused.
- App: `den-optional-endpoints.test.ts` (3 tests) — the substitute the desktop falls back to parses into a real discovery, a full-control-plane discovery still parses, and a malformed payload is rejected rather than half-read. `pnpm typecheck` exit `0`.
- The app test caught a real defect while it was being written: the first fallback payload (`{requirements: []}`) does **not** satisfy the discovery schema, which would have turned a missing endpoint into an "invalid response" error — worse than the 404 it replaced. It now returns the `unsupported` shape the schema and the UI both accept.
- `CanonicalHostTest` updated to assert `3.3.0`.
- `node scripts/release/review.mjs` permits `3.3.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `3.3.0`:

- Built from the same tree: `build:electron rc=0`, `electron-builder rc=0`; artifact `idexal-os-setup-3.3.0-x64.exe`, **374,415,902 bytes**, sha256 `5dbde6e9a20931e6097e52b89114b1d5de1eae4828ea6a475c282988853d837d` (blockmap 378,735 B).
- An earlier note in this file claimed 3.3.0 had no separate installer. That was wrong and is corrected here: the build finished normally. The mistake came from reading its log while the following build had already replaced the shared output directory.

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `3.3.0`.
2. ⬜ No new migration this release; confirm nothing is pending and rebuild the config, route and view caches.
3. ⬜ With a member key, confirm `GET /v1/plugins?includeAccess=true` and `GET /v1/plugins/{id}/access` answer 200 and that an unknown plugin answers 404.
4. ⬜ Confirm the endpoints that were already served are unchanged (`/v1/me/library`, `/v1/marketplaces`, `/v1/mcp-connections/presets`).
5. ⬜ Publish the desktop installer `3.3.0` to the release center and the public GitHub release; `/v1/app-version` lists `3.3.0` first.
6. ⬜ Open the Library's access view on a desktop and confirm it loads.
7. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- **The remaining six unimplemented endpoints are the next slice, in this order:** `POST /v1/gateway/usage-limit-reset-requests` (a member asking for more quota — it needs a small request table and an operator queue, the same shape the connector request already uses), `GET /v1/org/workbot-settings`, `GET /v1/resolved-plugins` (the batched form of the single-plugin resolver that already exists), `POST /v1/mcp-connections/discover`, `POST /v1/mcp/token` and `GET /v1/billing/web`. The last two need a capability the platform does not advertise (an MCP server, and the `idexalOsWeb` contract), so the honest fix there is negotiation, not a stub.
- Plugin *writes* (grant and revoke per team or member) are not implemented: the platform has no per-team plugin access model, so the desktop's grant/revoke calls still 404. Reading is enough for the Library to load, but managing access per team waits for teams.
- `features:sync` regenerates the Helm chart; the Den API contract/SDK step still needs MySQL 8.4 (this machine runs MariaDB 10.4), so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration before the platform deploy — the same note as v2.9.0–v3.2.0.
- Same parity gap as 2.5.x–3.2.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v3.2.0

#### Commit

The working-tree changes below (Anthropic models reach the desktop), on top of the v3.1.0 commit and its tracker note. A platform release: the gateway learns to translate between the OpenAI chat shape the desktop speaks and Anthropic's Messages API, so a Claude model the plan entitles is no longer filtered out of the desktop catalog. It reads no rows from the API clients differently: every existing path is untouched unless the operator turns the switch on. The desktop has no source change and is rebuilt for version parity only. The new setting `gateway_anthropic_desktop` is off by default and is the kill switch.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

Your Claude models reach the desktop, not just the API.

#### One-line summary

The v3.1.0 delivery panel made a long-standing gap visible: an Anthropic-driver provider's models were reported `driver_not_desktop` and stayed hidden from the app, because the desktop transport speaks the OpenAI chat shape and an Anthropic upstream does not. The gateway now translates in both directions — the request into an Anthropic Messages request, and the answer (including streamed deltas and tool calls) back into an OpenAI chat completion — so those models appear in the desktop like any other. The switch is off by default: with it off, the catalog, the diagnostic and every request behave exactly as they do today.

#### Platform changes

- **The translation lives in one testable place.** `app/Services/AnthropicCompatibility.php` holds the whole mapping as pure functions: `toMessagesRequest()` (system turn lifted out, `max_tokens` always present, `stop` → `stop_sequences`, OpenAI tools → Anthropic `tools` with an `input_schema`, `tool_choice` mapping including `none` withholding the tools, `tool_calls` → `tool_use` blocks, `tool` turns → `tool_result` blocks, base64 and remote images → Anthropic image sources), `toChatCompletion()` (text and `tool_use` blocks → `message.content` and `tool_calls`, `stop_reason` → `finish_reason`, token usage carried across), and `streamDelta()` (Anthropic's typed events → OpenAI `choices[].delta`, with `[DONE]` at `message_stop`).
- **The gateway uses it only where it must.** `translatesAnthropic()` gates every branch: `upstreamUrl()` switches the path to `/messages`, `upstreamPayload()` builds the Anthropic body instead of filtering the OpenAI one, `complete()` rewrites a successful answer back, and the stream relay feeds each upstream event through `streamDelta()` and emits the OpenAI deltas. `desktopDrivers()` adds `anthropic` to the catalog's driver list only while the setting is on, and `desktopExclusionReason()` follows it.
- **The switch is the kill switch.** `gateway_anthropic_desktop` (off) is a checkbox on the admin features screen next to the other gateway controls. Off restores today's behavior exactly: the models leave the catalog, the diagnostic reports `driver_not_desktop` again, and a `chat/completions` call for one of them is a 404 as before.
- **A small docs addition.** `/docs` gains a "One gateway, two API shapes" note explaining that the gateway speaks both shapes and that the desktop uses the OpenAI one, in English and Arabic.

#### Desktop / server changes

- No contract, enforcement or delivery change. `apps/server`, `packages/types` and the desktop config are untouched: the desktop already receives one OpenAI-compatible gateway provider and is unaffected by which upstream answers. The installer is rebuilt so its version stays in step.

#### App changes

- No app source change. The desktop build is re-run only for version parity: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `3.2.0`.

#### Verified before deploy

- Laravel: `488 tests / 2968 assertions` (Feature + Unit), Pint clean.
- New coverage: `AnthropicCompatibilityTest` (10 tests / 55 assertions) — the request mapping, `max_tokens` always present, tools and `tool_choice` (including `none` withholding them), a tool-result turn, a base64 image, the response mapping for text and tool calls, the stream delta mapping, and the events the client does not need being dropped. `AnthropicDesktopTest` (5 tests / 25 assertions) — with the setting off an Anthropic model is invisible to the desktop and a chat call for it is a 404; with it on the model is offered on `/v1/inference-providers`; a `chat/completions` call reaches the Anthropic `/messages` URL with an Anthropic-shaped body and returns an OpenAI-shaped answer; a streamed Anthropic answer is rewritten into OpenAI deltas ending in `[DONE]` with none of the Anthropic framing leaking to the client; and the delivery diagnostic stops reporting `driver_not_desktop`.
- The integration test caught a real defect while it was being written: the non-stream rewrite referenced a variable defined later in `complete()`, which would have failed every translated call. Fixed before commit.
- `CanonicalHostTest` updated to assert `3.2.0`.
- `node scripts/release/review.mjs` permits `3.2.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `3.2.0`:

- Built from the same tree: `build:electron rc=0`, `electron-builder rc=0`; artifact `idexal-os-setup-3.2.0-x64.exe`, **374,415,810 bytes**, sha256 `fb383f38bfbc79646f8b7472489998cd3e44351471b8da3cb5c25aeda83e8c45` (blockmap 378,607 B).
- Published to the release center and the public GitHub release: (to be filled after publish)

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `3.2.0`.
2. ⬜ No new migration this release; confirm nothing is pending and rebuild the config, route and view caches.
3. ⬜ Confirm the switch reads `false` and that the delivery panel still reports an Anthropic model as `driver_not_desktop` — the off state must be unchanged.
4. ⬜ Turn the switch on, confirm the Anthropic model appears in `/admin/gateway` delivery and on `/v1/inference-providers` for an entitled workspace, then make one real streamed and one non-streamed call and read both answers.
5. ⬜ Turn the switch off again and confirm the model disappears and a chat call for it returns 404.
6. ⬜ Publish the desktop installer `3.2.0` to the release center and the public GitHub release; `/v1/app-version` lists `3.2.0` first.
7. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- **The translation is verified against Anthropic's documented shapes, not a live Anthropic endpoint.** The unit and integration tests exercise the full mapping and the real gateway code path with a faked upstream, which is everything testable locally; the deploy checklist therefore includes one real streamed and one real non-streamed call *before* the switch is left on. Until that pass happens, the switch stays off.
- Prompt caching, extended thinking, and Anthropic's `top_k` are not mapped; the request simply does not ask for them. A model that requires them would answer without them rather than fail.
- The delivery panel diagnoses one workspace at a time; a platform-wide roll-up is still not built.
- `features:sync` regenerates the Helm chart; the Den API contract/SDK step still needs MySQL 8.4 (this machine runs MariaDB 10.4), so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration before the platform deploy — the same note as v2.9.0–v3.1.0.
- Same parity gap as 2.5.x–3.1.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v3.1.0

#### Commit

The working-tree changes below (why a model does or does not reach the desktop, and a required plan choice), on top of the v3.0.0 commit and its tracker note. A platform release: the gateway screen gains a **Desktop delivery** panel that names every gate that dropped a model, with the fix; and a new switch makes a new account choose a plan before the desktop opens. The desktop has no source change and is rebuilt for version parity only. No new feature flag beyond the one operator switch (`feature_require_plan_selection`), which is off by default so a deployment that has not adopted it behaves exactly as before.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

You can see why a model reaches the desktop — and require a plan first.

#### One-line summary

The desktop is served exactly one gateway provider, built from the models the organization's plan entitles and the gateway can actually reach. When that list came back empty a member saw no models at all and the operator had nothing to look at: a provider with no API key, a provider assigned to other plans only, an inactive provider and an Anthropic-driver provider all produced the same blank screen. The gateway screen now shows, per workspace, the plan it resolved, how many models reach the desktop, and every missing model with the gate that dropped it and the fix. A second switch lets the operator require every new account to choose a plan before the desktop opens.

#### Platform changes

- **The delivery panel.** `/admin/gateway` gains a "Desktop delivery" section: pick a workspace and see the plan it resolved and its subscription state, how many models reach the desktop out of every model on the platform, how many active providers the desktop transport can actually use, and the count of blocked models grouped by reason. Every model is listed with `shown` or its exclusion reason. The reasons are the ones `GatewayService::diagnoseDesktopCatalog()` already computes — `provider_without_credential`, `provider_plan_mismatch`, `model_plan_mismatch`, `driver_not_desktop`, `provider_inactive`, `model_inactive`, `combo_members_unavailable`, `duplicate_model_id` and the rest — each paired with the action that fixes it.
- **The two platform-wide traps are called out.** The panel warns plainly when there is no `free` plan (so a signup gets a workspace with no plan, and every provider assigned to a plan is hidden from it) and when every active provider is missing an API key (so no model can be served to anyone). These are the two ways a whole deployment silently serves nothing.
- **A plan is required when the operator says so.** A new switch on the admin features screen (`feature_require_plan_selection`, off by default) stops `OnboardingService` from granting the free plan automatically, and `MeController::desktopAccessGranted()` then reports the desktop as not granted for an organization with no active subscription — so the existing plan chooser holds the app until a plan is picked. Choosing any plan opens it immediately.
- **Why the models really were missing, in code.** The desktop list is `GatewayService::desktopGatewayModels()`: active model, active provider (platform or this organization), an OpenAI or OpenAI-compatible driver, the provider assigned to no plan or to this plan, the model assigned to no plan or to this plan, and a provider the gateway can authenticate. An Anthropic-driver provider never reaches the desktop, because the desktop transport speaks the OpenAI shape — that limitation is now visible instead of silent, and lifting it (an OpenAI↔Anthropic translation layer behind its own switch) is the next release, not this one.

#### Desktop / server changes

- No contract, enforcement or delivery change. `apps/server`, `packages/types` and the desktop config are untouched; the installer is rebuilt so its version stays in step.

#### App changes

- No app source change. The desktop build is re-run only for version parity: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `3.1.0`.

#### Verified before deploy

- Laravel: `473 tests / 2888 assertions` (Feature + Unit), Pint clean.
- New coverage: `AdminGatewayDeliveryTest` (5 tests / 15 assertions) — a keyless provider is reported as `provider_without_credential` with its fix; a provider assigned to another plan is reported as `provider_plan_mismatch`; an Anthropic-driver provider is reported as `driver_not_desktop`; a correctly configured provider is reported as shown; and a platform with no `free` plan shows the warning. `PlatformDesktopAccessTest` grows to 8 tests — requiring a plan leaves a new account without a subscription and reports `desktopAccess.granted: false`, and choosing a plan opens the desktop.
- A desktop-config probe against the real HTTP stack for a freshly-registered account confirmed the sync path itself is sound when the configuration is right: `org plan=free subs=1`, `/v1/me/desktop-config` 200 with `desktopAccess {required:true, granted:true, planSlug:"free"}`, `/v1/inference-providers` 200 with 1 provider and 3 models, `/v1/models` 200 with 7. So "no models" is a configuration gate, not a broken pipe — which is exactly what the new panel now makes visible.
- Live render check with a disposable operator (removed afterwards): `/admin/gateway` 200 with the delivery panel, the resolved plan, the blocked-model rows naming `provider_plan_mismatch` and `driver_not_desktop`, the fix text, and the full model table; `?lang=ar` 200 with `dir="rtl"` and the Arabic panel title.
- `CanonicalHostTest` updated to assert `3.1.0`.
- `node scripts/release/review.mjs` permits `3.1.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `3.1.0`:

- Built from the same tree: `build:electron rc=0`, `electron-builder rc=0`; artifact `idexal-os-setup-3.1.0-x64.exe`, **374,415,929 bytes**, sha256 `6b9da08f97f198ff77ced983ba35ea65fe724943cb7ba2654adda7237fa8b331` (blockmap 378,739 B).
- Published to the release center and the public GitHub release: (to be filled after publish)

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `3.1.0`.
2. ⬜ No new migration this release; confirm nothing is pending and rebuild the config, route and view caches.
3. ⬜ Open `/admin/gateway`, pick the workspace that reported no models, and read the reasons; apply the fix the panel names (assign the provider to the free plan, or add its key).
4. ⬜ Confirm a new signup then receives a provider and models on `/v1/inference-providers`.
5. ⬜ If the operator wants a required plan choice, turn on "Require every new account to choose a plan" and confirm a new signup lands on the plan chooser and opens after choosing.
6. ⬜ Publish the desktop installer `3.1.0` to the release center and the public GitHub release; `/v1/app-version` lists `3.1.0` first.
7. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- **Anthropic-driver models still do not reach the desktop.** The translation layer that would let the OpenAI-shaped desktop transport drive an Anthropic upstream is the next release, behind its own switch; until then the panel reports those models as `driver_not_desktop` rather than hiding the reason.
- The delivery panel diagnoses one workspace at a time; a platform-wide roll-up of every workspace that would serve no models is not built yet.
- `feature_require_plan_selection` needs at least one public plan to be safe: with none, a new account is held with no way forward. The switch's copy says so, and the delivery panel warns when no `free` plan exists.
- `features:sync` regenerates the Helm chart; the Den API contract/SDK step still needs MySQL 8.4 (this machine runs MariaDB 10.4), so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration before the platform deploy — the same note as v2.9.0 and v3.0.0.
- Same parity gap as 2.5.x–3.0.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v3.0.0

#### Commit

The working-tree changes below (the operator's full control over every account), on top of the v2.9.0 commit and its tracker note. A platform release: the admin Users screen becomes a complete console — detail pages, create, suspend/restore, verify, reset, revoke keys and sessions, bulk actions and CSV export — with the self-harm and last-admin guards built in. It also carries the v2.8.0/v2.9.0 desktop gate and provider entitlements, so the desktop installer is rebuilt for parity only. No feature flag is declared: this is an operator surface in the admin panel, not a product capability a member sees, so the kill switch is the deploy itself.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

The operator gets full control over every account.

#### One-line summary

The Users screen was a table with one inline edit form: no way to look at an account, nothing to create, no way to delete, and no control over the keys and devices an account holds. It is now a complete console. Each account has a page of its own (profile, workspaces and roles, API keys, desktop sessions, history) and an operator can create, suspend, restore, verify, reset, revoke and delete — one account at a time or many — while the guards that keep a platform from locking itself out (no self-harm, always one admin, no deleting a workspace owner) refuse the dangerous cases with a plain reason.

#### Platform changes

- **A detail page per account.** `GET /admin/users/{user}` shows the profile (id, phone, job title, language, timezone, created, last update), every workspace membership with its role and status, the account's API keys with abilities, expiry and last use, its desktop sessions with device, platform, address and last seen, and the last 40 audit entries that name it. Each section links to the workspace it belongs to.
- **Create an account, with or without a password.** `GET /admin/users/create` and `POST /admin/users` add a person directly: name, email, role, status, locale, timezone, phone, job title, an optional workspace membership with a role, and either a temporary password or a reset link. Creating an unverified account sends the reset link, which is the invite.
- **Suspend and restore, and the consequences are immediate.** `PATCH /admin/users/{user}/status` flips the status and, on suspend, revokes every API key and desktop session in the same action, so a suspended account cannot keep working through a device it already paired. The detail page offers the same through its action panel.
- **Grants an operator can make by hand.** `POST /admin/users/{user}/verify` marks the email verified without the person signing in; `POST /admin/users/{user}/reset-password` sends a reset link; `POST /admin/users/{user}/revoke-keys` and `.../revoke-sessions` revoke the account's keys or sign it out of every desktop, on their own, without suspending it.
- **The list, properly queryable.** `GET /admin/users` now reports six counts (total, active, suspended, admins, unverified, new this month) and filters by search (name, email or phone), role, status, verification, workspace and activity (has keys / has sessions / neither), with a sort order. `GET /admin/users/export` streams the filtered set as CSV.
- **Bulk actions.** `POST /admin/users/bulk` activates, suspends, verifies, resets or deletes a selected set in one request, reports how many changed, and names how many were skipped and why. Every action writes an audit entry.
- **The guards.** An operator cannot suspend, demote or delete their own account; the last remaining admin cannot be demoted, suspended or deleted; and an account that still owns a workspace cannot be deleted until ownership moves. Each refusal is a plain sentence on the screen, not a crash.

#### Desktop / server changes

- No contract, enforcement or delivery change: this is admin-panel work on the platform. `apps/server`, `packages/types` and the desktop config are untouched; the installer is rebuilt so its version stays in step with the platform.

#### App changes

- No app source change. The desktop build is re-run only for version parity: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `3.0.0`.

#### Verified before deploy

- Laravel: `464 tests / 2861 assertions` (Feature + Unit), Pint clean.
- New coverage: `AdminUsersTest` (20 tests / 76 assertions) — the index lists users and reports stats; filters by status and search; the detail page renders profile, workspaces and activity; an operator can create a user (with password hashed) and can add a workspace membership in the same step; suspending revokes keys and sessions; keys and sessions can be revoked on their own; an email can be verified by hand; a profile update saves name, role, locale and timezone; changing the email clears verification; a user can be deleted (soft delete); an operator cannot delete their own account; the last admin cannot be demoted or deleted; a workspace owner cannot be deleted; bulk suspend skips the operator's own account; bulk activate restores a suspended user; the CSV export returns the filtered users with the expected header; a non-admin is refused on every route; the create form renders; and the console renders in Arabic with the Arabic labels.
- `CanonicalHostTest` updated to assert `3.0.0`.
- Live render check against the real HTTP stack with a signed-in operator (disposable accounts, removed afterwards): `/admin/users` 200 with the six counts, the member row, the bulk control, the export and the New user action; an account's detail page 200 with profile, workspaces and roles, activity and the action panel; `/admin/users/create` 200 with the temporary-password and membership fields; `/admin/users/export` 200 `text/csv; charset=UTF-8` with the expected header; the filters (`verified=no&sort=name`, `activity=none`) 200; and the list **and** the detail page render in Arabic with `dir="rtl"` and Arabic labels. This check found a real defect the suite had missed — the export closure lazy-loaded `memberships` while the app forbids lazy loading in production, so the download returned 500 live — now fixed, with a regression test that enables `Model::preventLazyLoading()`.
- `node scripts/release/review.mjs` permits `3.0.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `3.0.0`:

- Built from the same tree: `build:electron rc=0`, `electron-builder rc=0`; artifact `idexal-os-setup-3.0.0-x64.exe`, **374,415,931 bytes**, sha256 `6165c0c278694a479b0d4757f58a0f2db5d6d63681b7fabfae8965e2ad605f00` (blockmap 378,774 B).
- Published to the release center and the public GitHub release: (to be filled after publish)

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `3.0.0`.
2. ⬜ No new migration this release; confirm nothing is pending and rebuild the config, route and view caches.
3. ⬜ Verify `/admin/users` reports the six counts, that the filters narrow the list, and that the CSV export streams `text/csv`.
4. ⬜ Open an account's detail page and confirm its workspaces, keys, sessions and history render; then suspend a test account and confirm its keys and sessions are revoked.
5. ⬜ Confirm the guards: the last admin cannot be demoted, the operator cannot delete their own account, and a workspace owner cannot be deleted.
6. ⬜ Publish the desktop installer `3.0.0` to the release center and the public GitHub release; `/v1/app-version` lists `3.0.0` first.
7. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- The detail page loads a bound slice of history (40 audit entries, 50 keys, 50 sessions) rather than paginating; a very active account shows its most recent activity only.
- Impersonation ("sign in as this user") is deliberately not offered: it needs a dedicated, audited handoff that cannot be faked, and it is a follow-up, not a checkbox on this screen.
- The CSV export streams the filtered set without a row cap; on a very large deployment that is a long response rather than a background job.
- `features:sync` regenerates the Helm chart; the Den API contract/SDK step still needs MySQL 8.4 (this machine runs MariaDB 10.4), so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration before the platform deploy — the same note as v2.9.0.
- Same parity gap as 2.5.x–2.9.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v2.9.0

#### Commit

The working-tree changes below (the operator controls every provider and every key), on top of the v2.8.0 commit and its tracker note. A platform + desktop release: the AI gateway's provider set and the members' own-key (BYOK) surface become one operator decision, delivered to the desktop as a `providerEntitlements` block, with a workspace-level kill switch on the admin organization screen. It also adds the public **Models & providers** page and a system-requirements + install-steps panel to `/download`. No feature flag is declared: the provider controls are plan and organization settings that the operator already owns (the plan↔provider pivots, `gateway_enabled`, and the existing `custom_providers` plan feature), so the kill switches are those settings themselves.

#### Released at

`2026-10-29` — see Deploy evidence below.

#### Title

You control every provider, and every key, from one screen.

#### One-line summary

Which models a member can use was previously implied by whatever the gateway happened to serve; a member could also add their own keys and bypass the plan entirely. Now the operator owns it end to end: a workspace-level switch forces everyone onto the plan, whose own keys stop being delivered to every connected desktop; any plan can still opt back in for an exception; and the desktop is told exactly which providers the plan covers and whether own keys are allowed, so it shows the same decision the gateway enforces.

#### Platform changes

- **One payload the desktop can trust.** `ProviderEntitlementService` computes `providerEntitlements` — `{ enabled, byok, plan, providers[] }` — and `MeController::desktopConfig()` delivers it on `GET /v1/me/desktop-config`. A provider with no plan rows serves every plan (the platform's existing rule), a provider assigned to a plan is listed only there, and an inactive provider is never listed. `enabled` follows `gateway_enabled`; when the gateway is off no provider is advertised at all.
- **One switch forces the plan.** A new action on the admin organization screen (`PATCH /admin/organizations/{organization}/byok`, `admin.organizations.byok`) writes `byok_killed` into the organization's settings. While it is on, `MeController` returns `allowCustomProviders: false`, which is the key the desktop already uses to hide the entire "add your own key" surface, and `providerEntitlements.byok` is `false`. The decision reaches connected desktops through `DesktopSync::announce('organization_byok')` and the five-minute config refresh, without a relaunch.
- **A plan can still opt back in.** A plan that explicitly sets `custom_providers` to `true` overrides the workspace kill switch, so a special plan can keep BYOK while everyone else is on plans only. A plan that denies `custom_providers` denies it even when the switch is off.
- **Every provider and its plan assignment stay one screen.** The existing `/admin/providers` (per-provider active toggle, key management, backup chain) and `/admin/plans` (per-plan provider and model assignment) are unchanged; the reference `custom_providers` and `desktop_access` features now render on the plan form so an operator can grant either without a code change.
- **A public Models & providers page.** `/providers` is generated from the active providers and models the platform actually runs, grouped by the plans that can use each one, with structured copy about BYOK. It is in `routes/web.php`, `SitemapController`, the desktop nav, the mobile nav and the footer, and it is localized in English and Arabic. It cannot advertise a provider the platform does not run, because it reads the same rows the gateway serves.

#### Desktop / server changes

- **The contract carries the decision.** `desktopProviderEntitlementSchema` / `desktopProviderEntitlementsSchema` (`DenProviderEntitlement`, `DenProviderEntitlements`) are added to `desktopConfigSchema` and threaded through `normalizeDesktopConfig()`, so a platform from before this field still parses — a missing block means "not told", and the desktop does not filter.
- **The client reads one hook.** `useProviderEntitlements()` in `desktop-config-provider.tsx` exposes `{ byok, enabled, plan, providers, known }` with `byok` defaulting to `true` when the block is missing.
- **No new enforcement in `apps/server`:** the gateway already refuses models from providers a plan does not cover, and the BYOK surface was already governed by `allowCustomProviders`; this release makes the operator's decision authoritative and visible.

#### App changes

- The desktop now shows the provider decision the platform made: the `providerEntitlements` block is fetched with the rest of the desktop config, cached per organization, and refreshed on session changes and the five-minute interval.
- When the workspace forces the plan, the "add your own key" entry points disappear on every surface that already gates on `allowCustomProviders` (model picker, provider settings, connection setup, session hero, control actions) — no new UI, one authoritative flag.
- Version parity in every other app surface: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `2.9.0`.

#### Verified before deploy

- Laravel: `445 tests / 2789 assertions` (Feature + Unit), Pint clean.
- New coverage: `ProviderEntitlementTest` (6 tests / 29 assertions) — the block lists only the providers the plan covers (a provider with no plan rows serves every plan, one assigned to another plan is not listed, an inactive one is never listed); BYOK is allowed by default and denied by the workspace kill switch (which also turns `allowCustomProviders` off); a plan can opt back in while the switch is on; a plan that denies BYOK denies it with the switch off; the gateway switch hides every provider; and the admin kill switch is reachable only by an operator and flips the delivered block. `PublicProvidersPageTest` (3 tests / 14 assertions) — the page lists active providers, their models and their plans in English, serves Arabic with `dir="rtl"`, and is in the sitemap.
- `CanonicalHostTest` updated to assert `2.9.0`.
- App: `pnpm typecheck` exit `0`; the gate and i18n suites pass; `node scripts/i18n-audit.mjs --plurals` all locales complete.
- New: `apps/desktop/test/desktop-distribution.test.mjs` (5 tests) — every shipped flavor requires sign-in, an unflavoured packaged build resolves to the public flavor *and* requires sign-in, only enterprise requires activation, an unactivated enterprise build is held while a stamp releases it, and the public and cloud flavors are never activation-gated. This is the build-level half of the "no anonymous use" promise; the routing half is the existing `den-signin-routing.test.ts` (`requireSignin: true` + signed out → `/signin`), and the UI half is the existing full-screen `ForcedSigninPage`.
- Shipped-artifact verification of the built installer `2.9.0` (read back from `win-unpacked/resources`): the bundled `desktop-distribution.mjs` inside `app.asar` reads `PUBLIC_DESKTOP_DISTRIBUTION = Object.freeze({ flavor: "public", … requireSignin: true, requireActivation: false })`, the renderer's own distribution fallback also requires sign-in, and the renderer bundle carries the plan-required screen (`den.plan_required_title` ×3, `plan_required_cta` ×3, the `plan-required-gate` test id) and the `providerEntitlements` keys (×4). The gate and the entitlement block are in the artifact that would be published, not only in the source.
- New: `apps/app/tests/desktop-entitlements.test.ts` (8 tests) — `desktopAccess` and `providerEntitlements` normalize permissively when the platform omits them (a platform from before the field never locks a user out), carry the explicit decision when present, and drop a malformed block instead of throwing.
- `node scripts/release/review.mjs` permits `2.9.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `2.9.0`:

- Built from the same tree: `build:electron rc=0`, `electron-builder rc=0`; artifact `idexal-os-setup-2.9.0-x64.exe`, **374,415,828 bytes**, sha256 `b33738a88763b2fb6b8437c427e47be8791c68d39e8714445dd79fe49a45cef9` (blockmap 378,704 B).
- Published to the release center and the public GitHub release: (to be filled after publish)

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `2.9.0`.
2. ⬜ No new migration this release (`byok_killed` is an `organizations.settings` JSON entry, and `custom_providers` / `desktop_access` are `plans.features` entries); confirm nothing is pending and rebuild the config, route and view caches.
3. ⬜ Verify `GET /v1/me/desktop-config` carries `providerEntitlements` and that `allowCustomProviders` follows the plan and the workspace switch.
4. ⬜ Verify `/providers` renders in both languages, links from the nav and footer, and appears in `/sitemap.xml`.
5. ⬜ Publish the desktop installer `2.9.0` to the release center and the public GitHub release; `/v1/app-version` lists `2.9.0` first.
6. ⬜ Toggle the workspace BYOK switch on a test workspace and confirm the "add your own key" entry points disappear on a connected desktop without a relaunch.
7. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- The admin provider console is the existing `/admin/providers` and `/admin/plans` screens plus the new workspace BYOK switch; a single combined "providers console" page that shows provider, plans and BYOK in one place is a later polish pass, not a capability gap.
- `byok_killed` is stored in `organizations.settings` (JSON), the same place the proxy and inference settings already live; it is not a column, so it is not indexed or queryable in bulk.
- The public providers page shows the platform's providers; it does not yet show a per-model price or a usage example.
- `features:sync` regenerates the Helm chart (done) and the Den API contract/SDK; the contract step needs MySQL 8.4 and this machine runs MariaDB 10.4, so `packages/docs/openapi.json` and `packages/sdk/src/gen/**` still need a regeneration on a machine with the right MySQL before the platform deploy.
- Same parity gap as 2.5.x–2.8.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v2.8.0

#### Commit

The working-tree changes below (the desktop app now requires a plan, and the operator can lock every provider on or off), on top of the v2.7.0 commit and its tracker note. A platform + desktop release that closes the "use it anonymously" hole: the public installer forces sign-in on every launch, a plan-level gate blocks the shell for a signed-in user whose plan does not grant desktop access, and a new operator switch plus a `desktopAccess` entitlement block carry that decision to the desktop. The provider hard-filter and the full admin console ship in 2.9.0; this release lands the enforcement surface they build on. No feature flag is declared for the sign-in requirement itself — that is the product baseline, not a rollout — but the plan-level gate ships behind `accountRequiredForDesktopAccess` (off by default), so a deployment that has not enabled it treats every plan as granting access and nothing changes.

#### Released at

`2026-10-28` — see Deploy evidence below.

#### Title

The desktop app requires a plan, and you pick it before the app opens.

#### One-line summary

Installing the desktop app used to open straight into a workspace you could use without an account, which is the opposite of the product we are building: the desktop is a client of the platform, not a standalone tool. Now the public installer forces a sign-in on every launch (the existing full-screen "Welcome to idexal Os" gate, with sign-in / create-account / browser handoff), and a signed-in user whose organization's plan does not grant desktop access is shown a "pick a plan" screen instead of their workspaces — the app opens the moment their plan grants it, with no relaunch. An operator flips the plan requirement on for the whole deployment in one click; while it is off, every plan grants access and nothing changes.

#### Platform changes

- **The plan entitlement is a real block, not a notice.** `Plan` gains `FEATURE_DESKTOP_ACCESS` (`desktop_access`): a plan that sets it to `false` denies app access; a plan that has never been asked about the key (created before the gate) still grants, so no existing plan silently locks its users out. `MeController::desktopConfig()` delivers a `desktopAccess` block — `{ required: true, granted, planSlug }` — and `granted` is computed by `desktopAccessGranted()`, which reads the new `DesktopSwitch::AccountRequiredForDesktopAccess` switch: while the switch is off, every plan grants; while it is on, access follows the plan.
- **The operator switch is a kill switch with a rollback.** Declared in the feature registry as `accountRequiredForDesktopAccess` (cloud and self-hosted, default off), rendered on the `/admin/features` desktop panel with an English and Arabic label, and published through the same `PlatformFeatures::map()` that the desktop reads before sign-in — so the desktop's gate can read the decision even when it cannot yet sign in.
- **A public record of the release.** The new entry is in `config/releases.php` and in both `lang/{en,ar}/changelog.php` under `2_8_0`, so `/changelog` lists it in both languages with a permalink.

#### Desktop / server changes

- **The contract carries the decision.** `desktopAccessSchema` (`DenDesktopAccess`: `required`, `granted`, `planSlug`) is added to `desktopConfigSchema` in `packages/types/src/den/desktop-policies.ts` and threaded through `normalizeDesktopConfig()`, so a platform from before the field still parses — a missing block means "no restriction".
- **No enforcement change in `apps/server`:** the gate is an identity decision, not a request decision, so the connector permission engine and the gateway are untouched.

#### App changes

- **Sign-in is mandatory on the public installer.** `PUBLIC_DESKTOP_DISTRIBUTION.requireSignin` is now `true` (cloud and enterprise were already `true`), and the renderer fallback in `apps/app/src/app/lib/desktop.ts` matches. `main.mjs`'s `FORCE_DESKTOP_REQUIRE_SIGNIN` reads the distribution, so every public build now holds the UI at the existing full-screen gate until a platform session exists. The "use without an account" first-launch workspace is no longer provisioned, because a public install is now always sign-in-required.
- **A "pick a plan" screen.** `PlanRequiredGate` (new, `domains/cloud/plan-required-gate.tsx`) renders between the sign-in gate and the web-access gate in `AppRoot`: when `desktopAccess.required` is `true` and `granted` is `false` it blocks the shell and offers "Pick a plan" (opens the platform's billing page), "Check again" (re-fetches the config and re-checks the session, so a plan the operator just assigned shows up without a relaunch) and "Sign out". When the block is absent or `granted` is `true` it renders its children unchanged.
- **The gate reads one hook.** `useDesktopAccessGrant()` in `desktop-config-provider.tsx` exposes `{ required, granted, planSlug }` with `granted` defaulting to `true` when the block is missing, so a deployment that has not enabled the switch never shows the screen.
- **Copy is honest in both languages.** The "pick a plan" strings are in `en` and `ar`; the app says the web dashboard is still usable while the desktop is locked, and names the plan that is missing the grant.
- **Version parity** in every other app surface: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `2.8.0`.

#### Verified before deploy

- Laravel: `436 tests / 2746 assertions` (Feature + Unit), Pint clean.
- New coverage: `PlatformDesktopAccessTest` (6 tests / 19 assertions) — the `desktopAccess` block is delivered while the switch is off with every plan granting; a plan created before the gate still grants while the switch is on; a plan that explicitly denies blocks it; a plan that grants allows it; no active plan denies it; and turning the switch off restores universal access (the kill-switch rollback).
- `CanonicalHostTest` and `PublicChangelogTest` updated to assert `2.8.0`.
- App: `bun test tests/plan-required-gate.test.tsx` — 6 pass (children render when the block is absent / granted / not required, and the pick-a-plan screen renders with the plan named, with an empty slug, and with sign-out + retry actions); the i18n placeholder-parity and den-signin-routing suites pass with the new keys; `pnpm typecheck` exit `0`; `node scripts/i18n-audit.mjs --plurals` all locales complete.
- New: `apps/app/tests/forced-signin-surface.test.tsx` (5 tests) — the gate's first screen welcomes the person, offers exactly one way in, offers the paste-a-code fallback, surfaces a sign-in error instead of continuing silently, and carries **no** skip, guest or offline-only affordance (asserted by scanning the rendered markup for those phrases). Together with the build-level `desktop-distribution.test.mjs` and the routing test, all three layers of "no anonymous use" are proven.
- Desktop installer `2.8.0`: `build:electron rc=0`, `electron-builder rc=0`; artifact `idexal-os-setup-2.8.0-x64.exe`, **374,416,144 bytes**, sha256 `0295621c2adfb1842afb351c933b8ce0df91133e4226ddf74edbdc62b46e468f` (blockmap 378,873 B).
- `node scripts/release/review.mjs` permits `2.8.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `2.8.0`:

- (to be filled after publish)

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `2.8.0`.
2. ⬜ No new migration this release (the `desktop_access` key is a `plans.features` JSON entry, not a column); confirm nothing is pending and rebuild the config, route and view caches.
3. ⬜ Verify `GET /v1/me/desktop-config` carries the `desktopAccess` block and that `PlatformFeatures` publishes `accountRequiredForDesktopAccess`; the switch reads `false` until the operator turns it on.
4. ⬜ Verify `/changelog` lists the 2.8.0 entry in both languages with its permalink.
5. ⬜ Publish the desktop installer `2.8.0` to the release center and the public GitHub release; `/v1/app-version` lists `2.8.0` first.
6. ⬜ Verify a public-flavor install opens the sign-in gate (no anonymous workspace), and that a signed-in user on a plan without `desktop_access` sees the pick-a-plan screen while the switch is on.
7. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- The `accountRequiredForDesktopAccess` switch ships **off**: every deployment that has not enabled it still lets every signed-in plan open the app. The sign-in requirement is unconditional, the plan requirement is opt-in. Turning it on is an operator action, not a deploy.
- The `DenDesktopAccess` key is a new field on the shared desktop-config contract, so a platform from before this release answers without it and the desktop treats that as "no restriction" — the gate is safe to ship against older servers.
- The provider hard-filter and the full admin provider console are 2.9.0; this release only lands the enforcement surface (`desktopAccess` delivery, the operator switch, the two gate screens) they build on.
- Same parity gap as 2.5.x–2.7.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v2.7.0

#### Commit

The working-tree changes below (your language follows every link, and the desktop app speaks Arabic), on top of the v2.6.2 commit and its tracker note. Website plus desktop release: the app gains a new locale and a right-to-left document direction, and the platform gains a public changelog, language-preserving links and a lighter home page. No feature flag is declared: a locale is not a capability that can be rolled out per organization, and every website change here is either copy or markup, so the kill switch is the deploy itself.

#### Released at

`2026-10-28` — see Deploy evidence below.

#### Title

Your language follows every link, and the desktop app speaks Arabic.

#### One-line summary

2.6.0 promised that "a language is a URL", but the links inside the site were still built with plain `route()`: a visitor reading Arabic who clicked *Pricing* landed on an address without `?lang=ar`, and the page they copied or bookmarked from there was English. Now every internal link carries the language, the footer says "All rights reserved." instead of "All Terms of Service", and a new `/changelog` page records each release in both languages. The desktop app adds العربية with a right-to-left document direction — the everyday workspace is translated, everything else falls back to English — and the home page plays the product in motion as a 288 KB video instead of a 1.5 MB GIF.

#### Platform changes

- **Every internal link keeps the language.** A new `localized_route()` helper in `app/helpers.php` appends `?lang=` to a named route when the current language is not the default (the default keeps the bare, canonical URL, and an existing fragment is preserved). Every public view and layout — header, mobile menu, footer, hero, pricing, docs, download, security, contact, legal, changelog — now builds its links with it instead of `route()`, so the address bar always describes the page the visitor is reading. The pricing monthly/yearly toggle is localized the same way.
- **A public record of every release.** `config/releases.php` holds the ordered versions and dates; the prose lives in `lang/en/changelog.php` and `lang/ar/changelog.php`, keyed by version with dots replaced by underscores because Laravel reads a dot as nesting. `ChangelogController` resolves those keys, `resources/views/public/changelog.blade.php` renders each release with a permalink anchor (`#v2.7.0`), a "Current release" badge on the newest one and a localized `aria-label` on the permalink, and `/changelog` is in `routes/web.php`, in `SitemapController` and in the nav as *What's new*.
- **The footer says what it means.** `ui.common.rights` replaced the wrong key that rendered "All Terms of Service"; the footer now reads "All rights reserved." / "جميع الحقوق محفوظة".
- **A lighter home page.** The product demo is a `<video>` (webm 199 KB / mp4 288 KB) with a 24 KB poster instead of a 1,564,937-byte GIF — about 5× less to download. It carries `preload="none"`, plays only while it is on screen (an `IntersectionObserver` in `resources/js/app.js`) and stays on the poster frame when the visitor prefers reduced motion. The dead `public/brand/app-demo.gif` was removed from the site.
- **Typefaces start loading with the HTML.** The Bunny Fonts stylesheet moved from a CSS `@import` to a `<link>` plus `<link rel="preconnect">` in `partials/head.blade.php`, so the connection opens while the document is parsed instead of after the stylesheet arrives.
- **Honest copy about Arabic.** The feature card, the FAQ answer and the desktop body no longer claim "full Arabic support"; both languages now say Arabic covers the everyday workspace with English as the fallback.
- Localized in English and Arabic.

#### Desktop / server changes

- **The app ships an Arabic catalogue.** `apps/app/src/i18n/locales/ar.ts` holds 545 keys (21% of the 2,575-key English catalogue) with all six CLDR plural forms complete; `apps/app/src/i18n/index.ts` registers `ar` in `Language`, `LANGUAGES`, `LANGUAGE_OPTIONS`, `TRANSLATIONS`, the plural rules and the empty-suffix list.
- **The document direction follows the language.** New `textDirection()` and `applyDocumentLocale()` set `lang` and `dir` on `document.documentElement` from `setLocale()` and `initLocale()`, so choosing العربية flips the whole interface to right-to-left immediately and choosing English restores `ltr`.
- **The language is offered where a person chooses one.** `ar` is in the desktop control actions' `APP_LANGUAGES`, so the settings surface lists العربية.
- **The guards know about the new locale.** `scripts/i18n-audit.mjs` audits `ar`, and `apps/app/tests/i18n-placeholder-parity.test.ts` includes it, so a translation that drops an interpolation is caught in CI.
- No Den contract, enforcement or delivery change: `packages/types`, `packages/sdk` and `apps/server` are untouched, and `pnpm den:contract` was not needed.

#### App changes

- Arabic (`العربية`) in Settings › Appearance, with a right-to-left layout for the whole shell.
- The untranslated remainder renders in English rather than as a missing key, so a partly translated locale is usable instead of broken.
- Version parity only in every other app surface: `apps/app`, `apps/desktop`, `apps/desktop/server` and `apps/server` all read `2.7.0`.

#### Verified before deploy

- Laravel: `430 tests / 2719 assertions` (Feature + Unit), Pint clean.
- New coverage: `PublicChangelogTest` (3 tests) — `/changelog` renders every release from `config/releases.php` with the current one marked and a permalink anchor, `?lang=ar` serves the Arabic prose with `dir="rtl"`, and the page is in the sitemap with both languages. `PublicSiteLanguageLinksTest` (4 tests) — internal links on every public page carry `?lang=ar` when Arabic is active, the default language keeps the bare canonical URL, the pricing toggle keeps both the interval and the language, and the footer states the rights in the visitor's language.
- `CanonicalHostTest` updated to assert `2.7.0`.
- App: `bun test tests/i18n-placeholder-parity.test.ts` and the i18n key suite pass with `ar` included; `node scripts/i18n-audit.mjs --plurals` reports all six Arabic forms complete; `pnpm typecheck` exit `0`.
- `npm run build` clean (`app-BMKsg2o1.js` 259.38 kB, `app-DaYNzuq0.css` 108.42 kB); `node scripts/release/review.mjs` permits `2.7.0`.
- Manual check on `php artisan serve`: `/changelog` lists four releases and reads `right-to-left`; `?lang=ar` serves `lang="ar" dir="rtl"` with Arabic titles and "جميع الحقوق محفوظة"; `?lang=en` restores LTR; the home page video decodes at 800×556 for 15 s (webm chosen, `readyState` 4) and no public page scrolls horizontally.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `2.7.0`:

- (to be filled after publish)

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `2.7.0`.
2. ⬜ No new migration this release; confirm nothing is pending and rebuild the config, route and view caches.
3. ⬜ Verify `/changelog` renders every release in both languages with the permalink anchors, and that it appears in `/sitemap.xml`.
4. ⬜ Verify an internal link clicked from `/?lang=ar` keeps `?lang=ar`, and that the footer reads "All rights reserved." / "جميع الحقوق محفوظة".
5. ⬜ Verify the home page serves the video and poster with `200` and that `brand/app-demo.gif` is gone.
6. ⬜ Publish the desktop installer `2.7.0` to the release center and the public GitHub release; `/v1/app-version` lists `2.7.0` first.
7. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- Arabic is a working partial translation, not a complete one: 545 of 2,575 keys (21%), so most screens still show English. It is described that way on the site and in the changelog, and the untranslated remainder falls back cleanly. Completing it is the next language pass, not a claim this release makes.
- The changelog prose is written per release in two language files rather than generated from commits, so publishing a release still means editing `lang/en/changelog.php` and `lang/ar/changelog.php` by hand.
- `public/brand/app-demo.gif` still exists at the repository root because `translated_readmes/README_JA.md` embeds it; only the website copy was removed.
- Same parity gap as 2.5.x–2.6.2: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v2.6.2

#### Commit

The working-tree changes below (the public site fits every screen), on top of the v2.6.1 commit and its tracker note. Website release: the desktop installer is rebuilt only so its version stays in step, with no app or server change. No feature flag is declared: this is a layout correction on the public pages, not a product capability, so the kill switch is the deploy itself.

#### Released at

`2026-10-28` — see Deploy evidence below.

#### Title

The public site fits every screen you open it on.

#### One-line summary

The two-column layout grids that carry code and long URLs (the home hero, the home gateway section, the docs and legal layouts, the security split, the home FAQ and the footer) let their columns refuse to shrink below their content's intrinsic width. On a phone that pushed the hero's paragraph and its "Explore the docs" button about 24 px past the right edge, where the hero's `overflow-hidden` cut them off, and it made `/docs` about 264 px wider than a 390 px screen — a horizontal scrollbar on the API reference. Adding `min-width: 0` to those grid children lets each column shrink and lets the inner code scrollers scroll instead, so every public page now fits at every width from 320 px up.

#### Platform changes

- **Layout grid columns can shrink.** `min-w-0` was added to the children of the public two-column layout grids: the homepage hero and gateway columns, the homepage FAQ columns, the docs sidebar and content column, the legal sidebar and article, the security split, and the footer brand and link columns. Grid items default to a minimum size of `auto` (their content's intrinsic width), so a column holding a `pre`/long URL refused to shrink below it; `min-w-0` removes that floor and lets the existing `overflow-x-auto` code blocks scroll within their card.
- No markup, route, config, translation or behavioural change; the fix is one Tailwind utility on grid children.

#### Desktop / server changes

- No contract, enforcement or delivery change. `apps/server` and the desktop config are untouched; the installer is rebuilt so its version matches the platform.

#### App changes

- No app source change. The desktop build is re-run only for version parity.

#### Verified before deploy

- Laravel: `423 tests / 2685 assertions` (Feature + Unit), Pint clean.
- Headless layout measurement at `320`, `390`, `414`, `768`, `1024` and `1440` px across all ten public pages, before and after: `main.scrollWidth` exceeded `main.clientWidth` on `/` (by 42 px at 320, 17 elements past the viewport at 390) and on `/docs` (by 334 px at 320 and 264 px at 390). After the fix every page reports `main.scrollWidth == main.clientWidth` and no element with real content juts past the viewport; the only remaining sub-pixel outlier (2 px at 320) sits inside a `pre` that already scrolls. Screenshots captured at 320 px (home hero wraps, no clipped text) and 390 px (`/docs` fits, no horizontal scroll).
- `node scripts/release/review.mjs` permits `2.6.2`.

#### Deploy evidence

Platform deploy to `os.idexal.com`:

- (to be filled after deploy)

Desktop installer `2.6.2`:

- (to be filled after publish)

Post-deploy monitoring:

- (to be filled after publish)

#### Deploy checklist

1. ⬜ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `2.6.2`.
2. ⬜ No new migration this release; confirm nothing is pending and the config, route and view caches are rebuilt.
3. ⬜ Verify the built stylesheet still contains `min-w-0` and the home hero, gateway, FAQ, docs, legal, security and footer layout grids carry it on their children.
4. ⬜ Re-run the width sweep over the live pages and confirm no public page scrolls horizontally at 320–1440 px.
5. ⬜ Publish the desktop installer `2.6.2` to the release center and the public GitHub release; `/v1/app-version` lists `2.6.2` first.
6. ⬜ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- The guard for this class of bug is a manual width sweep, not an automated visual regression: PHP tests cannot measure layout, so a future grid can reintroduce the floor without a red test.
- Same parity gap as 2.5.x–2.6.1: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v2.6.1

#### Commit

The working-tree changes below (the language a visitor chooses is the language they share), on top of the v2.6.0 commit and its tracker note. Website release: the desktop installer is rebuilt only so its version stays in step, with no app or server change. No feature flag is declared: this completes the public site's language handling and accessibility, not a product capability, so the kill switch is the deploy itself.

#### Released at

`2026-10-28` — see Deploy evidence below.

#### Title

The language you choose is the language you share.

#### One-line summary

2.6.0 promised that "a language is a URL", but the switch in the page header still pointed at the old session-based `/locale/{lang}` route: the URL never changed, so a visitor who switched to Arabic and copied the link handed the next person an English page, and a signed-in member's choice did not follow them to another device. Now the switcher writes an explicit `?lang=` into the link it points at, a signed-in member's language is saved to their account, each language gets its own share card, and the public chrome carries the landmarks a global audience and assistive technology expect — a skip link, a labelled `<nav>`, `aria-current` and a real `<main>` target.

#### Platform changes

- **The switch is a link you can share.** Both the desktop and the mobile language switches now build their target with `locale_url($locale)`, so they always carry an explicit `?lang=en|ar` and the address bar reflects the chosen language. The bare URL still maps to the default (English) for the canonical and `hreflang`, but the switch never relies on a remembered session, so a shared or copied link keeps its language. The links also carry `hreflang`/`lang` so crawlers and screen readers know what language the target is in.
- **A member's language follows them.** When a signed-in member chooses a language, `SetLocale` now writes it to `users.locale` (`forceFill`, and only when it actually changes), so the choice survives a new device instead of living only in the session. Guests are unaffected and the session still carries the choice for the visit.
- **Accessibility landmarks on every public page.** A "Skip to content" link, visually hidden until focused, targets `<main id="main" tabindex="-1">`; the header and the mobile menu are real `<nav>` landmarks with a localized `aria-label` (`ui.nav.primary`); the current page's nav link carries `aria-current="page"`; and the mobile menu button is wired to `aria-controls="site-menu"`.
- **Two more FAQ answers.** "Which languages are supported?" and "Can I use it from anywhere?" now render on the home page and are included in the home `FAQPage` JSON-LD, so the answers to the two questions a global visitor actually asks are both visible and machine-readable.
- Localized in English and Arabic.

#### Desktop / server changes

- No contract, enforcement or delivery change. `apps/server` and the desktop config are untouched; the installer is rebuilt so its version matches the platform.

#### App changes

- No app source change. The desktop build is re-run only for version parity.

#### Verified before deploy

- Laravel: `423 tests / 2685 assertions` (Feature + Unit), Pint clean.
- New coverage: `PublicSiteLanguageSwitchTest` (5 tests) — the switcher carries an explicit `?lang=`; `?lang=ar` persists to `users.locale` for a signed-in member and to the session for a guest; the accessibility landmarks and the localized navigation label are present. Together with `PublicSiteSeoTest` that is `11 tests / 73 assertions`, all green.
- `node scripts/release/review.mjs` permits `2.6.1`.

#### Deploy evidence

Platform deploy to `os.idexal.com` (commit `9a1cca3`):

- `deploy-2.6.1.sh` (full DB + code backup → sparse clone → `rsync` into `/var/www/vhosts/idexal.com/os.idexal.com-app` → `npm ci` + asset build → optimized autoload → recache) ran green: `rsync_rc=0`, `npmci_rc=0`, `build_rc=0`, `dump_rc=0`, `migrate_rc=0` with `Nothing to migrate` and no pending migration before or after.
- `config('idexal.version')` reads `2.6.1`; `GET /v1/health` returns `{"ok":true,...,"version":"2.6.1"}`; `locales.default` = `en` and `locales.supported` = `en,ar`.
- Both share cards are in place: `brand/og-default.png` 88,261 B and `brand/og-ar.png` 88,762 B; `robots.txt` still carries the `Sitemap:` line; the connector permission switch is still `false`.
- All ten public pages return `200`.
- The header switch now links to an explicit language: the home document contains `href="https://os.idexal.com?lang=ar"`, and `/pricing?lang=ar` serves `<html lang="ar" dir="rtl">` with `<link rel="canonical" href="https://os.idexal.com/pricing?lang=ar">` and a switch back at `href="https://os.idexal.com/pricing?lang=en"`.
- The share card follows the language: the English home emits `og:image` `brand/og-default.png` while `/?lang=ar` emits `brand/og-ar.png`, and `GET /brand/og-ar.png` returns `200 image/png 88,762` bytes.
- Accessibility landmarks are live: the home document carries `href="#main"`, `aria-controls="site-menu"` and the localized `aria-label="Primary"`; `/pricing` carries `aria-current="page"`.
- The FAQ is seven questions and `/sitemap.xml` serves `application/xml` with 32 `loc`/`hreflang` matches.

Desktop installer `2.6.1` (parity rebuild):

- Built from the same tree: `build:electron rc=0`, `electron-builder rc=0`; artifact `idexal-os-setup-2.6.1-x64.exe`, **374,408,436 bytes**, sha256 `c5e385eb129ca2c3e6b62cb6697104440a4c32b6f8fb7714ef8fde16d2ba1a02` (blockmap 378,954 B).
- Published to the platform release center with the merge-safe helper: live manifest fetched first, then merged **20 → 21** releases (`latest=2.6.1`), with the `v2.6.1/latest.yml` feed written and the installer uploaded before the manifests.
- Release-center download verified with a range request: `HTTP/1.1 206 Partial Content`, `content-range: bytes 0-1023/374408436`.
- Public GitHub release `v2.6.1` created (id `426377124`, not draft/prerelease) with assets `idexal-OS-Setup-2.6.1.exe`, its `.blockmap`, `latest.yml` and `SHA256SUMS`.
- `GET /v1/app-version` returns `{"latestAppVersion":"2.6.1",...}` and lists `2.6.1` first.

Post-deploy monitoring:

- The `2026-10-28` platform log still holds exactly one `production.ERROR`, the pre-existing `05:00:18` "Will not follow more than 5 redirects"; nothing new after the deploy. `SortDirection` count `0`.
- Queue is idle-cycling (`Worker STOPPED Queue empty`) with no failed jobs.
- Disk usage `68%` (545 GB free); `public/desktop` totals `7.4G` after the new installer.

#### Deploy checklist

1. ✅ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `2.6.1`.
2. ✅ No new migration this release; confirm nothing is pending and the config, route and view caches are rebuilt.
3. ✅ Verify the header switch links to an explicit `?lang=`, that `/?lang=ar` renders `dir="rtl"`, and that a signed-in member's choice is saved to `users.locale`.
4. ✅ Verify the skip link, the `<main id="main">` target, the localized nav label and `aria-current="page"` are present; `/?lang=ar` serves `brand/og-ar.png` while the English home serves `brand/og-default.png`; and the FAQ JSON-LD lists seven questions.
5. ✅ Publish the desktop installer `2.6.1` to the release center and the public GitHub release; `/v1/app-version` lists `2.6.1` first.
6. ✅ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- The desktop and mobile language switches are separate markup; extracting a shared partial would remove the duplication on a later pass.
- Same parity gap as 2.5.x–2.6.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v2.6.0

#### Commit

The working-tree changes below (the public site becomes findable, shareable and written for the whole world), on top of the v2.5.1 commit and its tracker note. Website release: the desktop installer is rebuilt only so its version stays in step, with no app or server change. No feature flag is declared: this changes the marketing site's markup and copy, not a product capability, so the kill switch is the deploy itself.

#### Released at

`2026-10-28` — see Deploy evidence below.

#### Title

The public site is built to be found and shared by anyone, anywhere.

#### One-line summary

The site spoke only to visitors who already knew the link and already read one region's language: it had no canonical URL, no language alternates, no share card, no structured data and no sitemap, and its own copy called it "Arabic-first". Now every public page advertises a canonical URL and English/Arabic alternates, publishes a 1200×630 share card and JSON-LD (organization, site, app, FAQ), ships `/sitemap.xml` and points `robots.txt` at it, and reads as one global workspace with Arabic offered as a first-class language rather than the whole identity.

#### Platform changes

- **A language is a URL, not a session secret.** Any page accepts `?lang=en|ar`; an unknown value is ignored. The choice is remembered for the visit and every canonical and alternate link carries the language, so a shared or indexed link keeps it. English is the anonymous default (`IDEXAL_DEFAULT_LOCALE`, a key the request middleware never rewrites) so a first-time visitor is never assumed to speak one region's language; a signed-in member still gets the language on their account.
- **One canonical URL and full language alternates.** Every public page emits `<link rel="canonical">`, `hreflang` for English, Arabic and `x-default`, and matching `og:url`/`og:locale`/`og:locale:alternate` — the default language lives at the bare URL and each translation is an addressable variant of the same page.
- **A share card that looks like the product.** `og:image`/`twitter:image` point at a new 1200×630 `brand/og-default.png` (the wordmark, the promise, the domain) with width/height/type/alt, plus `twitter:card`, `twitter:title` and `twitter:description`.
- **Structured data, not a keyword soup.** Every public page carries JSON-LD `Organization` and `WebSite`; the home page adds `SoftwareApplication` (free offer, version) and `FAQPage` built from the same questions the page renders, and `/download` adds `SoftwareApplication`.
- **A sitemap and a robots policy that invite crawlers in.** `GET /sitemap.xml` lists every public page with per-language alternates and `x-default`; `robots.txt` now allows the site and advertises the sitemap while keeping `/admin`, `/dashboard`, `/locale/`, `/auth/`, `/r/` and `/invitations/` out.
- **Copy repositioned from one region to the world.** The title, description, hero eyebrow, subtitle, the language feature card and the footer now describe a global AI workspace and name Arabic as a first-class language, not the whole identity — in both English and Arabic.
- `locales.default` and `locales.supported` are declared once in `config/idexal.php`; `supported_locales()`, `default_locale()`, `locale_url()` and `canonical_url()` are the single helpers, and the locale middleware, the locale route and the API key middleware all read them.

#### Desktop / server changes

- No contract, enforcement or delivery change. `apps/server` and the desktop config are untouched; the installer is rebuilt so its version matches the platform.

#### App changes

- No app source change. The desktop build is re-run only for version parity.

#### Verified before deploy

- Laravel: `418 tests / 2662 assertions` (Feature + Unit), Pint clean.
- New coverage: `PublicSiteSeoTest` (6 tests / 50 assertions) — the home head carries canonical, the three `hreflang` links, the og/twitter image tags, the `index, follow` robots policy and the four JSON-LD types; `?lang=ar` switches the document to `dir="rtl"` with an `ar_AR` og locale and an `?lang=ar` canonical and is remembered on the next page, `?lang=en` returns to English with no language in the canonical, and an unknown language is ignored; `/sitemap.xml` serves `application/xml` and lists all eight public pages with English, Arabic and `x-default` alternates; `robots.txt` advertises the sitemap and disallows the private surfaces; the share card is a real 1200×630 PNG (read from its IHDR).
- `node scripts/release/review.mjs` permits `2.6.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com` (commit `d91f6dc`):

- `deploy-2.6.0.sh` (full backup → sparse clone → `rsync` into `/var/www/vhosts/idexal.com/os.idexal.com-app` → `composer install`/autoload → asset build → config/route/view recache) returned `rc=0`; no migration to run and nothing pending.
- `config('idexal.version')` reads `2.6.0`; `GET /v1/health` returns `{"ok":true,...,"version":"2.6.0"}`.
- `locales.default` resolves to `en` and `locales.supported` to `en,ar` on the live app; the `sitemap` route is registered.
- `public/brand/og-default.png` (88,261 B) is present and is a real 1200×630 PNG; `robots.txt` carries the `Sitemap:` line and keeps `/admin`, `/dashboard`, `/locale/`, `/auth/`, `/r/` and `/invitations/` disallowed.
- The connector permission switch is still `false` (no rollout this release).
- All ten public pages return `200`; `/sitemap.xml` serves `application/xml`.
- Live SEO re-verified independently over HTTPS: the home document carries `<link rel="canonical">`, `hreflang` for `en`, `ar` and `x-default`, and four valid JSON-LD blocks (`Organization`, `WebSite`, `SoftwareApplication`, `FAQPage`); `/pricing` carries two and `/download` three; `/?lang=ar` serves `lang="ar" dir="rtl"` with an `?lang=ar` canonical; `/sitemap.xml` lists 8 URLs with 24 language alternates and zero raw ampersands; the share card is 1200×630.

Desktop installer `2.6.0` (parity rebuild):

- Built from the same tree: `build:electron rc=0`, `electron-builder rc=0`; artifact `idexal-os-setup-2.6.0-x64.exe`, **374,408,558 bytes**, sha256 `6f0c0449c942b0e160f29195b2cafc6b4a74fd6b37959cea2fd2b66ad3099da7` (blockmap 378,650 B).
- Published to the platform release center via the merge-safe helper: live manifest fetched first, then merged **19 → 20** releases (`latest=2.6.0`), with the `v2.6.0/latest.yml` feed written; the installer was uploaded before the manifests that point at it.
- Release-center download verified with a range request: `HTTP/1.1 206 Partial Content`, `content-range: bytes 0-1023/374408558`, `application/octet-stream`.
- Public GitHub release `v2.6.0` created (id `426356612`, not draft/prerelease) with assets `idexal-OS-Setup-2.6.0.exe` (374,408,558 B), its `.blockmap`, `latest.yml` and `SHA256SUMS`.
- `GET /v1/app-version` returns `{"latestAppVersion":"2.6.0",...}` and lists `2.6.0` first in `publishedDesktopVersions`.

Post-deploy monitoring:

- Platform log for `2026-10-28` holds exactly one `production.ERROR`, the pre-existing `05:00:18` "Will not follow more than 5 redirects" from before this release; nothing new after the deploy. `SortDirection` count `0`.
- Queue is idle-cycling (`Worker STOPPED Queue empty`) with no failed jobs.
- Disk usage `68%` (545 GB free); `public/desktop` totals `7.0G` after the new installer.

#### Deploy checklist

1. ✅ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `2.6.0`.
2. ✅ No new migration this release; confirm nothing is pending and the config, route and view caches are rebuilt.
3. ✅ Verify `/` serves the canonical, the `hreflang` alternates, the og image tags and the JSON-LD; `/?lang=ar` renders `dir="rtl"`.
4. ✅ Verify `/sitemap.xml` serves `application/xml` with alternates and `robots.txt` advertises it.
5. ✅ Publish the desktop installer `2.6.0` to the release center and the public GitHub release; `/v1/app-version` lists `2.6.0` first.
6. ✅ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- One share card, English-first; a per-language `og:image` is a follow-up.
- The sitemap covers the marketing surfaces only — there are no blog or docs content pages to list yet.
- Same parity gap as 2.5.x: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.

## v2.5.1

#### Commit

The working-tree changes below (the connector request loop, completed in both directions), on top of the v2.5.0 commit and its tracker note. Same feature flag as v2.5.0 (`connectorPermissionScopes`), still off by default; no new flag. It closes two halves of the ask loop that 2.5.0 left open.

#### Released at

`2026-10-28` — see Deploy evidence below.

#### Title

A member hears the answer to their connector request, and can ask again.

#### One-line summary

2.5.0 let a member ask; it left two ends of the loop unfinished. A revoked or lapsed connector told the member to "ask an admin to approve it again" but offered no button, so they were stuck at a dead end. And once an operator answered, the member who asked was told nothing — they had to reopen the Library to find out. Now the ask is offered whenever a connector is not approved, and the member who asked is notified (on the dashboard's notifications page) when an admin approves the connector or dismisses their ask.

#### Platform changes

- **Every answered ask tells the asker.** Approving a connector notifies each member with an open ask (`connector_request.approved`); dismissing one notifies that member (`connector_request.declined`), and the copy says they may ask again. One notice per person, however their asks line up. Delivery reuses `NotificationService` and links to `dashboard.notifications`.
- **A revocation tells nobody.** Access taken away is not a request answered, so revoking a grant notifies no one — the same rule 2.5.0 applied to the ask itself.
- `ConnectorGrantRequest::openFor($orgId, $slug)` gathers the open asks **with their members, before they are resolved** (resolving clears the `open` status the query filters on), so the operator who answers can tell each of them.
- Localized in English and Arabic.

#### Desktop / server changes

- No contract, enforcement or delivery change: the notice is a website notification, not a new desktop block. `apps/server`'s connector permission engine is untouched.

#### App changes

- **The ask is offered whenever it is the remedy.** The Library catalogue row and the connector setup page now offer **Request approval** for any connector that is not approved — pending, revoked or lapsed — instead of showing a dead-end "Access revoked" / "Approval expired" badge. `connectorApprovalAsk(view, requested)` is the single decision (`askable` / `waiting`), replacing the three-way status checks at both call sites.
- Localized in English (the other app locales fall back to English as usual).

#### Verified before deploy

- Laravel: `412 tests / 2612 assertions` (Feature + Unit), Pint clean.
- New coverage: `ConnectorRequestAccessTest` grows to 11 tests / 85 assertions — dismissing tells the member (`connector_request.declined`, carrying the connector's name), approving tells them (`connector_request.approved`), a member who asks twice is told once, two members who ask are each told once, and a revocation notifies nobody.
- App: `bun test tests/connector-permission-view.test.ts` — 23 pass (4 new for `connectorApprovalAsk`); `pnpm typecheck` clean; the i18n key suite passes.
- `node scripts/release/review.mjs` permits `2.5.1`.

#### Deploy evidence

Platform deploy to `os.idexal.com` (commit `f3f986f`):

- `mysqldump` backup (368,162 bytes) and a `tar.gz` code snapshot (54,395,544 bytes) written to `/var/www/vhosts/idexal.com/.deploy/backups/` before touching anything. `.env` carries no `IDEXAL_VERSION` override, so `config/idexal.php` (`2.5.1`) is the source of truth.
- Sparse clone of the private repo at `f3f986f` (444 files), rsynced into `/var/www/vhosts/idexal.com/os.idexal.com-app`; `npm ci` (0 vulnerabilities) + Vite build clean (`app-Dke11aT4.js`, 258.95 kB — byte-identical to 2.5.0, as expected: the platform frontend changed not at all) and `composer dump-autoload -o --no-dev` clean (6,867 classes).
- **No new migration**: `migrate:status` had nothing pending before and `migrate --force` reported "Nothing to migrate"; nothing pending after.
- `optimize:clear` + `config:cache` + `route:cache` + `view:cache` + `queue:restart`; `config('idexal.version')` reads `2.5.1`.
- **The notification code landed**: both `lang/en/ui.php` and `lang/ar/ui.php` carry the two new titles (`connector_approved_title`, `connector_declined_title`), and `AdminConnectorController` contains the approve-side `connector_request.approved` notice plus `ConnectorGrantRequest::openFor()` (gathering asks before they are resolved).
- **Still inert.** `POST /v1/me/connector-requests` (`api.me.connector-requests`) is still registered, the switch still reads `desktop_switch_connector_permission_scopes_enabled=false`, and an unauthenticated POST and `GET /v1/me/desktop-config` both answer `401`.
- `connectors:expire-grants` still scheduled hourly (`0 * * * *`) and ran clean by hand (0 approvals expired).
- Verified live: `/`, `/pricing`, `/docs`, `/download`, `/login`, `/register` all `200`; `GET /v1/health` → `{"ok":true,"service":"idexal-os","version":"2.5.1",...}`; `/admin/connectors` → `302` to login. `GET /v1/app-version` still lists `2.5.0` first because the desktop 2.5.1 publish follows this step.

Desktop installer `2.5.1`:

- `pnpm build:electron` then `electron-builder --win nsis --publish never` both exited `0` (`rc1=0 rc2=0`). Artifacts in `apps/desktop/dist-electron`: `idexal-os-setup-2.5.1-x64.exe` (374,408,601 bytes; sha256 `f87ad956a9d1bbf358b6e3107e20118d7899f1434b63dd2b53f8aa1e2e7359cc`; sha512 `iuat+l6dpJJ0NlU4b5Mt4zQZNiyYIl8mpLjpKdQ0y6wzI+yPQSGjXD2e2Hm26SqeumnYbjTLb99U94EZU7a/3w==`), `idexal-os-setup-2.5.1-x64.exe.blockmap` (378,923 bytes) and `latest.yml`.
- Published to the platform release center: `public/desktop/releases.json` merged onto the live manifest — **19 entries (was 18)**, `latest=2.5.1`. `GET /v1/app-version` lists `2.5.1` first; the installer answers `206` (`content-range: bytes 0-1023/374408601`); the versioned feed `/desktop/v2.5.1/latest.yml` answers `200`.
- Public GitHub release `v2.5.1` (id `426331445`) with `idexal-OS-Setup-2.5.1.exe`, its `.blockmap`, `latest.yml` and `SHA256SUMS`.

Post-deploy monitoring:

- The log for `2026-10-28` holds exactly one `production.ERROR`: the pre-existing `05:00:18` provider redirect failure ("Will not follow more than 5 redirects"). Nothing new after the `06:31` publish; `SortDirection` count `0`.
- Queue worker cycling cleanly on the minute (`Worker STOPPED Queue empty`) with no backlog.
- Disk `68%` used (546 GB free); `public/desktop` grew to `6.7 GB` with the new installer.
- `/v1/health` reads `2.5.1` and every public page answers `200`.

#### Deploy checklist

1. ✅ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `2.5.1`.
2. ✅ No new migration this release; confirm nothing is pending and rebuild the bootstrap caches.
3. ✅ Confirm `POST /v1/me/connector-requests` is still inert while the switch is off (route registered, switch `false`, unauthenticated `401`).
4. ✅ Publish the desktop installer `2.5.1` to the release center and the public GitHub release.
5. ✅ Verify `/v1/health` reads `2.5.1`, `/v1/app-version` lists `2.5.1` first, and the release center serves the installer.
6. ✅ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- Same parity gap as 2.5.0: the OSS `ee/apps/den-api` backend still lacks the connector permission and request blocks.
- The ask is for the whole organization, not a team (teams do not exist on this platform).
- One-use approval for high-risk runs (community backlog #3) is still deferred.

## v2.5.0

#### Commit

The working-tree changes below (a member can ask an admin for connector access), on top of `45638c5`. Same feature flag as 2.4.0/2.4.1 (`connectorPermissionScopes`), still off by default: a request is meaningless without approvals, so the existing switch is the kill switch and no new flag is declared. This release closes the gap where a member who needed a connector had no way to say so — the operator had a queue only in their head.

#### Released at

`2026-10-28` — see Deploy evidence below.

#### Title

A member can ask an admin to approve a connector, and the ask lands in their queue.

#### One-line summary

Approving a connector was an operator-only action, so a member who needed one waited or asked outside the product. Now a member asks from the Library: the platform records one open ask per member and connector, notifies the organization's managers, and lists it on the Connectors screen, where an operator reviews it (which pre-ticks what the connector declares) or dismisses it. Approving the connector answers every member who asked for it at once, because the answer is the grant, not the person. A request grants nothing on its own — the operator still approves — and the whole surface stays inert while the switch is off.

#### Platform changes

- **The ask.** A new `connector_grant_requests` table holds one row per (organization, connector, member) with the connector's declared scopes, a status (`open`/`approved`/`dismissed`) and who resolved it. Asking again reopens the same row rather than queueing a second, so the queue stays one line per person.
- **The endpoint.** `POST /v1/me/connector-requests` (member, `api.key` + `throttle:gateway`) resolves the slug against the catalogue and stores the scopes the catalogue declares — never the caller's — so an ask cannot be widened. It refuses (409) a connector that is already actively approved, and while the feature is off the route answers 404, so a platform that has not switched the feature on behaves exactly as before. A new ask notifies the organization's managers through `NotificationService::notifyManagers()` and writes a `member.connector_request.created` audit line; a repeat ask does neither.
- **The delivery.** `GET /v1/me/desktop-config` carries `connectorRequests` — the member's own open asks (`slug`, `status`, `scopes`, `requestedAt`) — **only while the switch is on**, so the Library can show that an ask is already waiting instead of offering it twice.
- **The queue.** `/admin/connectors` gains an **Access requests** panel listing each open ask (member, connector, when, the scopes it needs). **Review** links back to the connector with the scopes the connector declares pre-ticked; **Dismiss** closes one member's ask with an `admin.connector_request.dismissed` audit line, and the member may ask again.
- **Approving answers everyone.** Approving a connector resolves every open ask for it as `approved`; a revocation does not, because access taken away is not a request granted.
- Localized in English and Arabic.

#### Desktop / server changes

- **The contract carries the asks.** `packages/types/src/den/connector-permissions.ts` gains `desktopConnectorRequestSchema` (`DenConnectorRequest`), `connectorRequestFor()` (only an `open` ask counts) and `normalizeConnectorRequests()`; `desktopConfigSchema.connectorRequests` is optional, so a platform from before this block still parses.
- **One write path.** `DenClient.requestConnectorAccess(orgId, slug)` is the single call the app makes; the platform does the rest.
- **No new enforcement.** A request grants nothing, so `apps/server`'s connector permission engine is unchanged; the grant revision remains the only thing that widens or ends access.

#### App changes

- **Ask, then wait.** In the Library catalogue, a connector whose approval is pending now offers a **Request approval** button instead of only a dead-end badge; once the ask is sent the row reads **Approval requested**. The connector's setup page swaps its Add button for the same action while approval is pending, and explains that an admin is being asked.
- `connectorApprovalRequested()` reads the delivered block, and the page keeps a local set of asks sent this session so the row flips the moment the request lands, before the next config read corroborates it.
- Localized in English (the other app locales fall back to English as usual).

#### Verified before deploy

- Laravel: `409 tests / 2590 assertions` (Feature + Unit), Pint clean.
- New coverage: `ConnectorRequestAccessTest` (8 tests) — the feature ships off and the endpoint does not exist; a member's ask records the catalogue's scopes, reaches the desktop and notifies a manager with an audit line; asking twice queues one ask; an already-approved connector is refused (409, code `already_approved`); an unknown connector is not a request; an operator sees the ask and dismissing it closes it with an audit line and drops it from the delivery; approving the connector resolves the open ask; a request never crosses workspaces.
- App: `bun test tests/connector-permission-view.test.ts` — 19 pass (4 new for `connectorApprovalRequested`); `pnpm typecheck` clean; the i18n key, placeholder-parity and interpolate suites pass.
- `packages/types`: `tsc -p tsconfig.json --noEmit` clean. `node scripts/release/review.mjs` permits `2.5.0`.

#### Deploy evidence

Platform deploy to `os.idexal.com` (commit `5f6987f`):

- `mysqldump` backup (343,495 bytes) and a `tar.gz` code snapshot (54,393,805 bytes) written to `/var/www/vhosts/idexal.com/.deploy/backups/` before touching anything. `.env` carries no `IDEXAL_VERSION` override, so `config/idexal.php` (`2.5.0`) is the source of truth.
- Sparse clone of the private repo at `5f6987f` (444 files), rsynced into `/var/www/vhosts/idexal.com/os.idexal.com-app`; `npm ci` + Vite build clean (`app-Dke11aT4.js`, 258.95 kB) and `composer dump-autoload -o --no-dev` clean (6,867 classes).
- **The one new migration ran**: `2026_11_08_000000_create_connector_grant_requests_table` was the only pending migration and completed in 27.82 ms; `migrate:status` shows nothing pending afterwards. The table exists with `status`, `resolved_by` and `resolved_at` columns.
- `optimize:clear` + `config:cache` + `route:cache` + `view:cache` + `queue:restart`; `config('idexal.version')` reads `2.5.0`.
- **The feature ships inert.** `POST /v1/me/connector-requests` (`api.me.connector-requests`) is registered in `route:list`, and the desktop switch reads `desktop_switch_connector_permission_scopes_enabled=false`. An unauthenticated POST and an unauthenticated `GET /v1/me/desktop-config` both answer `401` (the route exists and is behind `api.key`); the switch-off `404` for an authenticated member is proven by `ConnectorRequestAccessTest::test_the_feature_ships_off_and_the_endpoint_does_not_exist`, since a live 404 needs a member key the deploy host does not hold.
- `connectors:expire-grants` is still scheduled hourly (`0 * * * *`) and ran clean by hand (0 approvals expired).
- Verified live: `/`, `/pricing`, `/docs`, `/download`, `/login`, `/register` all `200`; `GET /v1/health` → `{"ok":true,"service":"idexal-os","version":"2.5.0",...}`; `/admin/connectors` → `302` to login. `GET /v1/app-version` still lists `2.4.1` first because the desktop 2.5.0 publish follows this step.

Desktop installer `2.5.0`:

- Built at `05:51` UTC: `pnpm build:electron` (rc 0) then `electron-builder --config electron-builder.yml --win nsis --publish never` (rc 0). Artifact `idexal-os-setup-2.5.0-x64.exe` (374,408,379 bytes; sha512 `AICs6I3f9pvvw8zQN/sUzgCiQzJhK3LdifSJv8qz2IN2DR9OeHE22cF9ejKefAlpMh9DV7/FAp6ARnrhxc7Y6A==`, sha256 `b59dcb4121337c4ae1f41f81fd88f4543e2574018f9b2ad2a49ad5f64689737a`) plus `.blockmap` (378,929 bytes) and `latest.yml`.
- Release center (`public/desktop/`): installer + `.blockmap` + `latest.yml` + `releases.json`, plus the versioned feed `public/desktop/v2.5.0/latest.yml`. The publisher fetched the live manifest first and merged onto it — 17 → 18 entries, `latest = 2.5.0` — then uploaded the installer before the manifests that point at it. Verified over HTTP: the installer answers `206` with `Content-Range: bytes 0-1023/374408379` (and `HEAD` `200` in 126 ms); root `latest.yml` and the versioned feed both answer `200`.
- `GET /v1/app-version` now reads `{"latestAppVersion":"2.5.0","minAppVersion":"0.0.0","publishedDesktopVersions":["2.5.0","2.4.1","2.4.0","2.3.0","2.2.6","2.2.5","2.2.4","2.2.3","2.2.2","2.2.1","2.2.0","2.1.0"]}`.
- Public GitHub release `v2.5.0` (id `426308802`) published with assets `idexal-OS-Setup-2.5.0.exe`, `idexal-OS-Setup-2.5.0.exe.blockmap`, `latest.yml` and `SHA256SUMS`.

Post-deploy monitoring (after the platform deploy and the desktop publish):

- The only `production.ERROR` in today's log is the pre-existing scheduled `RunProviderHealthChecks` provider-redirect failure at `05:00:18` UTC — before the deploy and the publish. Zero errors after `05:16`; no connector-related log lines.
- Queue is cycling normally (`Worker STOPPED Queue empty` through `05:57`).
- Host disk 68% used; `public/desktop/` 6.3 GB.

#### Deploy checklist

1. ✅ Back up db + code on the platform host before touching anything (db 343,495 B, code 54,393,805 B).
2. ✅ Apply the `create_connector_grant_requests_table` migration and confirm nothing is pending (27.82 ms; no pending after).
3. ✅ rsync the sparse clone into the app root and rebuild assets/autoload; `config('idexal.version')` reads `2.5.0`.
4. ✅ Confirm `POST /v1/me/connector-requests` is inert while the switch is off (route registered, switch `false`, unauthenticated `401`; the authenticated `404` is covered by `ConnectorRequestAccessTest`).
5. ✅ Publish the desktop installer `2.5.0` to the release center and the public GitHub release.
6. ✅ Verify `/v1/health` reads `2.5.0` (done), `/v1/app-version` lists `2.5.0` first (after publish), and the release center serves the installer.
7. ✅ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- The `ee/apps/den-api` (OSS) backend still lacks `connectorPermissions` and therefore does not carry `connectorRequests` either — the same documented parity gap as 2.4.0/2.4.1, unchanged.
- A request is not per-team: teams do not exist on this platform, so the ask is for the whole organization.

## v2.4.1

#### Commit

The working-tree changes below (connector approvals that expire), on top of `a502027`. Same feature flag as 2.4.0 (`connectorPermissionScopes`), still off by default; this release adds a duration to an approval and makes the lapse bite on the desktop, in the delivery and in the audit log. One-use approval is still deferred to a follow-up; nothing here needs a new desktop-to-platform write path.

#### Released at

`2026-10-28` — see Deploy evidence below.

#### Title

An approval can now come with an expiry, and it lapses on its own.

#### One-line summary

An operator approves what a connector may do; that approval can now be given for a fixed window — seven, thirty or ninety days, or until revoked — instead of only forever. When the window passes the connector stops on its own: the desktop refuses it the next time it is used, the platform sweeps the row to `expired` at the next hour and writes an audit line, and re-approving is a normal new revision that revives it with a fresh window. Nothing changes while the feature is off; connectors approved without a duration keep working exactly as in 2.4.0.

#### Platform changes

- **A duration on the approval.** The `/admin/connectors` form gains an *Approval lasts* choice — *Until revoked*, 7 days, 30 days, 90 days — a closed set validated with `Rule::in`. Approving writes a lease on the grant (`lease_expires_at`, reserved by the 2.4.0 migration and used for real here); revoking clears it. `ConnectorGrantPolicy::expiryFromChoice()` is the one place a duration becomes a lease, and `expiryChoiceFor()` maps a stored lease back to the form's default.
- **A lapse is delivered, not waited for.** `ConnectorGrant::permissionsFor()` computes each grant's delivered `status` from its lease, so a grant whose deadline has passed is delivered as `expired` even before the scheduled sweep has run. An approval that was meant to lapse cannot outlive its deadline because the platform has not caught up. The block now carries `expiresAt` (ISO instant or null) per grant.
- **The sweep.** `connectors:expire-grants` (scheduled hourly in `routes/console.php`) flips every active grant whose lease has passed to `expired`, bumps the organization's set revision once per sweep, and writes one `admin.connector_grant.expired` audit entry per connector whose summary moved. It is idempotent: a second run finds nothing and writes nothing.
- **Named in the trail.** `ConnectorGrant::STATUS_EXPIRED` is a third state beside `active` and `revoked`; the row keeps its scopes, so re-approving is the next revision of the same grant. An active approval with a duration summarizes as `Approved · :count permissions · expires :date`, so shortening or dropping a window is a change an operator can see in the audit log.
- Localized in English and Arabic.

#### Desktop / server changes

- **The contract carries the deadline.** `desktopConnectorGrantSchema` gains `status: "expired"` and `expiresAt` (nullable, defaulted to null so a platform from before this field still parses). `decideConnectorPermission()` refuses an `expired` grant and, on an `active` one, refuses it the moment `expiresAt` has passed — with an injectable clock so the deadline is testable. `connectorGrantExpired()` treats a missing or unreadable instant as "never expired", so a value the desktop cannot read never silently cuts a working connector off.
- **Enforcement is unchanged in shape.** `apps/server` already denies through `connectorPermissionDenial`, so the deadline is enforced at all three MCP-add sites with no new plumbing and no relaunch.
- Regenerated the Den API contract in the same commit: `packages/docs/openapi.json` (`DenConnectorGrant` status enum + `expiresAt`, 16 lines) and `packages/sdk/src/gen/types.gen.ts` (3 lines) — the only contract diff.

#### App changes

- **The Library tells the truth about a lapse.** A connector whose approval expired shows an **Approval expired** badge and no Add action, with the same explanation on its setup page; an approval that is still valid shows when it expires. `connectorPermissionView()` reads the delivered deadline (and an `expired` status) and returns a fourth state, `expired`, so the catalogue row and the setup page agree; `formatApprovalDate()` renders the instant, or the raw value when it cannot be parsed.

#### Verified before deploy

- Laravel: `401 tests / 2527 assertions` (Feature + Unit), Pint clean.
- New coverage: `ConnectorGrantExpiryTest` (10 tests / 80 assertions) — an approval with no duration never expires; a duration delivers an `expiresAt` and stays active; a lease that has passed is delivered as `expired` before the sweep runs; the sweep flips the row, bumps the revision and writes the audit line; the sweep is idempotent; re-approving revives an expired grant with a fresh lease; revoking clears the lease; the screen shows the duration and an expired badge; a duration outside the offered set is rejected; a stored lease maps back to its choice. `ConnectorPermissionScopesTest` (12) and `CanonicalHostTest` (7) updated and green.
- Desktop server: `bun test ./test/connector-permission-engine.test.ts` — 13 pass (4 new: an expired approval denied, a mid-run deadline denied without a status flip, a future deadline allowed, an unreadable expiry never cutting a connector off); `tsc -p tsconfig.json --noEmit` clean for `packages/types` and `apps/server`.
- App: `bun test tests/connector-permission-view.test.ts` — 15 pass (5 new); `tsc -p tsconfig.json --noEmit` clean; the i18n key, placeholder-parity and interpolate suites pass.
- SDK: `tsc -p tsconfig.json --noEmit` clean after regeneration.
- Feature registry: `pnpm features:sync` ran green and produced no Helm diff (the registry is unchanged; the flag is the same one 2.4.0 declared).

#### Deploy evidence

Platform (`os.idexal.com`), deployed `2026-10-28`:

- `mysqldump` backup (322,433 bytes) and a `tar.gz` code snapshot (54,391,633 bytes) written to `/var/www/vhosts/idexal.com/.deploy/backups/` before touching anything. `.env` carries no `IDEXAL_VERSION` override, so `config/idexal.php` (`2.4.1`) is the source of truth.
- Sparse clone of the private repo at `b8f6b41` (441 files in the sparse set), rsynced into `/var/www/vhosts/idexal.com/os.idexal.com-app` with `rsync -a --delete`, excluding `vendor/ storage/ public/build/ public/desktop/ .env* node_modules/`.
- `npm ci` clean (0 vulnerabilities) and `npm run build` clean (Vite 8.3.4, `app-Dke11aT4.js` + `app-B-B4CrIh.css`); `composer dump-autoload -o --no-dev` under PHP 8.4 (6,866 classes).
- `migrate:status`: nothing pending before or after; `php artisan migrate --force` reports "Nothing to migrate".
- `optimize:clear` + `config:cache` + `route:cache` + `view:cache` + `queue:restart`; `config('idexal.version')` reads `2.4.1`.
- `connectors:expire-grants` is registered and scheduled hourly (`0 * * * *`); run by hand it printed "Expired 0 connector approvals." and returned 0 (the console line renders in Arabic because the platform's default locale is Arabic, as the other scheduled commands do).
- `connector_grants` and its `lease_expires_at` column both present.
- Verified live: `/`, `/pricing`, `/docs`, `/download`, `/login`, `/register` → 200; `GET /v1/health` → `{"ok":true,"service":"idexal-os","version":"2.4.1",...}`; `GET /v1/app-version` still lists `2.4.0` first because the desktop 2.4.1 publish follows this step; `/admin/connectors` → 302 to login (auth-gated as intended).

Desktop installer `2.4.1`:

- Built with `pnpm build:electron` (`build:electron rc=0`) then `electron-builder --config electron-builder.yml --win nsis --publish never` (`electron-builder rc=0`; electron 43.7.7). Artifact `idexal-os-setup-2.4.1-x64.exe` = 374,408,534 bytes, `sha512 RSilG6VaSluKs5n8LyZ/wkjx7mqWGmXeDAiM1m2v27aBoHOItxe0y3Tn7MeystqE9ypphYe68mGhR6l64fp0bA==`; `.blockmap` = 378,732 bytes.
- Release center (`public/desktop/`): installer + `.blockmap` + `latest.yml` + `releases.json`, plus the versioned feed `public/desktop/v2.4.1/latest.yml`. The publisher fetched the live manifest first and merged onto it — 16 → 17 entries, `latest = 2.4.1` — then uploaded the installer before the manifests that point at it. Verified over HTTP: the installer answers `206` with `Content-Range: bytes 0-1023/374408534`; root `latest.yml` and the versioned feed both answer `200`.
- Public release `https://github.com/idexal/idexal-OS/releases/tag/v2.4.1` (id 426276147) with `idexal-OS-Setup-2.4.1.exe`, `idexal-OS-Setup-2.4.1.exe.blockmap`, `latest.yml` and `SHA256SUMS` — the same four assets as `v2.4.0`. The published `SHA256SUMS` matches the locally computed hash of the installer (`75fb0e652be6fad7b5628316221894365eb854b8e86b6705878b6c5d270f6544`).
- `GET /v1/app-version` now reads `{"latestAppVersion":"2.4.1","publishedDesktopVersions":["2.4.1","2.4.0","2.3.0","2.2.6","2.2.5","2.2.4","2.2.3","2.2.2","2.2.1","2.2.0","2.1.0"]}`.

Post-deploy monitoring (after the platform deploy and the desktop publish):

- `storage/logs/laravel-2026-10-28.log` **does not exist** after the release went live, so the platform logged no exception, error or warning at all for the 2.4.1 window.
- `storage/logs/queue.log` shows the worker cycling cleanly (`Worker STOPPED Queue empty`) once a minute through 04:57 UTC; no failed jobs.
- Host disk 68% used; `public/desktop/` 6.0 GB.

#### Deploy checklist

1. ✅ Back up db + code on the platform host before touching anything.
2. ✅ rsync the sparse clone into the app root and rebuild assets/autoload with `/opt/plesk/php/8.4/bin/php`.
3. ✅ No new migration this release; confirm nothing is pending and rebuild the bootstrap caches.
4. ✅ Confirm `connectors:expire-grants` is scheduled (`php artisan schedule:list`) and runs clean once by hand.
5. ✅ Publish the desktop installer `2.4.1` to the release center (merging onto the accumulated `releases.json`) and the public GitHub release.
6. ✅ Verify `/v1/health` reads `2.4.1`, `/v1/app-version` lists `2.4.1` first, and the release center serves the installer.
7. ✅ Monitor the platform logs and the queue for the window after the deploy.

#### Known gaps

- One-use approval is still deferred: it needs a new desktop-to-platform consumption write path, which this release does not add.
- `expiresAt` is not enforced at the `ee/apps/den-api` (OSS) layer, because that backend still does not carry `connectorPermissions` at all — the same documented gap as 2.4.0, unchanged by this release.

## v2.4.0

#### Commit

The working-tree changes below (connector permission scopes + audit trail), on top of `16c91b8`. Ships the approved core scope only: connector scopes, grant revisions, revocation, the audit trail, delivery to and enforcement on the desktop, and the reference mid-run revocation fixtures at each implemented layer. Offline leases/expiry and one-use approval are deliberately deferred to a follow-up.

#### Released at

`2026-10-28` — platform deployed to `os.idexal.com` (Laravel 13.37.0, `config('idexal.version') = 2.4.0`); desktop installer `2.4.0` published to `public/desktop/`.

#### Deploy evidence

Platform (`os.idexal.com`), deployed `2026-10-28`:

- `mysqldump` backup (341,786 bytes) and a `tar.gz` code snapshot (54,389,434 bytes) written to `/var/www/vhosts/idexal.com/.deploy/backups/` before touching anything.
- Sparse clone of the private repo at `b40031a` (439 files in the sparse set; `config/idexal.php` already reads `2.4.0`), rsynced into `/var/www/vhosts/idexal.com/os.idexal.com-app` with `rsync -a --delete`, excluding `vendor/ storage/ public/build/ public/desktop/ .env* node_modules/`.
- `npm ci` (0 vulnerabilities) and `npm run build` (Vite 8.3.4, 424 ms, `public/build/manifest.json` emitted); `composer dump-autoload -o --no-dev` under PHP 8.4 (6,865 classes; `SortDirection` resolves — the earlier `SortDirection` 500 fix reapplied preemptively).
- `php artisan migrate --force` applied `2026_11_07_000000_create_connector_grants_table` (49.70 ms); nothing pending afterward, and `Schema::hasTable` confirms both `connector_grants` and `connector_grant_revisions`.
- `optimize:clear` + `config:cache` + `route:cache` + `view:cache` + `queue:restart`; `config('idexal.version')` reads `2.4.0`.
- Verified live: `/`, `/pricing`, `/docs`, `/download`, `/login`, `/register` → 200; `GET /v1/health` → `{"ok":true,"service":"idexal-os","version":"2.4.0",...}`; `/admin/connectors` → 302 to login (auth-gated as intended).
- Verified the switch is live and off: `DesktopSwitch::ConnectorPermissionScopes->settingKey()` reads `desktop_switch_connector_permission_scopes_enabled` with value `false`, so the feature is deployable and inert until an operator turns it on.

Post-deploy monitoring (first ~6 minutes after the release went live):

- No `storage/logs/laravel-2026-10-28.log` exists, so the platform has logged **no exception, error or warning since the deploy**. The 27 `production.ERROR` lines in `laravel-2026-10-27.log` are all stamped `22:59`–`23:05` on 10-27 — the previous release window's `Class "SortDirection" not found` (fixed at 23:05 that evening) — and none after.
- `artisan queue:failed` → `No failed jobs found`; `storage/logs/queue.log` shows the worker cycling (`Worker STOPPED Queue empty`) each minute through 02:50 UTC.

Desktop installer `2.4.0`, published `2026-10-28`:

- Built with `pnpm build:electron` (`build_rc=0`, 5m 04s; the Electron bridge covers 37 renderer methods) then `electron-builder --win nsis --publish never` (`pack_rc=0`; electron 43.7.7; every sidecar and the app binary signed with `signtool.exe`). Artifact `idexal-os-setup-2.4.0-x64.exe` = 374,409,430 bytes, `sha512 XtYtoZZYKVSQN20/hRWP97hwg/qRsmRFRUzHpE1HijBVXGWuS/A/3nV8AafptCh9Du9+BkISoEYV9jDQIFw4zA==`.
- Release center (`public/desktop/`): installer + `.blockmap` + `latest.yml` + `releases.json`, plus the versioned feed `public/desktop/v2.4.0/latest.yml`. Verified over HTTP: installer, blockmap, root `latest.yml` and the versioned feed all answer `206` to a range request; `releases.json` answers `200` with the full 16-entry manifest.
- Public release `https://github.com/idexal/idexal-OS/releases/tag/v2.4.0` (id 426235582) with `idexal-OS-Setup-2.4.0.exe`, `idexal-OS-Setup-2.4.0.exe.blockmap`, `latest.yml` and `SHA256SUMS` — the same four assets as `v2.3.0`. The published `SHA256SUMS` matches the locally computed hash of the installer (`39edcf82cd450f8a1adfed099aed2a7840bd1f90f38df75d23c96f9bbe250ca6`).
- `GET /v1/app-version` now reads `{"latestAppVersion":"2.4.0","publishedDesktopVersions":["2.4.0","2.3.0","2.2.6","2.2.5","2.2.4","2.2.3","2.2.2","2.2.1","2.2.0","2.1.0"]}`.

Incident during publishing (found and fixed before this release was called done):

- `publishedDesktopVersions` is derived from `public/desktop/releases.json` (`ApiMetaController::publishedDesktopVersions()`), so that file must **accumulate**. The publish helper passed a fresh, empty local path as the "remote" manifest, so the first upload replaced a 15-entry manifest with a single entry and `/v1/app-version` briefly reported only `["2.4.0"]`.
- Detected immediately by the post-publish check (the list had dropped from 10 unique versions to 1), recovered by merging the new `2.4.0` entry onto the prior manifest (`release-stage/releases.json`, still on disk) and re-uploading: 16 entries, 10 unique versions, `latest = 2.4.0`. Re-verified live.
- Hardened so it cannot recur: `publish-desktop-release.ps1` now `scp`s the live `releases.json` down first, merges onto it, refuses to publish if the entry count would drop or `latest` is not the version being shipped, and uploads the installer before the manifests that point at it.

#### Title

An organization says what each connector may do, and taking it back stops the connector.

#### One-line summary

The marketplace has always told you what a connector needs; nothing answered. An operator now approves the exact scopes a connector may use — read and write paths, the hosts it may reach, the tools it may call — and can revoke that at any time. The desktop refuses a connector that was never approved, and a revocation reaches a running app and denies the connector the next time it is used, without a relaunch. The whole set is ordered by a revision that only ever increases, so a stale permission set arriving late can never bring a revoked grant back. Everything is off until an operator turns it on: while the switch is off the desktop receives no permission block at all and every installed connector behaves exactly as it did before.

#### Platform changes

- **The catalogue declares what a connector needs; an operator answers.** A new `/admin/connectors` screen lists every catalogue connector that asks for scopes, with the exact scope set it declares, and lets an operator approve the subset the organization allows — and revoke it later with a reason. Nothing is approved by default, so a connector that asks for scopes does nothing until an operator ticks them; a connector that declares no scopes is not listed and is unaffected.
- **One scope vocabulary, shared with the desktop.** `App\Support\ConnectorScope` parses and canonicalizes the four kinds — `fs:read:<path>`, `fs:write:<path>`, `net:egress:<host>`, `tools:invoke` — to the same form the desktop uses. `ConnectorGrantPolicy::normalizeScopes()` validates, canonicalizes, deduplicates and caps a set (50), and an operator can approve only what the catalogue actually asked for, in the order the catalogue declares it.
- **The delivered block.** `GET /v1/me/desktop-config` carries `connectorPermissions` — `{revision, scoped, grants}` — **only while the feature switch is on**. `scoped` names every catalogue connector that declares scopes, so the desktop can refuse an unapproved connector without trusting a client's `declaresScopes` claim; `grants[]` carries each grant's `slug`, `revision`, `status` (`active`/`revoked`) and approved `scopes`. `GET /v1/me/mcp-connection-presets` adds each preset's requested `permissions` while the switch is on, so the app can show what a connector asks for before anything is approved.
- **Revocation is a revision, never a delete.** One `connector_grants` row per (organization, connector slug); one `connector_grant_revisions` row per organization holds the monotonic set revision (`bumpRevision()` writes it in a transaction with `lockForUpdate`). Every grant/revoke bumps the organization's revision, and revoking keeps the prior `scopes` on the row with `status = revoked` — so the next approval is simply the next revision and a replayed old set is ignored by its `revision`.
- **Audited by name.** A grant or revocation appends an audit entry with a before/after line per connector whose grant actually moved (`ConnectorGrantPolicy::changes()` diffs summaries captured before and after the write, so a save that changes nothing leaves no row behind). `connector_grants` was added to the audit field-label whitelist.
- **A desktop switch, off by default.** `DesktopSwitch::ConnectorPermissionScopes` (slug `connector_permission_scopes`) is published through the platform feature map and is toggleable and audited on `/admin/features`. The desktop switch is what surfaces the feature; while it is off, no permission block is sent and the whole surface is inert.
- **The catalogue asks for scopes.** `resources/marketplace/catalog.json` declares `permissions` for the reference connectors (`github-mcp`, `vercel-mcp`, `notion-mcp`).
- Localized in English and Arabic.

#### Desktop / server changes

- **The permission schema is the contract.** `packages/types/src/den/connector-permissions.ts` defines `{revision, scoped, grants}` and the pure decisions — `shouldApplyConnectorPermissions` (apply only a newer revision), `connectorGrantFor`, `decideConnectorPermission`, `connectorPermissionDenial` — as the single source of truth, and `desktop-policies.ts` carries the field through `desktopConfigSchema` and `normalizeDesktopConfig`.
- **The server enforces it, not the client.** `apps/server/src/connector-permission-engine.ts` holds a per-session `ConnectorPermissionRegistry` (`apply`/`active`/`clear`/`denial(slug)`), fed from the verified desktop config in `managed-desktop-policy.ts` and cleared on account change and session reset. `assertConnectorPermission(name)` guards **all three** MCP-add sites in `server.ts`, keyed by MCP server name = connector slug, so a revoked connector is denied by the server and a client cannot bypass it by claiming the connector declares no scopes.
- **A revocation lands mid-run.** Because the registry is updated from the desktop config and consulted at the add site, a connector revoked while the app is running is denied the next time it is used — the reference revocation fixture at each layer (`ConnectorPermissionScopesTest` on the platform, `connector-permission-engine.test.ts` on the server, `connector-permission-view.test.ts` in the app) proves the decision without a relaunch.

#### App changes

- **The Library tells the truth.** A connector the organization has not approved shows a **Needs approval** badge and no Add action; a revoked one shows **Access revoked**; neither can be added, because the server would refuse it. The setup page shows the scopes the organization approved, in words, and keeps a defensive block if the decision changes while the page is open.
- `apps/app/src/react-app/domains/settings/connector-permission-view.ts` turns the delivered block into the two things the UI needs (`pending`/`approved`/`revoked`, and the approved scopes), reads `connectorPermissions` from the verified desktop config (`DESKTOP_CONFIG_ITEMS`), and keeps the absent block as the gate: feature off ⇒ no block ⇒ the Library is unchanged.

#### Verified before deploy

- Laravel: `391 tests / 2447 assertions` (Feature + Unit), Pint clean.
- New coverage: `ConnectorPermissionScopesTest` (12 tests / 96 assertions) — the block is absent while the switch is off and present when on; `scoped` lists exactly the catalogue connectors that declare scopes; a grant is approved, reaches the desktop with its scopes, and its revision is the organization's; revocation keeps the row and flips the status while keeping the prior scopes; a stale set with an older revision can never restore a revoked grant; an operator can approve only what the catalogue asked for; a no-op save writes no audit row while a real change does; presets carry requested permissions only when the switch is on; cross-workspace isolation.
- Desktop server: `bun test ./test/connector-permission-engine.test.ts` — 9 pass; `tsc -p tsconfig.json --noEmit` clean for `packages/types` and `apps/server`.
- App: `bun test --isolate tests/connector-permission-view.test.ts` — 10 pass; `tsc -p tsconfig.json --noEmit` clean; the i18n key and placeholder-parity suites pass.
- Feature registry: `pnpm features:sync` regenerated the Helm chart's feature block, values schema and README table and the Den API contract/SDK in the same commit.

#### Deploy checklist

1. ✅ Migrations applied on the platform host (`create_connector_grants_table`).
2. ✅ Bootstrap caches cleared and rebuilt with `/opt/plesk/php/8.4/bin/php`.
3. ✅ Desktop installer `2.4.0` published to the platform release center and the public GitHub release.
4. ✅ `/admin/connectors` is registered and serves; `DesktopSwitch::ConnectorPermissionScopes` reads `false`, so **Connector permissions** is listed and off. The reference connectors' declared scopes are asserted by `ConnectorPermissionScopesTest`; the interactive check on a signed-in session is the remaining manual step.
5. ⬜ Not performed against production: turning the switch on and approving a grant would mutate live organization state. The delivery path is covered by `ConnectorPermissionScopesTest` instead.
6. ⬜ Not performed against production, same reason — mid-run revocation is covered by the three layer fixtures.

#### Known gaps

- The eval lane for connectors is a documented follow-up: the TypeScript `ee/apps/den-api` backend that the OSS eval harness runs against does not yet carry `connectorPermissions`, so parity with the Laravel platform (where v2.4.0 ships delivery) is deferred rather than fabricated.
- Offline leases/expiry (`lease_expires_at` is reserved on the grant row but unused) and one-use approval are deferred to the next release.
- Teams do not exist on this platform, so a grant is one per organization, not per team.
