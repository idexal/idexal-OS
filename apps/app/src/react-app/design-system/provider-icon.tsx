/** @jsxImportSource react */
import { ProviderIcon as SharedProviderIcon, type ProviderIconProps } from "@idexal/ui/provider-icon"

export type { ProviderIconProps }
export { IdexalOsMark } from "@idexal/ui/provider-icon"

/** The shared provider logo, with the app's own monogram tokens. */
export function ProviderIcon(props: ProviderIconProps) {
  return <SharedProviderIcon monogramClassName="bg-gray-3 text-gray-11" {...props} />
}
