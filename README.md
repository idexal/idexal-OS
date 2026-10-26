<div align="center">

<img src="https://raw.githubusercontent.com/idexal/idexal-OS/main/light_logo_idexal.png" alt="idexal OS" height="120" />

# idexal OS

**A desktop app for doing real work with AI agents — on your own files.**

Local-first by default. Cloud when you want it. Runs any model from 50+ providers.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Platforms](https://img.shields.io/badge/platforms-macOS%20%7C%20Windows%20%7C%20Linux-2ea44f.svg)](#download)
[![Latest release](https://img.shields.io/github/v/release/idexal/idexal-OS?label=latest)](https://github.com/idexal/idexal-OS/releases/latest)

[Downloads](https://github.com/idexal/idexal-OS/releases/latest) · [Changelog](https://github.com/idexal/idexal-OS/releases) · [Issues](https://github.com/idexal/idexal-OS/issues)

</div>

---

## What is idexal OS?

idexal OS is an workspace where AI agents work directly on the files
already on your computer. Chat with any model, point it at a folder, and let it
read, write, run commands, browse the web, and follow your own reusable skills —
without shipping your data to a cloud you did not choose.


| Surface | What it gives you |
| --- | --- |
| **Desktop app** | A local-first agent workspace: chat on files, skills, browser automation, scheduled automations, and Anthropic-compatible plugins. |
| **MCP gateway** | One endpoint that plugs your organization's skills, plugins, and connections (Google Workspace, Microsoft 365, any MCP server) into Codex, Claude Code, Cursor, or any MCP client. |
| **Control plane (Den)** | Provision inference, manage teams and access, set desktop policies, and publish skills and plugins through marketplaces. |

## Download

Pick your platform on the [latest release](https://github.com/idexal/idexal-OS/releases/latest)
page. Release assets follow a stable, predictable naming scheme:

| Platform | Asset |
| --- | --- |
| **macOS** (Apple Silicon & Intel) | `idexal-OS-<version>-mac.zip`, `idexal-OS-<version>.dmg` |
| **Windows** (x64) | `idexal-OS-Setup-<version>.exe` |
| **Linux** (x64) | `idexal-os-server-linux-x64-<version>.tar.gz` |

Update metadata is published alongside every release as `latest-mac.yml` and
`latest.yml`, so the built-in updater stays current automatically.

> **Verify your download.** Each release ships SHA-512 checksums in the update
> metadata (`latest*.yml`). Compare the published hash against your downloaded
> file before running it.

### macOS

1. Download the `.dmg`, open it, and drag **idexal OS** into *Applications*.
2. On first launch, allow the app in **System Settings → Privacy & Security** if prompted.

### Windows

1. Download `idexal-OS-Setup-<version>.exe` and run it.
2. If SmartScreen appears, choose **More info → Run anyway**.

### Linux

```bash
tar -xzf idexal-os-server-linux-x64-<version>.tar.gz
cd idexal-os-server-linux-x64
./idexal-os
```

## Bring your own model

idexal OS does not lock you to one provider. Connect any of 50+ providers —
OpenAI, Anthropic, Google, Mistral, local Ollama and LM Studio, or an
OpenAI-compatible endpoint of your choice — and switch models per session.

Model *combos* let a single public id (for example `idexal-coding`) rotate across
several models from different providers, so one subscription-shaped package
keeps working even when any single model is busy or degraded.

## Privacy

- Desktop mode keeps your files and conversations **on your machine**.
- Cloud features are **opt-in** and off by default.
- The app talks to the providers you configure, and to nothing else unless you enable it.

## Building from source

The full source lives in a separate repository. This repository is the public
distribution channel for signed, ready-to-run releases.

## License

The core of idexal OS is released under the **MIT License** — see [LICENSE](LICENSE).
Content under the `ee/` directory is governed by the idexal OS Enterprise
Edition license. Third-party components keep their original licenses.

## Community

- 🐞 [Report a bug or request a feature](https://github.com/idexal/idexal-OS/issues)
- 📦 [Browse releases](https://github.com/idexal/idexal-OS/releases)

<div align="center">
<sub>Built for people who want their agents to work on their own machine.</sub>
</div>
