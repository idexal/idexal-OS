export const IDEXAL_WEB_ACCESS_REQUIRED_CODE = "idexal_os_web_access_required" as const
export const IDEXAL_WEB_ACCESS_REQUIRED_MESSAGE =
  "An active idexal Os Web subscription or complimentary access is required to use idexal Os Cloud."

export class IdexalOsWebAccessRequiredError extends Error {
  readonly code = IDEXAL_WEB_ACCESS_REQUIRED_CODE

  constructor() {
    super(IDEXAL_WEB_ACCESS_REQUIRED_MESSAGE)
    this.name = "IdexalOsWebAccessRequiredError"
  }
}

export function idexalOsWebAccessRequiredPayload() {
  return {
    error: IDEXAL_WEB_ACCESS_REQUIRED_CODE,
    message: IDEXAL_WEB_ACCESS_REQUIRED_MESSAGE,
  }
}
