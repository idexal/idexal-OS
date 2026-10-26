# Numerals Policy

## The rule

idexal Os renders numbers with **plain ASCII digits and Latin separators** in
every language and on every surface.

```text
9,073,504      â† what we render
Ù©Ù¬Ù Ù§Ù£Ù¬Ù¥Ù Ù¤      â† what we never render
```

This is not a localisation bug; it is a deliberate policy.

## Why

An agent workspace is read by people who move between languages in the same
session. A usage table, an invoice and a dashboard can be compared side by side,
and a mixed digit shape makes those numbers hard to scan and harder to copy. A
number that a user copies into an issue should paste as the digits they saw.

## What stays localised

Almost everything:

- **Text** â€” labels, headings, messages.
- **Dates** â€” order, weekday names, month names.
- **Currency** â€” symbol and placement.

Only the **digit shape** is pinned. Separators follow the pinned numbering
system so grouping stays consistent.

## How it is enforced

### Platform (PHP)

All formatted numbers go through `number_format`. Two helpers are the only code
that touches currency and counts:

- `money()` â€” currency amounts.
- `compact_number()` â€” abbreviated counts (`1.2K`).

A test asserts that no other path formats a number.

### Desktop

Date and number locales are pinned with the `-u-nu-latn` Unicode extension:

```js
new Intl.NumberFormat(`${locale}-u-nu-latn`)
```

Digit shapes are Latin while date order, weekday and month names, and currency
symbols stay correct for the requested locale.

### Web console

A single guard is installed at the application root. It pins `nu-latn` on every
locale passed to `Intl.NumberFormat`, `Intl.DateTimeFormat`, and
`Number`/`BigInt`/`Date.prototype.toLocale*`. One interception point is used
instead of editing ~100 call sites, so a new component cannot reintroduce the
problem by forgetting a locale option.

## For contributors

- Never format a number with a hand-rolled separator.
- Never pass a raw locale to an `Intl` constructor; the guard handles the
  extension for you, but explicit code should still be unambiguous.
- If you add a language, you get Latin digits for free. That is the point.

## Related reading

- [Architecture](./architecture.md)
- [FAQ](./faq.md)
