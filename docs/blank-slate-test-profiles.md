# Blank-slate test profiles

Every desktop build (public, cloud, enterprise) accepts a `--blank-slate` CLI
flag that launches the app as if on a brand-new machine, without touching the
installed profile.

## Usage

Packaged app (macOS):

```bash
open -a "idexal Os Enterprise" --args --blank-slate
# or any flavor:
open -a "idexal Os" --args --blank-slate
```

Direct binary (useful for CDP-driven testing):

```bash
IDEXAL_ELECTRON_REMOTE_DEBUG_PORT=9898 \
  "dist-electron/mac-arm64/idexal Os Enterprise.app/Contents/MacOS/idexal Os Enterprise" --blank-slate
```

Dev mode:

```bash
pnpm dev:electron -- --blank-slate
```

## What it does

Each launch creates one unique temporary root (`$TMPDIR/idexal-os-test-profile-*`)
and redirects every persisted path into it before any other module loads:

- Electron `userData` and `home`
- `HOME`/`USERPROFILE`, `XDG_CONFIG_HOME`/`DATA`/`CACHE`/`STATE`,
  `APPDATA`/`LOCALAPPDATA`
- desktop bootstrap (`IDEXAL_DESKTOP_BOOTSTRAP_PATH`) — an enterprise build
  therefore starts at the activation gate, not an inherited control plane
- idexal Os server config, env store, token store, runtime DB, data dir
- OpenCode config dir and database

It also:

- enables Chromium's mock keychain so the real login keychain is never touched
- skips `idexalos://` protocol registration, Windows shortcut writes, brand
  icon/name persistence, and Linux desktop integration
- suffixes the window title with `Test profile`
- spawns a detached cleanup worker on quit that removes the whole temporary
  root after the app exits

A normal launch (no flag) is byte-for-byte unchanged. Packaged builds keep
their immutable flavor; the flag never alters sign-in or activation policy.

## Source

- `apps/desktop/electron/blank-slate-profile.mjs` — profile resolution (applied
  as the first import in `main.mjs`)
- `apps/desktop/electron/blank-slate-cleanup.mjs` — post-exit cleanup worker
- `evals/specs/desktop-blank-slate-profile.test.ts` — testkit proof
