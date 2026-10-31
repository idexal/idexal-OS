import { expect, test } from "bun:test"
import { interpolate, t } from "../src/i18n"

test("interpolate fills every occurrence of the same placeholder", () => {
  expect(interpolate("{name} asks for two codes from your {name} app.", { name: "Notion" }))
    .toBe("Notion asks for two codes from your Notion app.")
})

test("interpolate inserts values literally, not as replacement patterns", () => {
  // `String.prototype.replace` would expand `$&`, `$1` and `$'` inside the value.
  expect(interpolate("Value: {value}", { value: "$& and $1 and $'" }))
    .toBe("Value: $& and $1 and $'")
})

test("interpolate leaves placeholders without a matching param untouched", () => {
  expect(interpolate("Hello {who} and {name}", { name: "Sam" })).toBe("Hello {who} and Sam")
})

test("t fills repeated placeholders and ignores the lng selector", () => {
  expect(t("extensions.connector_note_codes", { lng: "en", name: "Notion" }))
    .toBe("Notion needs two codes before you can sign in. Add them, then continue with your own account.")
})

test("t keeps a dollar-bearing value intact", () => {
  expect(t("extensions.connector_setup_title", { lng: "en", name: "$& Co" })).toBe("Set up $& Co")
})
