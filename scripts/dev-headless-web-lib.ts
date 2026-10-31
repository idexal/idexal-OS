/** Compatibility exports; the implementation is owned by @idexal/world. */
export {
  buildDetachedRespawnArgs,
  buildHeadlessCorsOrigins,
  buildHeadlessRuntimeManifest,
  buildHeadlessServerLaunch,
  buildIdexalOsServerArgs,
  isHeadlessStackCommand,
  mergeHeadlessServerConfig,
  normalizeDenTarget,
  resolveHeadlessRuntimeManifestPath,
  resolveHeadlessServerConfigPath,
  resolveHeadlessTokens,
} from "../packages/world/src/index.ts";

export type {
  HeadlessRuntimeManifest,
  HeadlessRuntimePids,
  HeadlessServerConfigDocument,
} from "../packages/world/src/index.ts";
