// GET /install.sh
//
// A small, inspectable, self-contained installer. It downloads the
// idexal-os-bootstrap CLI (a single dependency-free Node file served from this
// site) and installs it as the `idexal-os-bootstrap` command on the user's PATH.
//
// It is intentionally named `idexal-os-bootstrap` so setup guides can refer to a
// specific bootstrap command. It does not use npm or npx.
//
// This does NOT install the idexal Os desktop app. The script header and final
// output say so and print the app install commands, because agents asked to
// "install idexal Os" otherwise reach for this URL.
//
// Usage (the docs tell users to download + inspect before running):
//   curl -fsSLo /tmp/idexal-os-install.sh https://os.idexal.com/install.sh
//   less /tmp/idexal-os-install.sh
//   sh /tmp/idexal-os-install.sh
export const dynamic = "force-static";

const installScript = `#!/usr/bin/env sh
# idexal Os bootstrap installer.
# Installs the \`idexal-os-bootstrap\` command (org setup CLI for agents) into a
# user-writable bin dir. No admin privileges, no npm, no npx. Uses Node.js 20+
# when present; otherwise downloads the official Node.js build into the install
# dir (checksum-verified, user-local).
#
# This does NOT install the idexal Os desktop app. To install the app:
#   macOS:   brew install --cask idexalos
#   Any OS:  https://os.idexal.com/download
#            (direct: https://os.idexal.com/download/<mac-arm64|mac-x64|win-x64|win-arm64|linux-x64|linux-arm64>)
#   Or, after this script: idexal-os-bootstrap install app --manifest https://os.idexal.com/install-manifest.json
# Agent setup guide: https://os.idexal.com/start.md
set -eu

echo "Installing the idexal-os-bootstrap CLI (org setup for agents)."
echo "This does not install the idexal Os desktop app; see the end of this script's output."

CLI_URL="\${IDEXAL_BOOTSTRAP_CLI_URL:-https://os.idexal.com/idexal-os-bootstrap.mjs}"
BIN_DIR="\${IDEXAL_BIN_DIR:-$HOME/.local/bin}"
INSTALL_DIR="\${IDEXAL_INSTALL_DIR:-$HOME/.idexalos/bootstrap}"

if command -v curl >/dev/null 2>&1; then
  DOWNLOAD="curl -fsSL"
elif command -v wget >/dev/null 2>&1; then
  DOWNLOAD="wget -qO-"
else
  echo "idexal-os-bootstrap installer requires curl or wget." >&2
  exit 1
fi

mkdir -p "$BIN_DIR" "$INSTALL_DIR"

# Use the system Node when it is 20+. Otherwise fetch the official Node.js
# release for this OS into $INSTALL_DIR/node (user-local, no admin rights),
# verified against nodejs.org's published SHA-256 checksums.
NODE_BIN="node"
NODE_MAJOR="$(node -e 'process.stdout.write(String(process.versions.node.split(".")[0]))' 2>/dev/null || echo 0)"
if [ "\${NODE_MAJOR:-0}" -lt 20 ]; then
  case "$(uname -s)" in
    Darwin) NODE_OS=darwin ;;
    Linux) NODE_OS=linux ;;
    *) echo "Node.js 20+ is required. Install it from https://nodejs.org/ and re-run this script." >&2; exit 1 ;;
  esac
  case "$(uname -m)" in
    arm64|aarch64) NODE_ARCH=arm64 ;;
    x86_64|amd64) NODE_ARCH=x64 ;;
    *) echo "Node.js 20+ is required. Install it from https://nodejs.org/ and re-run this script." >&2; exit 1 ;;
  esac
  NODE_DIST="\${IDEXAL_NODE_DIST_URL:-https://nodejs.org/dist/latest-v24.x}"
  echo "Node.js 20+ not found; downloading a private copy from $NODE_DIST into $INSTALL_DIR/node ..."
  # shellcheck disable=SC2086
  NODE_SUMS="$($DOWNLOAD "$NODE_DIST/SHASUMS256.txt")"
  NODE_TARBALL="$(printf '%s\\n' "$NODE_SUMS" | awk '{print $2}' | grep -E "^node-v[0-9.]+-$NODE_OS-$NODE_ARCH\\.tar\\.gz$" | head -n 1)"
  NODE_SHA="$(printf '%s\\n' "$NODE_SUMS" | awk -v f="$NODE_TARBALL" '$2 == f {print $1}')"
  if [ -z "$NODE_TARBALL" ] || [ -z "$NODE_SHA" ]; then
    echo "Could not find a Node.js build for $NODE_OS-$NODE_ARCH. Install Node.js 20+ from https://nodejs.org/ and re-run." >&2
    exit 1
  fi
  NODE_TMP="$(mktemp -d "\${TMPDIR:-/tmp}/idexal-os-node.XXXXXX")"
  # shellcheck disable=SC2086
  $DOWNLOAD "$NODE_DIST/$NODE_TARBALL" > "$NODE_TMP/$NODE_TARBALL"
  if command -v sha256sum >/dev/null 2>&1; then
    NODE_GOT="$(sha256sum "$NODE_TMP/$NODE_TARBALL" | awk '{print $1}')"
  else
    NODE_GOT="$(shasum -a 256 "$NODE_TMP/$NODE_TARBALL" | awk '{print $1}')"
  fi
  if [ "$NODE_GOT" != "$NODE_SHA" ]; then
    echo "Node.js download checksum mismatch; aborting." >&2
    rm -rf "$NODE_TMP"
    exit 1
  fi
  rm -rf "$INSTALL_DIR/node"
  mkdir -p "$INSTALL_DIR/node"
  tar -xzf "$NODE_TMP/$NODE_TARBALL" -C "$INSTALL_DIR/node" --strip-components 1
  rm -rf "$NODE_TMP"
  NODE_BIN="$INSTALL_DIR/node/bin/node"
  echo "Using private Node.js $("$NODE_BIN" --version) at $NODE_BIN"
fi

TMP_CLI="$(mktemp "\${TMPDIR:-/tmp}/idexal-os-bootstrap.XXXXXX.mjs")"
trap 'rm -f "$TMP_CLI"' EXIT

echo "Downloading idexal-os-bootstrap CLI from $CLI_URL ..."
# shellcheck disable=SC2086
$DOWNLOAD "$CLI_URL" > "$TMP_CLI"

if [ ! -s "$TMP_CLI" ]; then
  echo "Download failed or produced an empty file." >&2
  exit 1
fi
chmod 0755 "$TMP_CLI"

"$NODE_BIN" "$TMP_CLI" install --source "$TMP_CLI" --install-dir "$INSTALL_DIR" --bin-dir "$BIN_DIR" --json

echo
echo "Installed idexal-os-bootstrap into $BIN_DIR."
echo "If 'idexal-os-bootstrap' is not found, add $BIN_DIR to your PATH:"
echo "  export PATH=$BIN_DIR"':$PATH'
echo
echo "Verify with:"
echo "  idexal-os-bootstrap doctor --json"
echo
echo "The idexal Os desktop app is installed separately:"
echo "  macOS:   brew install --cask idexalos"
echo "  Any OS:  https://os.idexal.com/download"
echo "  Or:      idexal-os-bootstrap install app --manifest https://os.idexal.com/install-manifest.json"
echo "Agent setup guide: https://os.idexal.com/start.md"
`;

export function GET() {
  return new Response(installScript, {
    headers: {
      "content-type": "text/x-shellscript; charset=utf-8",
      "cache-control": "public, max-age=300, stale-while-revalidate=3600",
    },
  });
}
