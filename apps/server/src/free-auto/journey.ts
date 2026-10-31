import { randomBytes } from "node:crypto";
import { mkdir, readFile, writeFile } from "node:fs/promises";
import { join } from "node:path";
import { runtimeStorageDir } from "../runtime-db.js";
import type { ServerConfig } from "../types.js";

/** Random analytics correlation only. It never authorizes requests or changes the IP-based guest allowance. */
export async function readFreeAutoJourney(config: Pick<ServerConfig, "configPath">): Promise<string> {
  const fallback = randomBytes(32).toString("hex");
  const directory = runtimeStorageDir(config);
  const path = join(directory, "free-auto-journey");
  try {
    await mkdir(directory, { recursive: true });
    await writeFile(path, fallback, { flag: "wx", mode: 0o600 }).catch((error: unknown) => {
      if (!(error instanceof Error) || !("code" in error) || error.code !== "EEXIST") throw error;
    });
    const value = (await readFile(path, "utf8")).trim();
    return /^[a-f0-9]{64}$/.test(value) ? value : fallback;
  } catch { return fallback; }
}
