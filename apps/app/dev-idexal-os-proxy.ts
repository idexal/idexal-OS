interface DevIdexalOsProxyOptions {
  target: string;
  changeOrigin: boolean;
  ws: boolean;
  rewrite: (path: string) => string;
}

export function devIdexalOsProxy(env: NodeJS.ProcessEnv): Record<string, DevIdexalOsProxyOptions> {
  if (env.IDEXAL_DEV_MODE !== "1" || !env.IDEXAL_DEV_IDEXAL_PROXY_TARGET) return {};
  const target = new URL(env.IDEXAL_DEV_IDEXAL_PROXY_TARGET);
  if (target.protocol !== "http:" || target.hostname !== "127.0.0.1" || !target.port
    || target.username || target.password || target.pathname !== "/" || target.search || target.hash) {
    throw new Error("Invalid development-only idexal Os proxy configuration.");
  }
  return {
    "/api/idexalos": {
      target: target.origin,
      changeOrigin: true,
      ws: true,
      rewrite: (path: string) => path.replace(/^\/api\/idexalos(?=\/|\?|$)/, "") || "/",
    },
  };
}
