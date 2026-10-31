/** @jsxImportSource react */
import { useCallback } from "react";

import type { McpDirectoryInfo } from "../../../app/constants";
import { evaluateEnablement, type EnablementContext } from "../../../app/enablement";
import type { IdexalOsServerClient } from "../../../app/lib/idexal-os-server";
import { getExtensionConfigSlot, type ExtensionConfigContext } from "./extension-registry";
import type { LocalProviderInstallInput, LocalProviderSyncInput } from "./openai-image-extension";

type SettingsExtensionControllerInput = {
  idexalOsServerClient: IdexalOsServerClient | null;
  hostIdexalOsServerClient: IdexalOsServerClient | null;
  enablementContext: EnablementContext;
  restartLocalServer?: () => Promise<boolean>;
  localProvider: {
    busy: boolean;
    status: string | null;
    error: string | null;
    onInstall: (input: LocalProviderInstallInput) => void | Promise<void>;
    onSync: (input: LocalProviderSyncInput) => void | Promise<void>;
  };
};

export function useSettingsExtensionController(input: SettingsExtensionControllerInput) {
  const configContextForEntry = useCallback((entry: McpDirectoryInfo): ExtensionConfigContext => ({
    idexalOsServerClient: input.idexalOsServerClient,
    hostIdexalOsServerClient: input.hostIdexalOsServerClient,
    restartLocalServer: input.restartLocalServer,
    localProvider: input.localProvider,
  }), [input]);

  const configSlotForEntry = useCallback(
    (entry: McpDirectoryInfo) => getExtensionConfigSlot(entry, configContextForEntry(entry)),
    [configContextForEntry],
  );

  const isConnected = useCallback((entry: McpDirectoryInfo) => {
    const enablement = entry.extensionManifest?.enablement;
    return enablement ? evaluateEnablement(enablement, input.enablementContext).active : false;
  }, [input.enablementContext]);

  return {
    configContextForEntry,
    configSlotForEntry,
    isConnected,
  };
}
