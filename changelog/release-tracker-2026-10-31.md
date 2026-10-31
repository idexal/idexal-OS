# Release Changelog Tracker

Internal preparation file for release summaries. Not published until the deploy completes.

## v3.13.0 — 2026-10-31

#### Scope

- Website: skill pages preview the real `SKILL.md`, related entries ranked by
  shared words, catalogue tags cleaned.
- Desktop: empty-state for a plan with no assigned providers.
- Project: `DESIGN.md` added, derived from the shipped tokens.
- Versions: site and desktop packages move to 3.13.0. The newest installer is
  still 3.10.2.

#### Verified

- `php artisan test` — **596 passed** (3,630 assertions).
- `pnpm typecheck` in `apps/app` — clean.
- Screenshots of the live entry page, English and Arabic.

## v3.12.0 — 2026-10-31

#### Scope

- Website: a page per catalogue entry (`/integrations/{type}/{slug}`), listing
  links, featured links, sitemap entries, translations.
- Desktop: Settings → AI shows the providers the active plan reaches, and
  states when own provider keys are disabled.
- Versions: site and desktop packages move to 3.12.0. The newest installer is
  still 3.10.2; this release does not ship a binary.

#### Verified

- `php artisan test --filter=PublicIntegrationsPageTest` — 13 passed, including
  detail pages, 404s, listing links, sitemap coverage and Arabic.
- `pnpm typecheck` in `apps/app` — clean.
- Screenshots of a live entry page in English and Arabic.

## v3.11.2 — 2026-10-31

#### Scope

Site-only. The header navigation is rebuilt as a mega menu: structured
two-column panels with icons and descriptions, a start-free card, hover intent,
click-outside, Escape with focus return, arrow-key entry, a viewport cap, and
logical start/end alignment for Arabic.

#### Verified

- Screenshots at 1440 px in English and Arabic; the products panel is 752×332
  and stays inside the viewport.
- Real keyboard: focus opens, ArrowDown focuses "idexal Os Desktop", Escape
  closes and returns focus to "Products" without reopening.
- Full Laravel suite: 583 tests pass (version pin moved to 3.11.2).

## v3.11.1 — 2026-10-31

#### Commit

The working-tree changes below: a bundled skills catalogue, a required plan on
first desktop launch, and a navigation bar that no longer wraps. This is a
**site release**. No desktop binary changed, so the newest installer stays at
3.10.2; that distinction is now stated on the download page instead of being
implied away.

#### Verified

- `php artisan test --filter=PublicIntegrationsPageTest` — 8 passed. The page
  renders 285 bundled entries with no network and an empty cache.
- `php artisan test --filter=PlatformDesktopAccessTest` — 9 passed, including a
  new test that a fresh install (no settings row) blocks the desktop until a
  plan exists.
- `php artisan test --filter=CanonicalHostTest` — the API version and the
  newest release-history entry are asserted equal.
- Header screenshots at 1440/1280/1024/390 px in English and Arabic.
- Live before the fix: `https://os.idexal.com/integrations` reported
  Skills 0, Plugins 0, Connectors 0, Total 0.

#### Publication status

| Target | Status |
| --- | --- |
| Local repo + changelog | done |
| Git tag `v3.11.1` | done locally; push needs credentials |
| GitHub release `v3.11.1` | **blocked** — no GitHub token/CLI in this session |
| Site deploy to `os.idexal.com` | **blocked** — SSH/Plesk credentials not used without approval |

#### Commands once credentials are available

```bash
# push the commit and tag
git push origin main && git push origin v3.11.1

# publish the GitHub release (site release: no installers to attach)
gh release create v3.11.1 --title "idexal Os 3.11.1" --notes-file changelog/release-tracker-2026-10-31.md

# deploy the Laravel site
cd website-laravel-idexalOs
php artisan config:clear && php artisan view:clear && php artisan route:clear
npm run build
php artisan cache:clear
php artisan queue:restart
# then sync app/, resources/, public/build/, config/, lang/ to /var/www/os.idexal.com
```

#### Known gaps not in this release

- The desktop installer for Windows arm64 is still 2.2.5; x64 is 3.10.2.
- The desktop's `SUGGESTED_SKILLS` list is still three hand-written entries;
  the marketplace itself now delivers the 285-entry catalogue.
- No browser E2E covers signup → plan → payment → desktop hand-off end to end.
