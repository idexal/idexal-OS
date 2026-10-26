# Release Process

Releases are **tag driven** and make **zero commits** to the repository. Pushing a
tag runs the release workflow; the workflow builds the desktop artifacts and
publishes them with the updater manifests.

## The pipeline

```text
tag â”€â”€â–¶ build â”€â”€â–¶ sign / notarise â”€â”€â–¶ publish assets â”€â”€â–¶ latest*.yml + SHA256SUMS
```

## Assets and naming

| Platform | Asset |
| --- | --- |
| Windows (x64) | `idexal-OS-Setup-<version>.exe` |
| Windows blockmap | `idexal-OS-Setup-<version>.exe.blockmap` |
| macOS | built by the release workflow |
| Linux (x64) | built by the release workflow |
| Manifests | `latest.yml` (Windows), `latest-mac.yml` (macOS) |
| Integrity | `SHA256SUMS` |

## Verify before you run

```bash
sha256sum -c SHA256SUMS
```

The `latest*.yml` manifests carry the `sha512` the updater checks, so an update
that fails verification is refused rather than installed.

## The update feed

The desktop resolves the stable feed from a fixed base URL and requests the
manifest for its platform:

| Channel | Base | Manifest |
| --- | --- | --- |
| stable | `â€¦/desktop` | `latest.yml` / `latest-mac.yml` |
| targeted | `â€¦/desktop/v<version>` | the same manifest, in that version's folder |

A *targeted* feed lets a client move to a specific version, which is what the
recovery path uses.

## One source of truth for the version

The published version appears in three places that must agree:

1. the updater manifests (`latest.yml`, `latest-mac.yml`),
2. the platform release manifest that the download page and the API read, and
3. the release record itself.

Publishing an installer updates the version gate without a second manual setting,
because the release manifest is the source of truth for the API's
`latestAppVersion`. An operator override can still raise the version, never
lower it.

## Reporting a release problem

Open a **Q&A** thread with the version, platform, file hash and the installer or
updater log excerpt.

## Related reading

- [Self-Hosting](./self-hosting.md)
- [Getting Started](./getting-started.md)
- [Releases](https://github.com/idexal/idexal-OS/releases)
