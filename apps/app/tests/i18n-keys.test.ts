import { expect, test } from "bun:test"
import { readdirSync, readFileSync, statSync } from "node:fs"
import { join, relative, resolve } from "node:path"
import en from "../src/i18n/locales/en"

/**
 * A typo in a `t("...")` key is invisible in review and shows the raw key to the
 * user. `t()` falls back locale → English → the key itself, so a key that is
 * missing from English is the only case that leaks.
 *
 * Only files that import the i18n module are scanned, and only literal first
 * arguments containing a dot, so unrelated `t()` helpers and dynamic keys are
 * left alone.
 */
const srcRoot = resolve(import.meta.dir, "../src")
const english: Record<string, string> = en

const PLURAL_SUFFIXES = ["_zero", "_one", "_two", "_few", "_many", "_other"]

function sourceFiles(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) return sourceFiles(path)
    return /\.(ts|tsx)$/.test(name) ? [path] : []
  })
}

function knownKey(key: string): boolean {
  return english[key] !== undefined || PLURAL_SUFFIXES.some((suffix) => english[key + suffix] !== undefined)
}

test("every literal t() key in the app exists in English", () => {
  const problems: string[] = []
  let checked = 0

  for (const file of sourceFiles(srcRoot)) {
    if (file.includes(join("src", "i18n", "locales"))) continue

    const source = readFileSync(file, "utf8")
    if (!/from\s+"[^"]*\bi18n"/.test(source)) continue

    for (const match of source.matchAll(/\bt\(\s*"([A-Za-z0-9_.:-]+)"\s*[,)]/g)) {
      const key = match[1]
      if (!key.includes(".") || key.endsWith(".")) continue
      checked += 1
      if (knownKey(key)) continue
      problems.push(`${relative(srcRoot, file).replace(/\\/g, "/")} :: ${key}`)
    }
  }

  // A collapse here would mean the scan silently stopped finding call sites.
  expect(checked).toBeGreaterThan(500)
  expect(problems).toEqual([])
})
