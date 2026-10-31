# Release Changelog Tracker

Internal preparation file for release summaries. Not published until the deploy completes.

## v2.3.0

#### Commit

The working-tree changes below (health-aware routing, catalog honesty for combos, the desktop's unused failover copy removed, and the desktop's Den dashboard links made to resolve on the self-hosted platform), on top of `a4bbd75`. Supersedes the unpublished v2.2.9, which itself superseded the unpublished v2.2.8 and v2.2.7: none of them has reached the platform host, so this entry is the first one that ships the agent-permissions, desktop-switch, marketplace, combo-editor, model-visibility, cooldown and combo-plan-honesty work together (the plan copy is now purged and never read at both the model and the connection level).

#### Released at

`2026-10-27` — platform deployed to `os.idexal.com` (Laravel 13.37.0, `config('idexal.version') = 2.3.0`); desktop installer `2.3.0` published to `public/desktop/`.

#### Deploy evidence

- Source synced to the platform host with `rsync -a --delete` (excluding `vendor/`, `storage/`, `public/build/`, `public/desktop/`, `.env*`); `npm ci` and `vite build` both `rc=0` with a fresh `public/build/manifest.json`.
- Six migrations applied (`add_meta_to_gateway_requests_table`, `add_feature_overrides_to_ai_providers_table`, `add_feature_overrides_to_ai_models_table`, `fix_extension_installations_user_key`, `create_agent_permission_policies_table`, `purge_combo_plan_assignments`), then `optimize:clear` + `config:cache` + `route:cache` + `view:cache` + `queue:restart`.
- **Incident fixed during deploy:** every page began returning `500` with `Class "SortDirection" not found`. The host's `vendor/` had already moved to a newer Laravel that uses `SortDirection::Ascending`, provided by `symfony/polyfill-php86`; `composer dump-autoload` had been run under PHP 8.0.30 (`/usr/bin/php`), where the `>= 8.4.1` platform check fails, so `vendor/composer/autoload_files.php` never registered the polyfill (it listed php80/82/84/85 only) and the class was missing. Running the Composer **phar** (`/usr/local/psa/var/modules/composer/composer.phar`) with Plesk's PHP 8.4 (`/opt/plesk/php/8.4/bin/php`) regenerated the autoloader with `polyfill-php86`; `class_exists("SortDirection")` is now `true` and caches were rebuilt. Note: `/usr/local/bin/composer` is a shell wrapper and must not be fed to PHP directly.
- Live status after the fix: `/`, `/pricing`, `/docs`, `/download`, `/login`, `/register` → `200`; `/dashboard/models`, `/dashboard/custom-llm-providers`, `/dashboard/library?show=models`, `/dashboard/ai-gateway?tab=ai-providers` → `302` (auth redirect, route present); `/admin/*` → `200` (renders the marketing fallback, no admin data leaked).
- **Platform → desktop managed-model contract verified against production:** a temporary API key was minted, `GET /v1/inference-providers?scope=usable` returned `1` provider with `11` models, **zero** were dropped by the desktop's strict Crockford regex and every segment (alias, upstream id, model group, credential set) agreed; `.../{provider}/connect` answered `200` with an `ow_gw_` token; the key was then deleted. The "our models do not appear" report is a stale-installer gap, not a wire defect.
- Desktop `2.3.0` built locally (`build:electron` + `electron-builder --win nsis --publish never`, both `rc=0`) → `idexal-os-setup-2.3.0-x64.exe` (374,408,827 bytes, sha512 `SLrjzk0Bjs5StXUIHOGlW8JAbV5NDGgDM+SxCr0wcUa/Tvp0PXak2HEr8+kNiFUtNDg6oP09wV8O6jQjOGZqsA==`).
- Published to the platform's release center (`public/desktop/`): the installer + blockmap, `releases.json` (`latest: 2.3.0`, appended and sorted), root `latest.yml`, and the versioned feed `v2.3.0/latest.yml` (`../` prefix). `GET /desktop/idexal-os-setup-2.3.0-x64.exe` → `200` (`content-length` matches), `/desktop/latest.yml`, `/desktop/releases.json`, `/desktop/v2.3.0/latest.yml` → `200`, `/download` links the new file, and `GET /v1/app-version` reports `latestAppVersion: 2.3.0`.
- Public GitHub release `v2.3.0` published on `idexal/idexal-OS` (`idexal Os 2.3.0`) with `idexal-OS-Setup-2.3.0.exe`, `idexal-OS-Setup-2.3.0.exe.blockmap`, `latest.yml`, and `SHA256SUMS` (sha256 `59b9349b64ede40968e5c8107434e8befc1e096167e836ed230d3e5732c42835`).
- **Leaked credential removed:** a live full-scope GitHub PAT had been committed into `IMPLEMENTATION_REPORT.md`, `PRODUCTION_SERVER_SETUP.md`, and `DEPLOYMENT_CHECKLIST.md`. The three files now carry a placeholder (`fix(docs): stop committing a live GitHub token`), the server's clone remote URL was reset without the token, and the disposable server clone was deleted. The token remains in history, so it must be rotated.

#### Title

A dead provider stops being the first attempt of every message, and a combo stops storing a plan it never had.

#### One-line summary

The gateway now remembers which provider and model just failed twice and tries them last instead of first, so a healthy backup answers immediately; the admin sees which targets are cooling down, and a green health check returns them to the front at once. A combo no longer keeps a copy of its members' plans — it is offered through its members — so nothing can go stale and empty a workspace's catalog again. The desktop drops the client-side failover code that never ran and shows an honest, translated empty state when its workspace has no models. The three Den dashboard addresses the desktop opens — including "Manage organization models" — resolve on the self-hosted platform too, on a read-only Models page that lists exactly what the app receives.

#### Platform changes

- **A combo stores no plan rows at all — at either level.** `ComboModelService` no longer writes `plan_ai_model` rows when it builds an automatic or manual combo, and `AdminPlanController` drops any combo provider or hybrid model an operator posts to a plan while no longer listing them in the plans form. The same rule now holds for the provider level: the synthetic `idexal-combo` connection is never plan-gated in `GatewayService::limitProvidersToPlan()` or `desktopExclusionReason()`, so a stale `plan_ai_provider` row on that connection can no longer hide every combo at once — the sneakier copy of the model-level failure, one level up. Because the inference path (`resolveModels()`) now shares the same provider gate, a combo whose connection carries that stale row is still resolved and answered, not just listed. A combo is offered while at least one member is entitled and hides those members only while it is offered, so the copy that used to go stale on the next plan edit is gone at the source. The combos screen and the plans form both state the rule in place.
- **A migration removes the copies that already exist.** `2026_11_06_000000_purge_combo_plan_assignments` deletes every `plan_ai_model` row that points at a model on the `idexal-combo` connection or at any hybrid model, and every `plan_ai_provider` row that points at the `idexal-combo` connection. It is derived data with a single source of truth (the members' own plans), so the `down()` intentionally restores nothing.
- **A cooldown for targets that just failed.** `ProviderCooldownService` counts retryable failures per provider + model: two within five minutes mark the target cooling for two minutes, and the first success clears it. State lives in the cache, so this release adds no cache migration and the marker expires on its own even if nobody intervenes.
- **Cooling targets are tried last, never dropped.** `ProviderFallbackService::order()` applies the cooldown as a stable partition *after* the operator's backup chain, so an explicit chain keeps its order inside both halves. When every candidate is cooling the list is returned untouched, which means a workspace with a single provider behaves exactly as before — this reorders, it never removes.
- **The attempt loops report what happened.** `GatewayService::complete()` and `::stream()` count a transport failure and any retryable upstream status (401/403/404/408/409/425/429/5xx) against the target and clear it on the first success. The failure is counted even on the last candidate, so the admin view tells the truth even when there is nothing to fall back to.
- **The provider screen shows the state and lets you clear it.** `/admin/providers` renders a `Cooling down · N` badge with an explanation when a provider's targets are cooling. Running a health check that returns healthy releases that provider's cooling targets immediately (`clearForProvider`), so fixing a key and probing it once puts the provider back at the front instead of waiting out the cooldown.
- **The combos editor can no longer show a short list.** A combo counted every stored member, but the editor only offered checkboxes for members that were still active — deactivate or delete one and the row said "3 members" while two boxes appeared, and the next save silently dropped the third. `AdminComboController::index()` now loads the stored members that are no longer eligible, the row carries a `N unavailable` badge, and the edit panel names them with a note that saving removes them. The stored count and the visible list can no longer disagree.
- **No change to what a request returns.** A cooling target still answers when it is the only one, an operator's chain is still honoured, and no provider, model or combo is hidden from any list.
- **The desktop's model page resolves on the self-hosted platform.** The desktop opens "Manage organization models" at `/dashboard/custom-llm-providers`, a den-web address; the self-hosted platform answered `404`, so that button — and the two links built from `DEN_DASHBOARD_LINKS` (`/dashboard/ai-gateway?tab=ai-providers`, `/dashboard/library?show=models`) — led to a dead page. A workspace-facing **Models** page (`Dashboard\ModelController`, `GET /dashboard/models`, with a `ui.dashboard.nav.models` sidebar item) now lists exactly what the desktop receives — `GatewayService::desktopGatewayModels()`, one row per model id, combos included with their member count — read-only, because publishing providers is the operator's job. `/dashboard/custom-llm-providers` renders that page and the two other addresses redirect to it, so no desktop link is dead. Its empty state names the real cause and offers an operator the provider and catalog screens while a member is offered only Support; a provider key is never printed.

#### Desktop / server changes

- **Removed two client-side failover subsystems that never ran.** `apps/app/src/app/lib/model-fallback-manager.ts` (444 lines, an internal circuit breaker and health poller with zero importers) and `apps/desktop/src/services/DesktopFailoverService.ts` (no importers either) both claimed to do routing the gateway already owns, and neither was ever wired in. The live path — `model-unavailable-retry.ts`, which replays one rejected prompt on the next configured model — is untouched.
- **An honest empty state when a workspace has no models.** The model picker used to show "Nothing is connected in this workspace." — with no action — even when the workspace was managed and nothing could be connected there, contradicting the message right above it. It now says the workspace's organization has not published any models, offers a **Refresh organization models** button wired to the same re-fetch the composer uses, and keeps the connect copy for a workspace that really can add a provider. The dead `resolveModelPickerEmptyState` helper (written but never rendered) is gone.
- **An org admin can fix the empty catalog where it is published.** The settings URL the picker computed for a Den org admin was passed to the dialog and never used. That same empty state now shows a **Manage organization models** button for an admin, opening the organization's model page in the browser through the desktop's safe external-URL opener; members see no such action, and a workspace that can connect a provider keeps only the connect copy. The previously unused `models.manage_organization_models` string is now the button's label, so no new copy was invented.
- **The composer's own picker says the same thing as the dialog.** Only the separate full-picker dialog received the "organization published nothing" signal; the picker opened from the message box never did, so it kept the misleading "Connect a provider, or turn Auto back on" line for a managed workspace and wired its Retry to the local provider-list refetch, which cannot change an org-published catalog. The composer now threads the flag through and, for that case, points the picker's retry at the organization sync (`refreshCloudProviderSync`) instead, so the empty state names the real cause and its button actually re-pulls the models. `tests/model-select-org-empty.test.tsx` proves the flag reaches the shared list and the retry is the organization refresh, not the local one; both cases fail before the fix.
- **Both pickers offer the admin the same way out.** The full dialog let a Den org admin open the organization's model page, but the inline picker in the message box never received that action, so the same empty state showed one fewer control depending on where it was opened. `ModelSelect` now forwards `onManageOrganizationModels`, closes the popover before opening the page, and passes nothing for a member; `session-route` builds the action once from the admin's settings URL and hands it to both composer pickers. `tests/model-select-org-empty.test.tsx` grew to 4 tests: the action reaches the inline picker and closes it first, and a member is offered no such control.
- `scripts/comprehensive-testing-suite.sh` Phase 4 now checks that live path instead of the deleted file.
- Version stamped to **2.3.0** across `apps/app`, `apps/desktop`, `apps/server`, the desktop server, the sidecar manifest, the release review guard and the platform `idexal.version`.

#### Verified before deploy

- Laravel: `379 tests / 2343 assertions` (Feature + Unit), Pint clean.
- The platform → desktop managed-model contract is proven end to end and now guarded on both sides. A live run of the desktop's own parser (`apps/server/src/cloud-provider-sync.ts`) against a real `GET /v1/inference-providers?scope=usable` response from a local platform returned all three organization models with zero drops, and `…/{provider}/connect` answered `200` with an `ow_gw_` token — so the wire id, the segment consistency and the credential hand-off are correct as shipped, and the "our models do not appear" report is a deployment gap, not a wire defect (see the deploy checklist). `DesktopCloudProviderTest` gained `test_the_wire_model_id_is_the_canonical_alias_and_ignores_a_slashy_model_id` plus the `assertCanonicalGatewayAlias` invariant (lowercase Crockford `gwm_`/`gmg_`/`gcs_`, 84-char alias, segment agreement, no namespaced `model_id` leakage) applied to every row; reverting the id to uppercase fails it. `apps/server/test/laravel-gateway-contract.test.ts` (4 tests) runs the real desktop parser over the platform's literal payload, proves an uppercased id is dropped as `invalid_gateway_model` rather than silently accepted, and proves a `config.id` mismatch and a missing `credentialSetName` are named instead of hidden.
- New coverage: `ProviderCooldownTest` (7 tests) — the threshold and its return value, a success clearing both the cooldown and the failure run, the stable partition keeping the chain order and moving only the cooling target, every candidate cooling leaving the order untouched, an HTTP-level case where the third request starts with the healthy provider and the cooled one is not called, a single provider still being tried while cooling (three upstream calls for three requests), the admin badge and hint rendering only when cooling, and a green health check clearing the cooldown. `ComboModelTest` proves an automatic and a manual combo store no plan rows after their members are assigned to a plan, that the purge migration clears the stale `plan_ai_model` and `plan_ai_provider` rows while leaving the members' own entitlements intact, and `AdminPlanProviderAssignmentTest` proves a posted combo id and its provider are dropped while the plans form neither lists the combo provider nor a hybrid model. `CatalogDiagnosticsTest` proves a workspace keeps its combo when the combo connection itself carries a stale plan row, and `ComboModelTest` proves the same connection row does not block an actual `gwm_` chat call. `ComboEditorTest` gained two cases proving a deactivated member and a deleted member each show as unavailable (named, never a bare count that disagrees with the list) while saving keeps only the members that can still be offered. `DashboardModelTest` (5 tests) proves the models page lists what the workspace is entitled to without printing the provider key, names an empty workspace with a member's action only, sends a platform admin to the provider screen, resolves all three desktop addresses (`/dashboard/custom-llm-providers` renders, `/dashboard/library` and `/dashboard/ai-gateway` redirect to `/dashboard/models`), and never shows another workspace's private model — all five return `404` before the routes existed.
- Desktop: `bun test` over the i18n key and placeholder suites passes, so the picker's new copy resolves in every locale; `tsc --noEmit` is clean; `bun test` over the model suites — 76 pass / 0 fail; `tests/model-picker-empty-state.test.tsx` (3 tests) proves the empty state names the cause, offers the admin the publishing screen and fires it on click, offers a member no such action, and keeps the connect copy for a workspace that can add a provider; `tests/model-select-org-empty.test.tsx` (4 tests) proves the composer's inline picker receives the org-empty flag and retries through the organization sync rather than the local provider list, that an org admin's manage action reaches that picker and closes it first, and that a member is offered no such control.
- Desktop server: `apps/server` `tsc -p tsconfig.json --noEmit` is clean and `bun test ./test/laravel-gateway-contract.test.ts ./test/inference-provider-parse.test.ts` passes (8 tests), so the parser that consumes the platform's payload is verified against the platform's real shape on the desktop side too.

#### Deploy checklist (blocking)

1. `git pull` on the platform host, then `php artisan migrate --force` — six migrations are pending there: `add_meta_to_gateway_requests_table`, `add_feature_overrides_to_ai_providers_table`, `add_feature_overrides_to_ai_models_table`, `fix_extension_installations_user_key`, `create_agent_permission_policies_table`, and this release's `purge_combo_plan_assignments` (it clears the stale `plan_ai_model` copies left on combos and the stale `plan_ai_provider` row on the `idexal-combo` connection).
2. `php artisan config:clear && php artisan view:clear && php artisan cache:clear` with `/opt/plesk/php/8.4/bin/php`.
3. `python scripts/release/publish-desktop.py --version 2.3.0 --platform windows --arch x64 --file <installer> --notes "<one-line summary>"` with `IDE_RELEASE_SSH_HOST/USER/PASSWORD` set.
4. Open `/admin/catalog?organization=<a fresh workspace>` and confirm the models it is entitled to are listed, with members of an offered combo marked `combo_member_hidden` and any unreachable combo marked `combo_members_unavailable` — never a raw key; and that a workspace with nothing visible opens with the "would see no models" warning.
5. Open `/admin/combos`, edit one combo without touching its members, save, and confirm the slug, members, roles and order are unchanged; confirm any combo whose member is no longer active shows the `unavailable` badge and names that member in the edit panel.
6. Open `/admin/plans`, edit a plan, and confirm the combo connection and its hybrid models are not listed (they cannot be entitled); save, then confirm both `select count(*) from plan_ai_model join ai_models on ai_models.id = plan_ai_model.ai_model_id where ai_models.hybrid_config is not null` and `select count(*) from plan_ai_provider join ai_providers on ai_providers.id = plan_ai_provider.ai_provider_id where ai_providers.slug = 'idexal-combo'` are `0`.
7. With a provider whose key is deliberately wrong, send two messages, then reload `/admin/providers` and confirm the `Cooling down` badge appears; press "Check now" with the key fixed and confirm the badge disappears.
8. In an organization with no models published, open the model picker from the message box (not the full picker dialog) and confirm the empty state names the organization and its **Refresh organization models** button actually re-pulls the models (`refreshCloudProviderSync`), rather than the local provider-list refetch that changes nothing; as an org admin, confirm the same picker offers **Manage organization models** and it opens the Models page from item 9.
9. Open `<platform>/dashboard/custom-llm-providers` (the exact address the desktop opens for "Manage organization models"), then `/dashboard/library?show=models` and `/dashboard/ai-gateway?tab=ai-providers`, and confirm none of them answers `404` any more — the first renders the Models page, the other two redirect to `/dashboard/models`.

#### Known gaps

- The cooldown is per provider + model and kept in the process cache, so on a multi-node deployment each node keeps its own view. This platform runs one node; a shared store (Redis) is the fix if that changes.
- Cooling is invisible to the desktop: it is not told which models are temporarily de-prioritized, and a request can still be answered by the last candidate when every target is cooling.
- Teams do not exist on this platform, so a policy is one scope per workspace; the desktop's per-team overrides have no source here.
- No per-member quota for combo members: rotation and ordering are now health-aware, but a member that is out of credit is still discovered by trying it.
- A combo can no longer be entitled on its own. To keep a combo off a plan, restrict its member models (or their provider) — the combo follows its members. This is deliberate, but it is a behaviour an operator who previously ticked a combo in the plans form will notice.
- The workspace **Models** page is read-only: it resolves the desktop's dead link and answers "what can this workspace use", but publishing a provider still happens in `/admin/providers`. A workspace admin who is not also a platform admin is offered Support, not a dead-end edit screen.

## v2.2.9

#### Commit

The working-tree changes below (combo editor, one authority for what a workspace sees), on top of `5d627ff`. Supersedes the unpublished v2.2.8 and v2.2.7: none of them has reached the platform host, so this entry is the first one that ships the agent-permissions, desktop-switch, marketplace, combo-editor and model-visibility work together.

#### Released at

`pending` — waiting on the platform host credential for `os.idexal.com/app`.

#### Title

A new workspace sees its models, and a combo can be edited without losing what it held.

#### One-line summary

Two defects that both emptied or mangled a workspace: a combo's stale plan rows could hide every model from a brand-new desktop, and editing a combo silently dropped the fields the form did not resend. Both are closed, and the diagnostics page names the new reason instead of printing a raw key.

#### Platform changes

- **One authority for visibility.** `GatewayService::desktopVisibility()` now decides what an organization may see from the gates alone (active model, active provider, driver, workspace, provider plan, model plan, credential). `/v1/models`, the managed desktop list and the diagnostics page all read it, so they can no longer disagree.
- **A combo carries no entitlement of its own.** Its stored plan rows are only a copy of its members' assignments from the moment it was built. A combo is now offered when at least one member reached the workspace, and only a combo that is offered hides its members. A stale per-combo plan row can no longer empty a catalog — which is exactly how a new workspace ended up with zero models: the combo was filtered out on a plan it no longer matched while its members were already hidden by it.
- **A new diagnostic reason.** When no member of a combo is reachable, the catalog page reports `combo_members_unavailable` (en + ar) with the fix, instead of mislabeling it as a plan mismatch. `combo_member_hidden` now appears only for members of a combo the workspace actually received.
- **Combos are editable in place.** The combos screen gains an honest inline edit panel for every combo. An edit inherits every value the form does not send (slug, members, roles, weights, fallback chain, attempt order), so a rename can no longer reset routing. Each member row has an attempt-order input (1 = first), and a rejected edit reopens the panel with the typed values and the exact field errors instead of starting over.
- **`/v1/models` stays wider on purpose.** It keeps every driver and does not require a credential (an API client may be calling `/v1/messages`), but it derives combos and hidden members the same way. The raw list and the desktop list can no longer describe two different products.
- **The catalog page says when a workspace would see nothing.** When a workspace's desktop catalog is empty, `/admin/catalog` now opens with a warning and a per-reason count, so an operator sees the new-sign-up failure before a user reports it instead of having to read the reason column row by row.

#### Desktop / server changes

- No desktop code changed: it calls the same endpoints and now receives the models a fresh workspace is entitled to.
- Version stamped to **2.2.9** across `apps/app`, `apps/desktop`, `apps/server`, the desktop server, the sidecar manifest, the release review guard and the platform `idexal.version`.

#### Verified before deploy

- Laravel: `342 tests / 2170 assertions` (Feature) and `15 tests / 31 assertions` (Unit), Pint clean.
- New coverage: `ComboEditorTest` (8 tests) — slug and members survive an edit, roles/weights/fallback chain survive, attempt order beats provider priority and can be swapped, nested-combo rejection, a rejected edit reopens the panel with typed values, audit entry, Arabic rendering.
- New coverage: `CatalogDiagnosticsTest` (7 tests) — a fresh workspace keeps its models when a combo carries a stale plan (combo shown, members hidden, `assertCatalogMatchesDiagnoses`), a combo with no reachable member is reported `combo_members_unavailable` while its members keep their own reason and are never hidden, and the admin page warns only when the workspace can see nothing.
- New coverage: `DesktopCloudProviderTest::test_a_fresh_workspace_sees_its_combo_even_when_the_combo_carries_a_stale_plan` — the real desktop contract end to end: registration builds the workspace, `/v1/inference-providers` returns the combo (and not its hidden members), and a `gwm_` chat call resolves the combo to a member despite its stale plan rows.

#### Deploy checklist (blocking)

1. `git pull` on the platform host, then `php artisan migrate --force` — five migrations are still pending there: `add_meta_to_gateway_requests_table`, `add_feature_overrides_to_ai_providers_table`, `add_feature_overrides_to_ai_models_table`, `fix_extension_installations_user_key`, `create_agent_permission_policies_table`.
2. `php artisan config:clear && php artisan view:clear && php artisan cache:clear` with `/opt/plesk/php/8.4/bin/php`.
3. `python scripts/release/publish-desktop.py --version 2.2.9 --platform windows --arch x64 --file <installer> --notes "<one-line summary>"` with `IDE_RELEASE_SSH_HOST/USER/PASSWORD` set.
4. Open `/admin/catalog?organization=<a fresh workspace>` and confirm the models it is entitled to are listed, with members of an offered combo marked `combo_member_hidden` and any unreachable combo marked `combo_members_unavailable` — never a raw key; and that a workspace with nothing visible opens with the "would see no models" warning.
5. Open `/admin/combos`, edit one combo without touching its members, save, and confirm the slug, members, roles and order are unchanged.

#### Known gaps

- Teams do not exist on this platform, so a policy is one scope per workspace; the desktop's per-team overrides have no source here.
- No per-member health, cooldown or quota for combo members; rotation relies on in-request failover.
- `agent_permissions` is not tied to a plan feature. Turning it into a paid capability would mean one column on `plans`, one check in `MeController`, and the screen staying the only producer.

## v2.2.8

#### Commit

The commit that adds this entry (`feat(permissions): let an operator decide what agents may do`), on top of `6ddc9a2`. Supersedes the unpublished v2.2.7: neither release has reached the platform host, so this entry is the one that ships both.

#### Released at

`pending` — waiting on the platform host credential for `os.idexal.com-app`.

#### Title

Agent permissions that actually reach the computer.

#### One-line summary

The desktop has always read "Ask first" and "Block" rules and enforced them; the platform never sent any. It now has a screen that produces them, one ordered list per workspace, and the exact rules are shown before they are saved.

#### Platform changes

- **`/admin/agent-permissions`.** One card per family — Commands, Files, Web, Skills and MCP servers, Desktop app — with a picklist (Allow / Ask first / Block) or a switch, and "Always allowed" / "Always blocked" lists for commands and sites. Ships allowing everything: a workspace that never opens the screen behaves exactly as before.
- **`agentPermissions.rules` in `/v1/me/desktop-config`.** The ordered list the desktop passes to its permission engine, built from one resolver (`AgentPermissionPolicy`) so the admin preview, the API response and the audit all show the same rules. A decision becomes a `*` rule, its allowed patterns follow as exceptions, blocked patterns come last so a block always wins, and blocking file edits appends a command block last (any command can change files).
- **Websites are stored in the form the desktop matches.** `HTTPS://Example.COM./docs/` is stored as `https://example.com/docs`, a trailing `/*` or `/` adds nothing, and a pattern the desktop cannot parse is rejected in the form instead of silently dropped.
- **A switch never asks.** A switch offers only allow and block; a stored "ask" is saved as block, so a family can never end up prompting for something the screen cannot configure.
- **Audited by name.** A save records which permissions moved with a before/after line each (`Run commands: Not restricted → Ask first`), the audit page renders them as a table, and an unchanged save writes no entry.
- **Workspace isolation.** Rules are stored per workspace (`agent_permission_policies`, ULID keys) and one workspace's policy never reaches another; a missing row is the default policy, not an error.

#### Desktop / server changes

- No desktop code changed: it already read and enforced these rules for app settings, proxies, extensions, the built-in browser, and — on the newer engine — commands, edits, fetches and searches. The screen simply fills in what was always missing.
- Version stamped to **2.2.8** across `apps/app`, `apps/desktop`, `apps/server`, the desktop server, the sidecar manifest, the release review guard and the platform `idexal.version`.

#### Verified before deploy

- Laravel: `330 tests / 2049 assertions` (Feature) and `15 tests / 31 assertions` (Unit), Pint clean.
- New coverage: `AgentPermissionsTest` (14 tests) — empty by default, every emitted rule matches the desktop's strict schema, decision/exception/block ordering, edits→commands, switch coercion, website normalization, invalid pattern rejected, audit entry and description, unchanged save inert, screen lists every family and the rules it will send, non-admin refused, cross-workspace isolation, Arabic rendering.

#### Deploy checklist (blocking)

1. `git pull` on the platform host, then `php artisan migrate --force` — five migrations are still pending there: `add_meta_to_gateway_requests_table`, `add_feature_overrides_to_ai_providers_table`, `add_feature_overrides_to_ai_models_table`, `fix_extension_installations_user_key`, `create_agent_permission_policies_table`.
2. `php artisan config:clear && php artisan view:clear && php artisan cache:clear` with `/opt/plesk/php/8.4/bin/php`.
3. `python scripts/release/publish-desktop.py --version 2.2.8 --platform windows --arch x64 --file <installer> --notes "<one-line summary>"` with `IDE_RELEASE_SSH_HOST/USER/PASSWORD` set.
4. Open `/admin/agent-permissions` once and confirm the workspace picker and the empty state render in both locales.

#### Known gaps

- Teams do not exist on this platform, so a policy is one scope per workspace; the desktop's per-team overrides have no source here.
- No per-member health, cooldown or quota for combo members; rotation relies on in-request failover. (2.2.9 adds the combo edit screen.)
- `agent_permissions` is not tied to a plan feature. Turning it into a paid capability would mean one column on `plans`, one check in `MeController`, and the screen staying the only producer.

## v2.2.7

#### Commit

`f029e2e` (marketplace + ULID keys) and the working-tree changes below (desktop switches, version stamp). Supersedes the unpublished v2.2.6 (`951bc43`): that tracker never left `pending`, so this entry is the first one that ships the combos, audit, feature-pipeline and marketplace work together.

#### Released at

`pending` — waiting on the platform host credential for `os.idexal.com-app`.

#### Title

A platform that tells every desktop what it can do, and an admin panel that reads like one product.

#### One-line summary

The platform now publishes every desktop switch it owns — including the nine experiences that were permanently off because no endpoint named their keys — and both feature endpoints answer from one map, so a signed-in desktop sees exactly what the deployment serves.

#### Platform changes

- **Desktop switches on the Features page.** Eight experiences (App build progress, background tasks in chat, connection-failure presentation, last-reply model footer, model-removal recovery, remember last organization, secure keys in chat, tasks on other computers) are operator-owned toggles. They ship off, are published through `GET /v1/features` and `GET /v1/org`, and reach a running desktop on its next check. `engineV2Upgrade` is deliberately never published: this deployment runs the v1 engine.
- **One feature map.** `/v1/features` and `/v1/org` now build their answer from `PlatformFeatures`, so the signed-in map can no longer answer `library: false` while the deployment map says `true`.
- **Extensions marketplace rebuilt.** `admin/extensions` is localized (en + ar) and on the design system: tabs per type with counts, server-side search across name, slug, description and tags, an installed strip, and per-card install/remove.
- **Marketplace ownership fixed.** `extension_installations` was the only table keying a user with an integer while users are ULIDs; every account collapsed onto one owner and the second account could not install. Set/remove is now idempotent and keyed by ULID, and a migration replaces the corrupt table where it still exists.
- **Audit reads like a table.** Each entry names the setting that moved, with a before/after pair, a person filter, a period filter, and reusable field labels for all 52 written keys (24 of them social-provider keys).
- **Features panel answers "is it working".** Every card shows requests this week against the week before, an override count per provider and model, and a "Switched this week" table naming the operator and the resulting state.
- **Arabic is a first-class locale.** `lang/ar/validation.php` and `lang/ar/passwords.php` mirror the framework table, and a guard fails the suite when an English sentence is used as a translation key.
- **Gateway feature pipeline** (from the same release line): eight platform features applied to every request through one choke point, pinned per provider and per model with three-state overrides, billed web search, and a hybrid combos builder.
- **Operations** (same release line): real payment gateways, social sign-in, referrals, support desk, provider key rotation with fallback chains and health probes, real-time platform-to-desktop sync, and a desktop catalog that explains why a model is missing.

#### Desktop / server changes

- The desktop gains nothing to configure: it already read these keys, so the switches light up existing surfaces.
- Version stamped to **2.2.7** across `apps/app`, `apps/desktop`, `apps/server`, the desktop server, the sidecar manifest, the release review guard and the platform `idexal.version`.
- `desktop-free-release.mjs` and `sidecars/versions.json` follow the same stamp.

#### Verified before deploy

- Laravel: `316 tests / 1949 assertions` (Feature) and `15 tests / 31 assertions` (Unit), Pint clean.
- New coverage: `DesktopSwitchesTest` (both endpoints agree, a switch reaches both, audit entry, page listing, engine key never published), `AdminExtensionsTest` (list, search, empty, install/remove, marking, cross-account isolation, double install, refused slug, missing installation, refresh), `AuditFieldLabelTest` walking every written key including the desktop switches.
- No new migration is required for the switches: they are settings rows, created on first save.

#### Deploy checklist (blocking)

1. `git pull` on the platform host, then `php artisan migrate --force` — four migrations are still pending there: `add_meta_to_gateway_requests_table`, `add_feature_overrides_to_ai_providers_table`, `add_feature_overrides_to_ai_models_table`, `fix_extension_installations_user_key`.
2. `php artisan config:clear && php artisan view:clear && php artisan cache:clear` with `/opt/plesk/php/8.4/bin/php`.
3. `python scripts/release/publish-desktop.py --version 2.2.7 --platform windows --arch x64 --file <installer> --notes "<one-line summary>"` with `IDE_RELEASE_SSH_HOST/USER/PASSWORD` set; it writes `public/desktop/releases.json` and the updater manifests.
4. Open `/admin/features` once and confirm the eight switches render in both locales.

#### Known gaps

- `agentPermissions` is still read by the desktop and never emitted by this platform, so "Ask first / Block" rules remain inert. Next contract to close.
- No per-member health, cooldown or quota for combo members; rotation relies on in-request failover.
