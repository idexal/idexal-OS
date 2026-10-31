---
name: idexal-release-notes
description: Turns a list of merged changes into a professional, customer-ready release note. Use when preparing a version bump, changelog entry, or announcement.
---

# Release Notes Writer

## Input

A list of merged changes (titles, PR numbers, or bullet points) and the
version number.

## Output shape

1. **Title** — one line, benefit-first, no jargon. Example:
   "Faster model switching and a clearer catalog".
2. **One-line summary** — what a returning user notices first.
3. **Highlights** — 3 to 5 bullets. Each starts with a verb, names the
   feature, and says why it matters. No internal ticket numbers.
4. **Fixes** — short bullets, past tense.
5. **Upgrade steps** — only if action is needed; otherwise "Nothing to do —
   the update applies itself."

## Style rules

- Never use Arabic-Indic digits (٠١٢٣). Always Western digits: 0123456789.
- Never name internal systems, customers, or tokens.
- Say what the user gains, not what the code did.
- Keep the whole note under 150 words unless the release is major.

## Example

Input: "fixed combo rotation on quota exhaustion; added desktop catalog
diagnostics page"

Output:

### idexal Os 2.2.6

A desktop catalog you can explain, and combos that survive naming
differences.

**Highlights**
- Model combos now rotate members automatically when one runs out of quota.
- The admin catalog names the exact reason any model is hidden from a desktop,
  so nothing disappears without an explanation.
