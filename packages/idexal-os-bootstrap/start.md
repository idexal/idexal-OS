# idexal Os Start

You are an agent helping a user install and set up idexal Os.

Goal: install idexal Os, create a provisional workspace without requiring email
identity first, create a first skill, prepare the desktop app with that
workspace/skill, verify the running app opens to the setup-complete state, and
offer to connect the idexal Os MCP to the agent the user is already using.

> The bootstrap command is `idexal-os-bootstrap`. Use that exact command for
> these setup steps so the bootstrap flow stays distinct from other idexal Os
> tooling a user may already have on their PATH.

> Working without a person present (CI, a background agent)? Follow
> https://os.idexal.com/auth.md instead: it is the agent sign-in protocol
> (register, use, hand over a claim later).

## 1. Install CLI

Install the `idexal-os-bootstrap` CLI into a user-writable bin dir.

If you already have this repository locally:

```bash
node packages/idexal-os-bootstrap/bin/idexal-os.mjs install \
  --install-dir "$HOME/.idexalos/bootstrap" \
  --bin-dir "$HOME/.local/bin" \
  --json
```

In production, download the bootstrap script, inspect it, then run it. Do not
pipe remote scripts directly into a shell. The script downloads the
`idexal-os-bootstrap` CLI (a single dependency-free Node file) and installs it
into `$HOME/.local/bin` - no npm or npx required. If Node.js 20+ is missing,
the script downloads the official Node.js build into
`$HOME/.idexalos/bootstrap/node` (checksum-verified, no admin rights), so the
user does not need to install anything first.

```bash
curl -fsSLo /tmp/idexal-os-install.sh https://os.idexal.com/install.sh
less /tmp/idexal-os-install.sh
sh /tmp/idexal-os-install.sh
```

Verify:

```bash
idexal-os-bootstrap doctor --json
```

## 2. Install App

Install the desktop app for this OS from the manifest:

```bash
idexal-os-bootstrap install app \
  --manifest https://os.idexal.com/install-manifest.json \
  --json
```

Verify:

```bash
idexal-os-bootstrap doctor --app --json
```

## 3. Create Cloud Workspace

For agent-first setup where email identity should not block desktop readiness,
create a provisional workspace first. This does not create an email/password
account. It writes claim links to the local desktop bootstrap file so a human can
claim ownership later.

Optionally ask the user for their own email address first. It is only used to
pre-fill the claim page later (not a security boundary, not an account, no
password) - skip it if the user does not want to share it. Also optionally ask
for teammate email addresses to invite. Invites only go out once the workspace
is claimed (a provisional workspace has no authenticated owner yet to send them
as) - they fire automatically the moment a human claims ownership.

```bash
idexal-os-bootstrap cloud bootstrap-workspace \
  --base-url https://api.idexal.com \
  --workspace-name "<workspace-name>" \
  --skill-name "First idexal Os Skill" \
  --claim-roles owner \
  --prepare-desktop \
  [--owner-email "<email-if-given>"] \
  [--teammate-emails "<email1>,<email2>"] \
  --json
```

If the user wants to attach a real account immediately, finish this provisional
setup first, then use the `Claim workspace and continue` action in the desktop app. Do
not create an email/password account from the CLI during agent-first install.

### Signing in as an existing user (no password)

When the user already has an idexal Os account, or wants the CLI to act as them,
sign in with a one-time code instead of a password:

```bash
idexal-os-bootstrap login --base-url https://api.idexal.com --json
```

The command prints a link (`verification_uri_complete`) and a code such as
`ABCD-EFGH` on stderr. Show both to the user; they open the link, sign in or
create an account, check that the code matches, choose the organization, and
click `Sign in idexal Os CLI`. The command then saves the session to
`~/.idexalos/credentials.json` (owner-only) and prints who is signed in. If
`IDEXAL_API_TOKEN` is set, it is used instead and no code is shown.

After `login`, `idexal-os-bootstrap cloud onboard --base-url <url> --org-name
"<name>" [--teammate-emails <emails>] --json` creates the organization, any invitations,
and starter skill as that user. Never ask the user for their password.

Only if `login` is unavailable, the deprecated password path remains: `cloud
onboard --request-code` emails a 6-digit code, then rerun with
`--verification-code <code>` (or `--verification-code-stdin`). Ask the user for
the code; never guess it.

## 4. Launch the App

Open the desktop app so the user lands on the setup-complete screen with their
first skill ready. This works on macOS, Windows, and Linux:

```bash
idexal-os-bootstrap open app --json
```

The app opens to a green `Setup complete` banner with the workspace name and
`First skill ready`. The person is not signed in yet: a provisional workspace
has no owner until they claim it, so do not try to sign them in from the CLI.
On Linux the app may first ask to add itself to the application launcher; tell
the user to choose `Integrate` so browser sign-in can return to the app.

## 5. Finish Well (most important step)

Do NOT end by dumping readiness JSON or a list of `ok: true` checks. The user
does not care about flags — they want to know what to do next. End with a short,
friendly, human message that gives momentum:

1. Confirm in one line that idexal Os is installed and their workspace is ready
   (use the workspace name).
2. Give them the ONE next step: in the app, click `Claim workspace and
   continue` to make the workspace theirs (it signs them in and sends any
   teammate invites). Then suggest one first task, e.g. "try typing:
   'summarize the files in my Downloads folder' and hit Run."
3. Mention that teammates whose emails were already provided will be invited
   automatically once the workspace is claimed. If no emails were provided,
   offer to collect them later.
4. End with this single question: "Want me to connect the idexal Os MCP to this
   agent so you can manage your organization from here?"

Keep it to a few sentences. Warm, concrete, action-oriented. No JSON, no
checklists, no internal flag names in the final message.

If the user says yes:

1. A provisional workspace must be claimed before its owner can authenticate
   the idexal Os MCP. If it has not been claimed yet, explain that dependency and
   ask whether to open the claim step now. Retrieve and open the claim link only
   after the user confirms.
2. After the user has claimed the workspace and signed in, configure the MCP in
   the agent they are currently using. The server URL is:

   ```text
   https://api.idexal.com/mcp/agent
   ```

3. For Codex, run:

   ```bash
   codex mcp add idexalos --url https://api.idexal.com/mcp/agent
   codex mcp login idexalos
   ```

   If an `idexalos` entry already exists, do not add a duplicate. Authenticate
   the existing entry instead. To switch organizations or recover stale auth,
   run `codex mcp logout idexalos` before `codex mcp login idexalos`.
4. For another agent, use its current instructions from
   `https://os.idexal.com/docs/cloud/run-in-the-cloud/cloud-mcp`; do not guess
   unsupported client commands.
5. Tell the user to restart or reopen the current agent after setup so the new
   MCP tools are loaded. Do not claim the connection works until the restarted
   client can see idexal Os's `search_capabilities` and `execute_capability`
   tools.

## 6. Retrieving the Claim Link

Do not print the claim URL preemptively. Only retrieve it when the user
explicitly says they want to claim the workspace now (for example, after
asking "claim the workspace").

```bash
idexal-os-bootstrap cloud claim-link --role owner --json
```

Then open the returned `url` for the user (for example `open <url>` on macOS)
instead of pasting the raw link into chat.

## 7. Success Criteria (internal — do not show the user)

You are done only when all are true:

- `idexal-os-bootstrap doctor --json` returns `ok: true`
- `idexal-os-bootstrap doctor --app --json` returns `ok: true`
- `idexal-os-bootstrap cloud bootstrap-workspace ... --json` returns:
  - `ok: true`
  - `organization.id` is present
  - `setup.id` is present
  - `skill.id` is present
  - `skillRun.triggered` is `true`
  - `skillRun.output` is `IDEXAL_BOOTSTRAP_SKILL_TRIGGERED`
  - `claimLinks[0].id` is present
  - `desktop.prepared` is `true`
  - `desktop.bootstrapPath` is present
  - `desktop.skillPath` is present
- `idexal-os-bootstrap doctor --desktop-bootstrap --json` returns `ok: true`
- When the desktop app is launched, it lands on the onboarding screen showing a
  green "Setup complete" banner, the organization name, a "First skill ready"
  row, and a "Claim workspace and continue" action.

## 8. If Something Fails

- If CLI install fails: report OS, shell, command, and stderr.
- If the `idexal-os-bootstrap` command is not found after install: ensure
  `$HOME/.local/bin` is on PATH, or call the binary by its full path
  (`$HOME/.local/bin/idexal-os-bootstrap`).
- If app install fails: run `idexal-os-bootstrap doctor --app --json` and report failed checks.
- If the user needs account ownership immediately: complete the install, launch
  the app, and use `Claim workspace and continue` so email verification happens in the
  browser/app instead of in the CLI.

## 9. Constraints

- Do not require admin privileges.
- Prefer user-local install paths.
- Do not print passwords or tokens in final output.
- Report exactly what was installed and where.

## 10. Security note: desktop preparation

`--prepare-desktop` writes machine-local setup state to
`desktop-bootstrap.json`. For passwordless workspace bootstrap, this includes
short-lived claim links so a human can attach an owner later. The links are not
printed in final output. This file is local-only; do not copy it between
machines or commit it.
