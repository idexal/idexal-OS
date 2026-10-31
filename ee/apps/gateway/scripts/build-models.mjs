import { mkdir, readFile, writeFile } from "node:fs/promises"
import path from "node:path"
import { fileURLToPath } from "node:url"

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const appDir = path.resolve(__dirname, "..")
const sourceDir = path.join(appDir, "src", "models")
const outputPath = path.join(appDir, "models-site", "models", "api.json")
const devIdexalOsApi = "http://127.0.0.1:8791/api/v1"
const prodIdexalOsApi = "https://inference.idexal.com/api/v1"

async function readJson(filePath) {
  return JSON.parse(await readFile(filePath, "utf8"))
}

function idexalOsProvider(models, api) {
  return {
    idexalos: {
      id: "idexalos",
      env: ["IDEXAL_API_KEY"],
      npm: "@openrouter/ai-sdk-provider",
      name: "idexal Os Models",
      api,
      models,
    },
  }
}

const isDevMode = process.env.IDEXAL_DEV_MODE === "1"
const base = await readJson(path.join(sourceDir, "base.json"))
const idexalOsModels = await readJson(path.join(sourceDir, "idexal-os-models.json"))
const idexalos = idexalOsProvider(idexalOsModels, isDevMode ? devIdexalOsApi : prodIdexalOsApi)
const models = { ...base, ...idexalos }

await mkdir(path.dirname(outputPath), { recursive: true })
await writeFile(outputPath, `${JSON.stringify(models)}\n`)

console.log(`[gateway] generated ${path.relative(appDir, outputPath)} (${isDevMode ? "dev" : "prod"})`)
