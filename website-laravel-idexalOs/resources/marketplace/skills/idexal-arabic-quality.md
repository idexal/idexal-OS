---
name: idexal-arabic-quality
description: Reviews Arabic interfaces for global quality — Western digits, correct separators, mixed-direction text, and respectful tone. Use when auditing Arabic pages or strings.
---

# Arabic UI Quality Review

The platform serves every country. Arabic is one interface language, not one
number system. Enforce these rules on every Arabic surface.

## Numbers (the most common failure)

- ALWAYS Western digits: 1, 2, 3 — 9073504 — 1,234.50
- NEVER Arabic-Indic digits: ٠١٢٣٤٥٦٧٨٩ or Eastern Persian ۰۱۲۳۴۵۶۷۸۹
- Thousands separator: comma (,). Decimal separator: dot (.)
- Currency: `USD 1,234.50` or `1,234.50 USD` — never `١٬٢٣٤٫٥٠`

Search the rendered page for the ranges U+0660–U+0669 and U+06F0–U+06F9.
Any hit is a defect.

## Mixed-direction text

- Latin tokens inside Arabic sentences (model names, URLs, code) stay LTR.
- Parentheses must mirror correctly: في الرابط `https://os.idexal.com` ثم
  أكمل الجملة.
- Numbers adjacent to Latin words keep Latin separators.

## Tone

- Address the reader respectfully; no slang, no regional idioms tied to one
  country.
- Prefer clear Modern Standard Arabic over dialects.
- Keep technical terms in English when users search for them in English
  (model id, API key, gateway).

## Checklist

1. grep for Arabic-Indic digit ranges — zero allowed.
2. grep for `٬` (Arabic thousands separator) and `٫` (Arabic decimal) — zero.
3. All dates use `YYYY-MM-DD` or the locale's Latin-digit form.
4. RTL layout holds for tables: numbers right-aligned, headers readable.
