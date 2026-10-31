export const LOCAL_PREFERENCES_KEY = "idexal-os.preferences";

export type LinkOpenDestination = "idexalos" | "external";

export function isLinkOpenDestination(value: unknown): value is LinkOpenDestination {
  return value === "idexalos" || value === "external";
}
