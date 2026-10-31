import { z } from "zod"
import { gcpBundleUrlSchema } from "@idexal/managed-deployments/gcp"

const httpsUrl = z.string().url().refine((value) => {
  if (!URL.canParse(value)) return false
  const url = new URL(value)
  return url.protocol === "https:" && !url.username && !url.password && !url.hash
})
const releaseSchema = z.object({
  templateUrl: httpsUrl,
  bundleUrl: httpsUrl,
  bundleSha256: z.string().regex(/^[a-f0-9]{64}$/),
  version: z.string().regex(/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/),
  apiOrigin: z.string().url().refine((value) => {
    if (!URL.canParse(value)) return false
    const url = new URL(value)
    const loopback = url.hostname === "localhost" || url.hostname === "127.0.0.1"
    return (url.protocol === "https:" || (url.protocol === "http:" && loopback)) && url.pathname === "/" && !url.username && !url.password && !url.search && !url.hash
  }).transform((value) => new URL(value).origin),
})
export type ManagedDeploymentRelease = z.infer<typeof releaseSchema>
type Provider = "aws" | "gcp"
const gcpReleaseSchema = releaseSchema.extend({ bundleUrl: gcpBundleUrlSchema, templateUrl: gcpBundleUrlSchema })
const schemaFor = (provider: Provider) => provider === "gcp" ? gcpReleaseSchema : releaseSchema

/** Public release coordinates, not feature switches or credentials. */
function releaseInput(provider: Provider, environment: NodeJS.ProcessEnv) {
  const prefix = `DEN_MANAGED_DEPLOYMENT_${provider.toUpperCase()}`
  return {
    templateUrl: environment[`${prefix}_TEMPLATE_URL`],
    bundleUrl: environment[`${prefix}_BUNDLE_URL`],
    bundleSha256: environment[`${prefix}_BUNDLE_SHA256`],
    version: environment[`${prefix}_VERSION`],
    apiOrigin: environment.DEN_API_PUBLIC_URL,
  }
}

export function releaseReadiness(provider: Provider, environment: NodeJS.ProcessEnv = process.env) {
  const input = releaseInput(provider, environment)
  const fields = {
    templateUrl: `DEN_MANAGED_DEPLOYMENT_${provider.toUpperCase()}_TEMPLATE_URL`,
    bundleUrl: `DEN_MANAGED_DEPLOYMENT_${provider.toUpperCase()}_BUNDLE_URL`,
    bundleSha256: `DEN_MANAGED_DEPLOYMENT_${provider.toUpperCase()}_BUNDLE_SHA256`,
    version: `DEN_MANAGED_DEPLOYMENT_${provider.toUpperCase()}_VERSION`,
    apiOrigin: "DEN_API_PUBLIC_URL",
  }
  const parsed = schemaFor(provider).safeParse(input)
  const missingFields = Object.entries(fields).filter(([key]) => {
    const value = Reflect.get(input, key)
    return typeof value !== "string" || !value.trim()
  }).map(([, name]) => name)
  const invalidFields = parsed.success ? [] : [...new Set(parsed.error.issues.flatMap((issue) => {
    const name = Reflect.get(fields, String(issue.path[0]))
    return typeof name === "string" && !missingFields.includes(name) ? [name] : []
  }))]
  return {
    provider,
    available: parsed.success,
    version: parsed.success ? parsed.data.version : null,
    reason: parsed.success ? "ready" : missingFields.length ? "not_configured" : "invalid_configuration",
    missingFields: [...missingFields, ...invalidFields],
  }
}

function release(provider: Provider): ManagedDeploymentRelease | null {
  const parsed = schemaFor(provider).safeParse(releaseInput(provider, process.env))
  return parsed.success ? parsed.data : null
}
export function awsRelease(): ManagedDeploymentRelease | null { return release("aws") }
export function gcpRelease(): ManagedDeploymentRelease | null { return release("gcp") }
