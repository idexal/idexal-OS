# Release Changelog Tracker

Internal preparation file for release summaries. Not published until the deploy completes.

## v2.2.6

#### Commit
`951bc43` plus the uncommitted working-tree changes listed below (combos layer, catalog diagnostics, provider-sync resilience). Nothing has been committed yet.

#### Released at
`pending` — waiting on the SFTP credential for `os.idexal.com-app`.

#### Title
A desktop catalog you can explain, and combos that survive naming differences.

#### One-line summary
Every model in the desktop catalog now names the gate that hid it, one unreadable model no longer takes its whole provider down, and the same model offered by different vendors groups into one combo even when they spell it differently.

#### Platform changes

- New admin page **Desktop catalog** (`/admin/catalog`) lists every model with the exact reason it does or does not reach a desktop for a chosen workspace: inactive model/provider, non-desktop driver, other workspace, provider/model plan mismatch, hidden inside a combo, missing credential, or a duplicate id won by a higher-priority provider. Each reason carries the fix.
- Manual combos hide their members, so a combo named `idexal-coding` is the only row a user sees; deleting or deactivating the combo reveals the members again.
- Automatic combos group by family identity instead of raw string equality: vendor prefixes, separator style and trailing build stamps no longer split one model into two rows (`deepseek-ai/DeepSeek-V4-Flash-0731` == `DeepSeek-V4-Flash`), while size and variant tokens stay distinct (`DeepSeek-V4` != `DeepSeek-V4-Flash`).
- Workspace-scoped models no longer join global automatic combos.
- A combo can no longer contain another combo, and a combo id can no longer shadow a live provider model id.

#### Desktop / server changes

- `@idexal/server` cloud provider sync drops only the unreadable model row instead of rejecting the entire provider, and reports it in `skippedModels` on `GET /cloud-provider-sync/status`.

#### Verified before deploy

- Laravel: `104 tests / 623 assertions`, Pint clean.
- `@idexal/server`: 4 parser tests pass; `tsc --noEmit` clean.
- Local platform over HTTP: 13 admin pages and 4 dashboard pages all return 200.
- Desktop handoff end to end: `dh_` grant issued, exchange 200, token accepted by `/v1/me/desktop-config`, `/v1/inference-providers` (1 gateway provider, 3 models), `/v1/models`, `/v1/gateway/usage-limits/me`, `/v1/org`.
- Live data: combos rebuild produced exactly one combo, `combo-deepseek-v4-flash`, from `idexal-hub/DeepSeek-V4-Flash` and `gonkarouter/deepseek-ai/DeepSeek-V4-Flash-0731`.

#### Deploy checklist (blocking)

1. `php artisan migrate` on the platform host — four migrations are still pending in production copies: `workspace_memories`, `reasoning_options`, `free_inference_credentials`, `hybrid_config`.
2. Upload the application (including the untracked combos files) and the built `public/build`.
3. `python scripts/release/publish-desktop.py --version 2.2.6 --platform windows --arch x64 --file <installer> --notes "<one-line summary>"` with `IDE_RELEASE_SSH_HOST/USER/PASSWORD` set; it writes `public/desktop/releases.json` and the updater manifests.
4. Clear config/route/view caches after upload.

#### Known gaps

- No edit screen for a combo (delete and recreate only).
- No per-member health/cooldown or per-member quota; rotation relies on in-request failover.
- Web `/v1/features` and `/v1/org` feature keys still do not match the keys the desktop reads.
- `agentPermissions` is read by the desktop but never emitted by this platform, so "Ask first / Block" rules are inert.
