# idexal Os Model Catalog

This directory holds the generated idexal Os model catalog. Cloudflare Pages builds it from `dev` through its Git integration and publishes the `models/` folder at `https://models.idexal.com`:

- Build command: `pnpm --dir ee/apps/gateway models:build`
- Build output directory: `ee/apps/gateway/models-site/models`
- Catalog URL: `https://models.idexal.com/api.json`

The generated `models/api.json` file is ignored by git. It is rebuilt from `src/models/base.json` and the active idexal Os overlay by `scripts/build-models.mjs`.

idexal Os-specific models live in `src/models/idexal-os-models.json`. `scripts/build-models.mjs` turns that list into the idexal Os provider overlay at build time and switches the provider API URL based on `IDEXAL_DEV_MODE`.

Local development still serves the generated catalog from the gateway Hono app at `/models/api.json` so one local service can provide both the proxy API and model catalog during dev.

## Caching

Clients get the Pages default `Cache-Control: public, max-age=0, must-revalidate` and always revalidate. Only Cloudflare's edge keeps a copy:

- `models/_headers` sets `Cloudflare-CDN-Cache-Control` on `/api.json`. The edge keeps the catalog for 5 minutes and serves the old copy while it refreshes or if Pages errors. Cloudflare does not forward this header to clients.
- A Cache Rule on the zone, set in the Cloudflare dashboard, makes the catalog cacheable: hostname `models.idexal.com`, path `/api.json` or `/models/api.json`, empty query string; Eligible for cache; Edge TTL "Use cache-control header if present, bypass cache if not"; Browser TTL "Respect origin".
- `.github/workflows/purge-model-catalog.yml` runs when the catalog sources change on `dev`. It rebuilds the catalog, waits until Pages serves those exact bytes, then purges both URLs. It needs the `MODELS_CLOUDFLARE_PURGE_TOKEN` secret (Cache Purge on this zone only) and the `MODELS_CLOUDFLARE_ZONE_ID` variable. Without them, or if the purge fails, it warns and the 5-minute TTL bounds staleness.
