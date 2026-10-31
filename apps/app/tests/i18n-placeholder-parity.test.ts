import { expect, test } from "bun:test"
import ar from "../src/i18n/locales/ar"
import ca from "../src/i18n/locales/ca"
import en from "../src/i18n/locales/en"
import es from "../src/i18n/locales/es"
import fr from "../src/i18n/locales/fr"
import ja from "../src/i18n/locales/ja"
import ptBR from "../src/i18n/locales/pt-BR"
import ru from "../src/i18n/locales/ru"
import th from "../src/i18n/locales/th"
import vi from "../src/i18n/locales/vi"
import zh from "../src/i18n/locales/zh"

/**
 * A translation may reword a sentence freely, but it must keep the `{token}`
 * placeholders English uses. A dropped, renamed or machine-translated token
 * (e.g. `{entorno de ejecuciónWorkspaceId}`) reaches the user as literal
 * braces, because `interpolate` only fills the names the caller passes.
 */
const english: Record<string, string> = en
const catalogs: Record<string, Record<string, string>> = { ar, ca, es, fr, ja, "pt-BR": ptBR, ru, th, vi, zh }

const tokens = (value: string): Set<string> => new Set(value.match(/\{[^{}\n]*\}/g) ?? [])

test("no locale invents or drops a placeholder the English copy does not use", () => {
  const problems: string[] = []

  for (const [locale, catalog] of Object.entries(catalogs)) {
    for (const [key, value] of Object.entries(catalog)) {
      const template = english[key]
      if (template === undefined) continue

      const wanted = tokens(template)
      const found = tokens(value)
      for (const token of wanted) {
        if (!found.has(token)) problems.push(`${locale} ${key}: missing ${token}`)
      }
      for (const token of found) {
        if (!wanted.has(token)) problems.push(`${locale} ${key}: stray ${token}`)
      }
    }
  }

  expect(problems).toEqual([])
})

test("every placeholder is an identifier the interpolator can fill", () => {
  const problems: string[] = []

  for (const [locale, catalog] of Object.entries({ en, ...catalogs })) {
    for (const [key, value] of Object.entries(catalog)) {
      for (const token of tokens(value)) {
        if (!/^\{[A-Za-z0-9_]+\}$/.test(token)) problems.push(`${locale} ${key}: malformed ${token}`)
      }
    }
  }

  expect(problems).toEqual([])
})
