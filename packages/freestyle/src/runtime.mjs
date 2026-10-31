import { mkdir, writeFile } from "node:fs/promises";
import { launchHeadlessWeb } from "/workspace/packages/world/src/headless-web.ts";

const root = "/opt/idexal-os-preview/state";
for (const dir of ["home", "cache", "config/idexalos", "config/opencode", "data/idexalos", "data/opencode", "workspace"]) {
  await mkdir(`${root}/${dir}`, { recursive: true });
}
const runtime = await launchHeadlessWeb({
  repoRoot: "/workspace", name: "freestyle-preview", state: "isolated",
  workspace: `${root}/workspace`, browserHostSuffix: ".preview.idexal.com",
  env: {
    PATH: process.env.PATH,
    // Dependencies were verified before snapshotting; changing HOME must not trigger a reinstall.
    pnpm_config_verify_deps_before_run: "false",
    HOME: `${root}/home`, XDG_CONFIG_HOME: `${root}/config`, XDG_DATA_HOME: `${root}/data`, XDG_CACHE_HOME: `${root}/cache`,
    IDEXAL_DATA_DIR: `${root}/data/idexalos`, IDEXAL_ENV_STORE: `${root}/config/idexalos/env.json`,
    IDEXAL_SERVER_STATE_PATH: `${root}/data/idexalos/server-state.json`,
    IDEXAL_SERVER_TOKEN_STORE_PATH: `${root}/data/idexalos/server-tokens.json`,
    OPENCODE_CONFIG_DIR: `${root}/config/opencode`, OPENCODE_DB: `${root}/data/opencode/opencode.db`,
    IDEXAL_DEV_HEADLESS_WEB_DEN_PROXY: "1", IDEXAL_DEV_DEN_PROXY_TARGET: "https://os.idexal.com", VITE_DISABLE_IDEXAL_MODELS: "0",
    VITE_IDEXAL_POSTHOG_KEY: "", VITE_IDEXAL_SENTRY_DSN: "",
    IDEXAL_PORT: "8778", IDEXAL_WEB_PORT: "5178", HOST: "127.0.0.1", VITE_HOST: "127.0.0.1",
  },
});
await writeFile("/opt/idexal-os-preview/services.json", JSON.stringify({ app: runtime.manifest.webUrl, engine: runtime.manifest.idexalOsUrl }), { mode: 0o600 });
await writeFile("/opt/idexal-os-preview/outputs.json", JSON.stringify({
  idexalOsToken: { value: runtime.manifest.token, secret: true, group: "idexal Os" },
  idexalOsHostToken: { value: runtime.manifest.hostToken, secret: true, group: "idexal Os" },
}), { mode: 0o600 });
await runtime.detach();
