# idexal Os Bootstrap CLI

Script-installable `idexal-os-bootstrap` command for agent-first onboarding.

This package is intentionally small and does not assume npm is the install
channel. A bootstrap script can place `bin/idexal-os.mjs` on disk, then run:

```bash
idexal-os-bootstrap install --bin-dir ~/.local/bin --install-dir ~/.idexalos/bootstrap
idexal-os-bootstrap doctor --json
idexal-os-bootstrap install app --manifest https://example.com/idexal-os-install-manifest.json
idexal-os-bootstrap doctor --app --json
idexal-os-bootstrap login --base-url https://den.example.com
idexal-os-bootstrap cloud onboard --base-url https://den.example.com --org-name "Ada Workspace" --teammate-emails teammate@example.com --skill-name 'First skill' --json
```

Current scope:

- `install` installs the lightweight CLI into a user-writable bin directory.
- `install app` downloads a manifest-selected desktop app artifact, verifies its
  SHA-256 digest, and installs it into a user-writable app directory.
  Supported artifact types: macOS `.dmg`, `.zip`, `.tar.gz`/`.tgz`, Linux
  `.AppImage`, and Windows `.exe`/`.msi` copy-installs.
- `doctor` verifies the CLI install and, optionally, a Den API health endpoint.
- `login` signs in with the OAuth 2.0 device authorization grant (RFC 8628):
  it prints a link and a one-time code, the person approves in the browser, and
  the session is saved to `~/.idexalos/credentials.json` (mode 0600, override
  with `IDEXAL_CREDENTIALS_PATH`). `IDEXAL_API_TOKEN` takes precedence.
  `logout` revokes the session and deletes the file.
- `cloud onboard` creates an org, invites a teammate, and creates a starter
  skill as the signed-in person. The `--owner-email`/`--owner-password*` flags
  still work but are deprecated: passwords end up in shell history. On the deprecated
  password path, hosted Cloud still needs the two-step email code
  (`--request-code`, then `--verification-code <code>` or
  `--verification-code-stdin`).

This is a bootstrap layer for install and Cloud onboarding; runtime hosting uses the desktop app, idexal Os Cloud, or `idexal-os-server`.
