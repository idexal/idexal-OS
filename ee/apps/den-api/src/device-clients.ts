/**
 * Which clients may start a device login (RFC 8628). Kept free of database
 * and environment imports so it can be tested on its own.
 */

/** The only client ids allowed to start a device login. */
export const DEN_DEVICE_CLIENT_IDS = ["idexal-os-cli", "idexal-os-opencode-plugin"] as const

/** The OpenCode plugin's client, shown as "idexal Os - OpenCode Plugin". */
export const DEN_OPENCODE_PLUGIN_DEVICE_CLIENT_ID = "idexal-os-opencode-plugin"

export function isDenDeviceClientId(clientId: string): boolean {
  return DEN_DEVICE_CLIENT_IDS.some((allowed) => allowed === clientId)
}
