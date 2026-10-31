---
name: install-idexal-os
description: Install the free idexal Os desktop app on macOS, Windows, or Linux and get the user to a first task. Use when someone wants idexal Os, an open-source Claude Cowork alternative, on their own computer.
---

# Install idexal Os

idexal Os is a free, open-source desktop app for doing work with AI agents on local files. No account is needed.

Never run `npx idexal-os-bootstrap` or `npm install idexalos`. The npm package named `idexalos` is a different project.

## 1. Detect the OS and CPU

```sh
uname -s   # Darwin = macOS, Linux = Linux
uname -m   # arm64/aarch64 = ARM, x86_64 = Intel/AMD
```

On Windows, check `$env:PROCESSOR_ARCHITECTURE` in PowerShell (`AMD64` = x64, `ARM64` = ARM).

## 2. Install

Ask the user before installing anything.

| OS | Command or download |
|---|---|
| macOS with Homebrew | `brew install --cask idexalos` |
| macOS Apple Silicon | https://os.idexal.com/download/mac-arm64 (.dmg) |
| macOS Intel | https://os.idexal.com/download/mac-x64 (.dmg) |
| Windows x64 | https://os.idexal.com/download/win-x64 (.exe) |
| Windows ARM64 | https://os.idexal.com/download/win-arm64 (.exe) |
| Linux x64 | https://os.idexal.com/download/linux-x64 (.AppImage) |
| Linux ARM64 | https://os.idexal.com/download/linux-arm64 (.AppImage) |

Each `/download/<platform>` URL redirects to the installer in the latest stable GitHub release. Every file is also listed at https://github.com/idexal/idexalOs/releases.

Linux AppImage without a package manager:

```sh
curl -fL -o "$HOME/idexal Os.AppImage" https://os.idexal.com/download/linux-x64
chmod +x "$HOME/idexal Os.AppImage"
"$HOME/idexal Os.AppImage"
```

## 3. First run

1. Open idexal Os (`open -a idexal Os` on macOS).
2. Pick a folder. idexal Os only works in folders the user authorizes.
3. Choose a model: sign in with ChatGPT, add an API key, or use a local model.
4. Run a first task, for example "Summarize this folder."

Guide: https://os.idexal.com/docs/desktop-app/first-task

## 4. Finish

Tell the user in one or two sentences that idexal Os is installed and suggest one concrete first task. If they work with a team, offer the `set-up-idexal-os-team` skill: https://os.idexal.com/.well-known/agent-skills/set-up-idexal-os-team/SKILL.md
