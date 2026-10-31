# Device login tests

Run the CLI protocol tests:

```sh
pnpm --filter idexal-os-bootstrap test
```

These tests run the shipped CLI script in a child process. Each test has a separate HOME and credentials file. The fixture replaces network requests. It controls the clock. It records request times, not codes or tokens.

The tests check these conditions:

- Pending approval keeps the specified poll interval.
- Approval can occur after two minutes.
- `slow_down` adds five seconds to later poll intervals.
- HTTP 429 does not require a specific response body.
- The CLI follows `Retry-After` and `X-Retry-After`.
- A missing or invalid delay cannot cause immediate retries.
- Retries stop at code expiry.
- A request or response body that stops responding is cancelled at expiry.
- Denial, expiry, and invalid grants do not save credentials.

A token received before the device deadline is a completed grant. The session then has its own lifetime. A later `/v1/me` check does not extend device polling.

The protocol tests do not prove server rate-limit behavior. Use `ee/apps/den-api/test/device-login/run.mjs` for that check. See the README in that directory for commands. No local test proves that production is fixed.
