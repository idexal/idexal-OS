# DESIGN.md

The design rules for every user-facing surface in this repository: the Laravel
site (`website-laravel-idexalOs`) and the desktop app (`apps/app`). Read this
before designing or changing UI, and cite the rule ids in the pull request.

This file was missing while the product shipped; the rules below are extracted
from the tokens and components that already exist in
`website-laravel-idexalOs/resources/css/app.css`, so it records the system
rather than inventing a new one.

## DS-01 — Brand and voice

- One wordmark: the committed logo assets. Never re-set the name in a font.
- Voice: direct, specific, and calm. Sentence case for headings and buttons.
- Never invent a metric, a customer, a quote or a date. A number on a page must
  come from configuration, the database or a published release.
- The product is global. Write copy that works for any country; no regional
  idiom, no flag, no culturally specific figure of speech.

## DS-02 — Colour

Tokens, not hex values, in new markup:

| Token | Value | Use |
| --- | --- | --- |
| `paper` | `#f7f6f1` | page background |
| `paper-2` | `#efede5` | alternating band, quiet surface |
| `ink-950` | `#080a08` | primary text, dark sections, primary button |
| `ink-500` / `ink-400` | `#5c6859` / `#7c8978` | body copy / metadata |
| `ink-100`–`ink-300` | greys | borders, dividers, disabled |
| `lime` | `#7dff00` | one accent: emphasis, active state, dark-section highlight |
| `lime-dark` | `#4fae00` | lime text on light backgrounds (contrast) |

- Lime is an accent, never a background for large areas of text.
- On dark surfaces use `text-paper` and `text-paper/55` for secondary copy.
- Do not introduce a second accent colour.

## DS-03 — Typography

- Sans: `IBM Plex Sans Arabic`, `Instrument Sans`, system fallbacks
  (`--font-sans`). Mono: `IBM Plex Mono` (`--font-mono`) for code, identifiers,
  versions and numeric tables.
- Display: `text-4xl` → `text-6xl`, `font-semibold`, `tracking-[-0.03em]`,
  `text-balance`. One `h1` per page.
- Section titles: `text-2xl` → `text-3xl`.
- Body: `text-base leading-8` for hero copy, `text-sm leading-7` for cards and
  document sections. Never below `text-xs` for prose.
- Metadata and overlines: `text-[11px]` / `text-xs`, `font-mono` or uppercase
  with `tracking-[0.14em]`.

## DS-04 — Layout and rhythm

- Page gutter: `container-x` (`max-w-7xl`, `px-5 sm:px-8`). Never hand-roll a
  container.
- Section spacing: `py-16` on small screens and `lg:py-20`–`lg:py-28` on large.
- Alternate bands with `bg-paper-2/60` or `bg-white/60`; do not stack more than
  two full-bleed surfaces without a band.
- Grids: `gap-3` for dense catalogues, `gap-4` for cards, `gap-10`+ between
  major columns.
- Text columns stop at `max-w-2xl` / `max-w-3xl`; a full-width paragraph is a
  bug.

## DS-05 — Components

Use the existing classes; do not re-implement them inline.

- Buttons: `.btn` plus one variant (`.btn-primary`, `.btn-lime`, `.btn-outline`,
  `.btn-ghost`, `.btn-danger`) and optional `.btn-sm`. Icon-only actions use
  `.btn-icon` with an `aria-label`.
- Surfaces: `.card` (raised), `.card-flat`, `.card-dark`, padding via
  `.card-pad`.
- Inputs: `.input`, `.select`, `.checkbox`, always with a `.label` or a visually
  hidden label.
- Badges: `.badge` plus a tone; used for type, status and plan, never as a
  button.
- Every interactive element has a visible `:focus-visible` outline (set
  globally) and a hover state.

## DS-06 — Header and navigation

- The header is 72px tall, sticky, translucent (`bg-paper/85 backdrop-blur-xl`)
  with a 1px bottom border.
- The desktop menu appears at `xl` (1280px). Between 1024px and 1279px the
  drawer is used; the primary sign-up action stays visible from `sm`.
- Navigation never wraps: `flex-nowrap`, `whitespace-nowrap`, `shrink-0` on the
  logo and the account actions.
- Mega menus: structured columns, each entry with an icon, a name and one line
  of description; a footer row links the section and its neighbour.
- Panel behaviour: 90ms open delay, 180ms close delay, close on outside click,
  Escape closes and returns focus to the trigger, Down enters the panel, and
  panels are measured on open and shifted inside the viewport.
- A panel must never be the only way to reach a page: the trigger is a real
  link and the footer row repeats the section.

## DS-07 — Right-to-left and localisation

- Use logical properties: `ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`, `end-*`.
  A physical `left`/`right` in shared markup is a bug.
- Directional icons flip with `rtl:rotate-180`; the arrow must point the way the
  text reads.
- Arabic is a first-class locale, not a translation layer: every new string gets
  an `ar` entry in the same change, and the page is checked at 390 / 1024 /
  1440px in both directions.
- Numerals: Western digits (`123456789`) in both locales; a Latin technical
  value (identifier, version, URL) keeps `dir="ltr"`.

## DS-08 — Motion

- Interaction transitions: 150ms (`duration-150`). Panel enter: 150ms ease-out,
  leave: 100ms ease-in.
- Animate opacity and a 2–4px translate only. Never animate width, height or
  layout position.
- Content reveals use `.reveal` / `.is-visible`; `prefers-reduced-motion` must
  keep everything visible and still.

## DS-09 — Accessibility

- Every page starts with the skip link, one `h1`, and landmark regions
  (`header`, `nav` with an `aria-label`, `main`, `footer`).
- Icon-only controls carry `aria-label`; decorative icons are `aria-hidden`.
- Menus expose `aria-haspopup`, `aria-controls` and a live `aria-expanded`.
- Contrast: body text on paper is `ink-500` or darker; lime text is only used on
  ink-950; lime on white is reserved for fills and dots.
- Status is never conveyed by colour alone: pair it with a word.

## DS-10 — Catalogue and store patterns

- A listing card is one link with a title, a one-line description and a type
  badge. A nested link inside a card must be raised above the card's stretched
  link (`relative z-10`).
- Every catalogue item has its own page. The page shows what it does, where it
  comes from, how it reaches a workspace, the opening of its real instructions,
  and related entries. No item links straight to a third party as its only
  destination.
- Counts are computed from the catalogue at render time; never hard-code a
  number in copy.

## DS-11 — Content structure for marketing pages

- Hero: one promise, one sentence of proof, one primary action, one secondary.
- Then, in order: what it is, who it is for, how it works, proof, pricing, FAQ.
- Every claim that names a capability must link to the page that demonstrates it
  (docs, store, pricing, changelog).
- Empty states state the truth and the next action; never a blank region.

## Review checklist

Before calling UI work done:

1. Screenshots at 390, 1024, 1280 and 1440px, English and Arabic.
2. Keyboard pass: Tab order, Escape on overlays, focus returns where it left.
3. No horizontal scroll at any of those widths.
4. Every new string exists in both `lang/en` and `lang/ar`.
5. The page's numbers come from data, not from the template.
