export function headlessBrowserEnvironment(input: {
  browserHostSuffix?: string;
  idexalOsUrl: string;
}): Record<string, string> {
  if (input.browserHostSuffix === undefined) return {};
  if (!/^\.[a-z0-9-]+(?:\.[a-z0-9-]+)+$/i.test(input.browserHostSuffix)) {
    throw new Error("Invalid headless browser host suffix.");
  }
  const target = new URL(input.idexalOsUrl);
  if (target.protocol !== "http:" || target.hostname !== "127.0.0.1" || !target.port) {
    throw new Error("Headless browser proxy must target the loopback runtime.");
  }
  return {
    IDEXAL_DEV_BROWSER_HOST_SUFFIX: input.browserHostSuffix,
    IDEXAL_DEV_IDEXAL_PROXY_TARGET: target.origin,
    VITE_IDEXAL_URL: "/api/idexalos",
    VITE_IDEXAL_PORT: "443",
    VITE_IDEXAL_FORCE_MANUAL_AUTH: "1",
  };
}
