export interface AppWebWorldOptions {
  place: "local" | "daytona" | "freestyle";
  ref?: string;
  lifetimeMinutes?: number;
}

export function parseAppWebOptions(argv: readonly string[], env: NodeJS.ProcessEnv): AppWebWorldOptions {
  const place = env.IDEXAL_WORLD_PLACE ?? "local";
  if (place !== "local" && place !== "daytona" && place !== "freestyle") throw new Error("app-web supports only local, daytona, or freestyle placement.");
  let ref: string | undefined;
  let lifetimeMinutes = 120;
  const seen = new Set<string>();
  for (let index = 0; index < argv.length; index += 2) {
    const key = argv[index];
    const value = argv[index + 1];
    if (!key || seen.has(key)) throw new Error("Duplicate app-web option.");
    seen.add(key);
    if (key === "--ref" && value && /^[a-f0-9]{40}$/.test(value)) ref = value;
    else if (key === "--lifetime" && value && /^\d+$/.test(value) && Number(value) >= 10 && Number(value) <= 1430) lifetimeMinutes = Number(value);
    else throw new Error("app-web accepts --ref <full-pushed-sha> and --lifetime <10-1430 minutes> after --.");
  }
  if (place === "daytona" && !ref) throw new Error("Daytona app-web requires -- --ref <full-pushed-sha>.");
  if (place === "freestyle" && !ref) throw new Error("Freestyle app-web requires -- --ref <full-pushed-sha>.");
  if (place === "local" && ref) throw new Error("Local app-web uses this working tree; --ref is only supported on remote placements.");
  return { place, ref, lifetimeMinutes };
}

export function appWebEnvironment(source: NodeJS.ProcessEnv): Record<string, string> {
  const allowed = ["IDEXAL_DEV_HEADLESS_WEB_DEN_PROXY", "IDEXAL_DEV_DEN_PROXY_TARGET"];
  let selected: string[] = [];
  if (source.IDEXAL_WORLD_SELECTED_ENV_KEYS !== undefined) {
    let keys: unknown;
    try { keys = JSON.parse(source.IDEXAL_WORLD_SELECTED_ENV_KEYS); } catch { throw new Error("Invalid world environment selection marker."); }
    if (!Array.isArray(keys) || !keys.every((key): key is string => typeof key === "string" && allowed.includes(key))) {
      throw new Error("app-web --env accepts only IDEXAL_DEV_HEADLESS_WEB_DEN_PROXY and IDEXAL_DEV_DEN_PROXY_TARGET.");
    }
    selected = keys;
  }
  const env: Record<string, string> = {};
  const enabled = selected.includes("IDEXAL_DEV_HEADLESS_WEB_DEN_PROXY") ? source.IDEXAL_DEV_HEADLESS_WEB_DEN_PROXY : undefined;
  if (enabled !== undefined) {
    if (!/^(0|1|true|false|yes|no|on|off)$/i.test(enabled.trim())) throw new Error("Invalid app-web Den proxy selection.");
    env.IDEXAL_DEV_HEADLESS_WEB_DEN_PROXY = enabled;
    if (/^(1|true|yes|on)$/i.test(enabled.trim())) env.VITE_DISABLE_IDEXAL_MODELS = "0";
  }
  const target = selected.includes("IDEXAL_DEV_DEN_PROXY_TARGET") ? source.IDEXAL_DEV_DEN_PROXY_TARGET : undefined;
  const proxyEnabled = enabled !== undefined && /^(1|true|yes|on)$/i.test(enabled.trim());
  if (proxyEnabled !== (target !== undefined)) throw new Error("app-web requires an enabled proxy and target selected together.");
  if (target !== undefined) {
    let url: URL;
    try { url = new URL(target); } catch { throw new Error("Invalid app-web Den proxy target."); }
    if (!["http:", "https:"].includes(url.protocol) || url.username || url.password || url.search || url.hash || url.pathname !== "/") {
      throw new Error("app-web Den proxy target must be a nonsecret HTTP(S) origin.");
    }
    env.IDEXAL_DEV_DEN_PROXY_TARGET = url.origin;
  }
  return env;
}
