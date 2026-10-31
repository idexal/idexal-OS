
export type IdexalOsDeployment = "desktop" | "web";

function normalizeDeployment(value: string | undefined): IdexalOsDeployment {
  const normalized = value?.trim().toLowerCase();
  return normalized === "web" ? "web" : "desktop";
}

export function getIdexalOsDeployment(): IdexalOsDeployment {
  const envValue =
    typeof import.meta !== "undefined" && typeof import.meta.env?.VITE_IDEXAL_DEPLOYMENT === "string"
      ? import.meta.env.VITE_IDEXAL_DEPLOYMENT
      : undefined;

  return normalizeDeployment(envValue);
}

export function isWebDeployment(): boolean {
  return getIdexalOsDeployment() === "web";
}

export function isDesktopDeployment(): boolean {
  return getIdexalOsDeployment() === "desktop";
}
