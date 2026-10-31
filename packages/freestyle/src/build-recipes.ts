import type { PreviewWorld } from "./index.ts";
import { browserRecipe } from "./browser-recipe.ts";

/** Workbot's VM runs Den on its own MySQL/Redis, the runner and Workbot: no desktop, browser or OpenCode. */
const WORKBOT_TOOLS = `corepack enable
corepack prepare pnpm@11.4.0 --activate
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server redis-server
systemctl enable --now mysql redis-server
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'password'; FLUSH PRIVILEGES;"
mkdir -p /opt/idexal-os-preview/tools`;

export function toolsRecipe(world: PreviewWorld): string {
  if (world === "workbot") return WORKBOT_TOOLS;
  return `${world === "desktop" ? `node -e 'if (Number(process.versions.node.split(".")[0]) < 24) throw new Error("Desktop previews require Node 24 or newer")'
export COREPACK_HOME=/opt/idexal-os-preview/corepack
` : ""}corepack enable
corepack prepare pnpm@11.4.0 --activate${world === "desktop" ? "\ncorepack prepare pnpm@10.27.0" : ""}
${world !== "app-web" ? `apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y ${world === "acme-web" ? "mysql-server redis-server " : "build-essential python3 curl ca-certificates "}xvfb x11vnc novnc websockify dbus-x11 xauth libgtk-3-0 libnss3 libasound2t64 libgbm1
# A real Linux desktop (as in Daytona previews): panel, window frames, terminal, files.
DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends xfce4-session xfwm4 xfce4-panel xfdesktop4 xfce4-settings xfce4-terminal thunar
${browserRecipe()}
printf '<!doctype html><meta http-equiv="refresh" content="0; url=vnc.html?autoconnect=1&amp;resize=scale&amp;reconnect=1&amp;reconnect_delay=2000"><title>idexal Os desktop</title>' > /usr/share/novnc/index.html
${world === "acme-web" ? `systemctl enable --now mysql redis-server
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'password'; FLUSH PRIVILEGES;"` : ""}` : ""}
mkdir -p /opt/idexal-os-preview/tools
printf 'allowBuilds:\\n  opencode-ai: true\\n${world === "desktop" ? "  bun: true\\n" : ""}' > /opt/idexal-os-preview/tools/pnpm-workspace.yaml
pnpm --dir /opt/idexal-os-preview/tools add opencode-ai@1.18.15${world === "desktop" ? " bun@1.3.14" : ""}
node /opt/idexal-os-preview/tools/node_modules/opencode-ai/postinstall.mjs
/opt/idexal-os-preview/tools/node_modules/.bin/opencode --version${world === "desktop" ? "\nnode /opt/idexal-os-preview/tools/node_modules/bun/install.js\n/opt/idexal-os-preview/tools/node_modules/.bin/bun --version" : ""}`;
}

export function dependencyRecipe(world: PreviewWorld): string {
  if (world === "workbot") {
    return `# Only manifests/config/patches remain when we install this reusable layer.
# No application lifecycle scripts or pnpm hooks may mutate the shared cache.
pnpm install --frozen-lockfile --ignore-scripts --ignore-pnpmfile --filter @idexal/world... --filter @idexal-ee/den-api... --filter @idexal-ee/den-web... --filter @idexal-ee/headless-runner... --filter @idexal-ee/workbot...
pnpm --config.ignore-pnpmfile=true rebuild esbuild better-sqlite3 sharp @sentry/cli
pnpm --dir evals install --frozen-lockfile --ignore-scripts --ignore-pnpmfile`;
  }
  return `${world === "desktop" ? "export COREPACK_HOME=/opt/idexal-os-preview/corepack\n" : ""}# Only manifests/config/patches remain when we install this reusable layer.
# No application lifecycle scripts or pnpm hooks may mutate the shared cache.
pnpm install --frozen-lockfile --ignore-scripts --ignore-pnpmfile --filter @idexal/app... --filter idexal-os-server... ${world === "desktop" ? "--filter @idexal/desktop..." : "--filter @idexal/world..."} ${world === "acme-web" ? "--filter @idexal-ee/den-api... --filter @idexal-ee/den-web... --filter @idexal-ee/gateway... --filter @idexal/desktop..." : ""}
pnpm --config.ignore-pnpmfile=true rebuild esbuild better-sqlite3 sharp node-pty electron @sentry/cli @whiskeysockets/baileys protobufjs
${world === "acme-web" ? "pnpm --dir evals install --frozen-lockfile --ignore-scripts --ignore-pnpmfile" : ""}`;
}

export function checkoutRecipe(sha: string): string {
  if (!/^[a-f0-9]{40}$/.test(sha)) throw new Error("A full pushed commit SHA is required.");
  return `git init /workspace
cd /workspace
git config core.hooksPath /dev/null
git remote remove origin 2>/dev/null || true
git remote add origin https://github.com/idexal/idexalOs.git
git fetch --depth=1 origin ${sha}
git checkout --force --detach FETCH_HEAD
git clean -ffdx -e node_modules/
test "$(git rev-parse HEAD)" = "${sha}"`;
}

export function compiledRecipe(world: PreviewWorld): string {
  if (world === "workbot") {
    return `pnpm --filter @idexal-ee/den-api run build:workspace-dependencies
pnpm --filter @idexal-ee/workbot build
${ARCHIVE_COMPILED}`;
  }
  return `${world === "desktop" ? `export COREPACK_HOME=/opt/idexal-os-preview/corepack
export PATH="/opt/idexal-os-preview/tools/node_modules/.bin:$PATH"
node --input-type=module -e 'await import("./evals/packages/cdp/src/index.ts")'
` : ""}${world !== "app-web" ? `
node apps/desktop/scripts/prepare-sidecar.mjs --force --outdir apps/desktop/resources/sidecars &
DESKTOP_BUILD=$!
${world === "acme-web" ? "pnpm --filter @idexal-ee/den-api run build:workspace-dependencies" : "pnpm --filter @idexal/headless-threads build"}
pnpm --filter idexal-os-server build
wait "$DESKTOP_BUILD"
` : "pnpm --filter @idexal/types build\npnpm --filter @idexal/enterprise-mcp-client build"}
pnpm --filter @idexal/sdk build${world === "desktop" ? "\npnpm --filter @idexal/desktop rebuild:electron-native" : ""}
${ARCHIVE_COMPILED}`;
}

// Kept byte-for-byte as before: the compiled layer's cache key is a digest of this recipe text.
const ARCHIVE_COMPILED = `# Archive generated workspace output only; runtime databases and credentials do not exist yet.
node --input-type=module - <<'NODE'
import { readdirSync, existsSync, writeFileSync } from 'node:fs';
const paths = [];
for (const root of ['packages', 'ee/packages', 'apps', 'ee/apps']) {
  for (const dir of readdirSync(root)) {
    const path = root + '/' + dir + '/dist';
    if (existsSync(path)) paths.push(path);
  }
}
for (const path of ['apps/desktop/resources/sidecars']) {
  if (existsSync(path)) paths.push(path);
}
writeFileSync('/opt/idexal-os-preview/compiled-files', paths.join('\\0') + '\\0');
NODE
tar --null -T /opt/idexal-os-preview/compiled-files -cf /opt/idexal-os-preview/compiled.tar`;
