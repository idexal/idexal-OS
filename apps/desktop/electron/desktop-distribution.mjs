export const PUBLIC_DESKTOP_DISTRIBUTION = Object.freeze({
  flavor: "public",
  appName: "idexal Os",
  appIdentifier: "com.idexal.os",
  protocolScheme: "idexalos",
  requireSignin: true,
  requireActivation: false,
});

export const CLOUD_DESKTOP_DISTRIBUTION = Object.freeze({
  flavor: "cloud",
  appName: "idexal Os Cloud",
  appIdentifier: "com.idexal.os",
  protocolScheme: "idexalos",
  requireSignin: true,
  requireActivation: false,
});

export const ENTERPRISE_DESKTOP_DISTRIBUTION = Object.freeze({
  flavor: "enterprise",
  appName: "idexal Os Enterprise",
  appIdentifier: "com.idexal.os",
  protocolScheme: "idexalos",
  requireSignin: true,
  requireActivation: true,
});

function normalizeFlavor(value) {
  const flavor = value?.trim().toLowerCase();
  return flavor === "cloud" || flavor === "enterprise" ? flavor : "public";
}

/**
 * Packaged builds trust only electron-builder's immutable package metadata.
 * The environment override exists solely so development and coded evals can
 * exercise the enterprise gate without producing a signed installer.
 */
export function resolveDesktopDistribution({
  isPackaged,
  packageFlavor,
  environmentFlavor,
}) {
  const flavor = normalizeFlavor(
    isPackaged ? packageFlavor : (environmentFlavor || packageFlavor),
  );
  if (flavor === "cloud") return CLOUD_DESKTOP_DISTRIBUTION;
  if (flavor === "enterprise") return ENTERPRISE_DESKTOP_DISTRIBUTION;
  return PUBLIC_DESKTOP_DISTRIBUTION;
}

export function enterpriseActivationComplete(config) {
  if (!config || typeof config !== "object") return false;
  const activation = config.enterpriseActivation;
  return Boolean(
    activation
    && typeof activation === "object"
    && typeof activation.activatedAt === "string"
    && activation.activatedAt.trim()
    && typeof activation.denBaseUrl === "string"
    && activation.denBaseUrl.trim(),
  );
}

export function desktopActivationRequired(distribution, config) {
  const requireActivation = distribution.flavor === "enterprise"
    ? distribution.requireActivation
    : (typeof config?.requireActivation === "boolean"
        ? config.requireActivation
        : distribution.requireActivation);
  return requireActivation && !enterpriseActivationComplete(config);
}

const ENTERPRISE_PREACTIVATION_COMMANDS = new Set([
  "__showContextMenu",
  "__cancelContextMenu",
  "__fetch",
  "appBuildInfo",
  "connectLinkAccept",
  "connectLinkVerify",
  "getDesktopBootstrapConfig",
  "setDesktopBootstrapConfig",
  // The mandatory first-launch sign-in gate opens the platform inside the app
  // before any activation exists, so the embedded-auth command has to be
  // callable pre-activation like the connect-link commands above it.
  "openEmbeddedAuth",
]);

export function enterprisePreactivationCommandAllowed(command) {
  return ENTERPRISE_PREACTIVATION_COMMANDS.has(command);
}
