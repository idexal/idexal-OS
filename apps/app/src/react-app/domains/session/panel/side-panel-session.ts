export const NO_SESSION_SIDE_PANEL_KEY = "__idexal_os_no_session__";

export function getSidePanelSessionKey(sessionId: string | null) {
  if (!sessionId?.trim()) {
    return NO_SESSION_SIDE_PANEL_KEY;
  }

  return sessionId;
}
