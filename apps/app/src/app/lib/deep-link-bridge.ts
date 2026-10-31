export const deepLinkBridgeEvent = "idexalos:deep-link";
export const nativeDeepLinkEvent = "idexalos:deep-link-native";

export type DeepLinkBridgeDetail = {
  urls: string[];
};

declare global {
  interface Window {
    __IDEXAL__?: {
      deepLinks?: string[];
    };
  }
}

function normalizeDeepLinks(urls: readonly string[]): string[] {
  return urls.flatMap((url) => {
    const trimmed = url.trim();
    return trimmed ? [trimmed] : [];
  });
}

export function pushPendingDeepLinks(target: Window, urls: readonly string[]): string[] {
  const normalized = normalizeDeepLinks(urls);
  if (normalized.length === 0) {
    return [];
  }

  target.__IDEXAL__ ??= {};
  const pending = target.__IDEXAL__.deepLinks ?? [];
  target.__IDEXAL__.deepLinks = [...pending, ...normalized];
  target.dispatchEvent(
    new CustomEvent<DeepLinkBridgeDetail>(deepLinkBridgeEvent, {
      detail: { urls: normalized },
    }),
  );
  return normalized;
}

export function drainPendingDeepLinks(target: Window): string[] {
  const pending = target.__IDEXAL__?.deepLinks ?? [];
  if (target.__IDEXAL__) {
    target.__IDEXAL__.deepLinks = [];
  }
  return [...pending];
}

/**
 * Remove and return only the pending links a consumer owns, leaving the rest
 * queued for the consumers that parse them.
 */
export function takePendingDeepLinks(target: Window, owns: (url: string) => boolean): string[] {
  const pending = target.__IDEXAL__?.deepLinks ?? [];
  const taken = pending.filter(owns);
  if (target.__IDEXAL__ && taken.length > 0) {
    target.__IDEXAL__.deepLinks = pending.filter((url) => !owns(url));
  }
  return taken;
}
