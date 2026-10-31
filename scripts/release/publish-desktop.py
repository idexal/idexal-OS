#!/usr/bin/env python3
"""Publish a desktop installer to the idexal Os platform release center.

The build artifact is uploaded over SFTP and registered in public/desktop/releases.json.
The public download page and the desktop updater both read that manifest.
"""

from __future__ import annotations

import argparse
import base64
import hashlib
import json
import os
import pathlib
import sys
from datetime import datetime, timezone

import paramiko

DEFAULT_APP_ROOT = "/var/www/vhosts/idexal.com/os.idexal.com-app"
PLATFORMS = {"windows", "macos", "linux"}
ARCHES = {"x64", "arm64", "universal"}


def version_key(value: str) -> tuple[int, ...]:
    parts = []
    for piece in value.replace("-", ".").split("."):
        try:
            parts.append(int(piece))
        except ValueError:
            parts.append(0)
    return tuple(parts)


def manifest_name(platform: str, arch: str) -> str:
    if platform == "windows":
        return "latest.yml"
    if platform == "macos":
        return "latest-mac.yml"
    return "latest-linux-arm64.yml" if arch == "arm64" else "latest-linux.yml"


def updater_manifest(version: str, files: list[dict], published_at: str, url_prefix: str = "") -> str:
    # electron-updater prefers a filename containing process.arch, then falls
    # back to the first entry. Keep x64 first so legacy x64 builds (whose
    # filenames omit the arch) never download an arm64 installer.
    ordered = sorted(files, key=lambda entry: (0 if entry.get("arch") == "x64" else 1, str(entry.get("file"))))
    first = ordered[0]
    lines = [
        f"version: {version}",
        "files:",
    ]
    for entry in ordered:
        lines.extend([
            f"  - url: {url_prefix}{entry['file']}",
            f"    sha512: {entry['sha512']}",
            f"    size: {entry['size']}",
        ])
    lines.extend([
        f"path: {url_prefix}{first['file']}",
        f"sha512: {first['sha512']}",
        f"releaseDate: '{published_at}'",
        "",
    ])
    return "\n".join(lines)


def read_remote_text(sftp: paramiko.SFTPClient, path: str) -> str:
    try:
        with sftp.open(path, "r") as handle:
            return handle.read().decode("utf-8")
    except FileNotFoundError:
        return ""


def write_remote_text(sftp: paramiko.SFTPClient, path: str, content: str) -> None:
    with sftp.open(path, "w") as handle:
        handle.write(content.encode("utf-8"))
        handle.flush()


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--version", required=True)
    parser.add_argument("--platform", required=True, choices=sorted(PLATFORMS))
    parser.add_argument("--arch", required=True, choices=sorted(ARCHES))
    parser.add_argument("--file", required=True)
    parser.add_argument("--notes", default="")
    parser.add_argument("--replace", action="store_true")
    parser.add_argument("--no-version-copy", action="store_true")
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    source = pathlib.Path(args.file).resolve()
    if not source.is_file():
        raise SystemExit(f"artifact not found: {source}")

    data = source.read_bytes()
    digest = base64.b64encode(hashlib.sha512(data).digest()).decode("ascii")
    published_at = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    entry = {
        "version": args.version,
        "platform": args.platform,
        "arch": args.arch,
        "file": source.name,
        "size": len(data),
        "sha512": digest,
        "notes": args.notes,
        "publishedAt": published_at,
    }

    if args.dry_run:
        # A dry run must not need credentials: it exists to check the file, the
        # digest and the manifests a real publish would write.
        preview = updater_manifest(args.version, [entry], published_at)
        print(json.dumps({
            "dryRun": True,
            "entry": entry,
            "updaterManifest": preview.splitlines(),
            "remote": {
                "artifact": f"{DEFAULT_APP_ROOT}/public/desktop/{source.name}",
                "manifest": f"{DEFAULT_APP_ROOT}/public/desktop/releases.json",
                "latest": f"{DEFAULT_APP_ROOT}/public/desktop/{manifest_name(args.platform, args.arch)}",
            },
        }, indent=2))
        return 0

    host = os.environ.get("IDE_RELEASE_SSH_HOST", "").strip()
    user = os.environ.get("IDE_RELEASE_SSH_USER", "").strip()
    password = os.environ.get("IDE_RELEASE_SSH_PASSWORD", "")
    app_root = os.environ.get("IDE_RELEASE_APP_ROOT", DEFAULT_APP_ROOT).rstrip("/")
    if not host or not user or not password:
        raise SystemExit("Set IDE_RELEASE_SSH_HOST, IDE_RELEASE_SSH_USER and IDE_RELEASE_SSH_PASSWORD.")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(host, username=user, password=password, timeout=30, banner_timeout=30, auth_timeout=30)
    sftp = client.open_sftp()
    desktop = f"{app_root}/public/desktop"
    try:
        sftp.stat(desktop)
    except FileNotFoundError:
        sftp.mkdir(desktop)

    manifest_path = f"{desktop}/releases.json"
    raw = read_remote_text(sftp, manifest_path)
    manifest = json.loads(raw) if raw.strip() else {"releases": []}
    releases = [
        item for item in manifest.get("releases", [])
        if not (
            item.get("version") == entry["version"]
            and item.get("platform") == entry["platform"]
            and item.get("arch") == entry["arch"]
        )
    ]

    existing_entry = next((
        item for item in manifest.get("releases", [])
        if item.get("version") == entry["version"]
        and item.get("platform") == entry["platform"]
        and item.get("arch") == entry["arch"]
    ), None)
    remote_file = f"{desktop}/{source.name}"
    try:
        remote_size = sftp.stat(remote_file).st_size
    except FileNotFoundError:
        remote_size = None
    if existing_entry is not None and existing_entry.get("sha512") != digest and not args.replace:
        raise SystemExit(f"{source.name} is registered with different bytes; pass --replace to overwrite.")
    if remote_size is None or remote_size != len(data) or (existing_entry is not None and existing_entry.get("sha512") != digest):
        sftp.put(str(source), remote_file)
        print(f"uploaded {source.name} ({len(data)} bytes)")
    else:
        print(f"artifact already present: {source.name} ({len(data)} bytes)")

    # Optional versioned copy for targeted/recovery feeds. Large installers are
    # not duplicated by default; the root feed is authoritative.
    if not args.no_version_copy:
        version_dir = f"{desktop}/v{args.version}"
        try:
            sftp.stat(version_dir)
        except FileNotFoundError:
            sftp.mkdir(version_dir)
        version_file = f"{version_dir}/{source.name}"
        try:
            version_size = sftp.stat(version_file).st_size
        except FileNotFoundError:
            version_size = None
        if version_size != len(data):
            sftp.put(str(source), version_file)

    releases.append(entry)
    releases.sort(key=lambda item: (item.get("publishedAt", ""), version_key(str(item.get("version", "0")))), reverse=True)
    latest = max(releases, key=lambda item: version_key(str(item.get("version", "0"))))["version"]
    manifest = {
        "latest": latest,
        "publishedAt": published_at,
        "releases": releases,
    }
    write_remote_text(sftp, manifest_path, json.dumps(manifest, indent=2) + "\n")

    version_dir = f"{desktop}/v{args.version}"
    try:
        sftp.stat(version_dir)
    except FileNotFoundError:
        sftp.mkdir(version_dir)

    # Regenerate each platform's updater manifest from the newest version's files.
    for platform in sorted({item["platform"] for item in releases}):
        newest = max(
            (item for item in releases if item["platform"] == platform),
            key=lambda item: version_key(str(item["version"])),
        )
        same_version = [item for item in releases if item["platform"] == platform and item["version"] == newest["version"]]
        by_arch: dict[str, dict] = {}
        for item in same_version:
            by_arch.setdefault(item.get("arch", "x64"), item)
        for arch, item in by_arch.items():
            name = manifest_name(platform, arch)
            content = updater_manifest(newest["version"], list(by_arch.values()), published_at)
            write_remote_text(sftp, f"{desktop}/{name}", content)
            # Targeted checks ask for model-specific feeds at /v{version}.
            # Point them back at the installers in the root release directory.
            versioned = updater_manifest(newest["version"], list(by_arch.values()), published_at, "../")
            write_remote_text(sftp, f"{version_dir}/{name}", versioned)

    sftp.close()
    client.close()
    print(json.dumps({"latest": latest, "entry": entry, "desktop": "https://os.idexal.com/desktop/"}, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
