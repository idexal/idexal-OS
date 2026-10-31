# Community feedback backlog

Feedback gathered from the public tracker, kept as a work list. Contributors are
not named here; see the linked discussion for attribution.

Source: `idexal/idexal-OS` discussion #12 — "Idea: plugin permission scopes and an
audit trail".

## Captured proposals

### 1. Retire a scope by revising the grant, never by aliasing it
- Keep the installed slug/version **separate** from the approved scope set and the
  grant revision.
- A revocation is the next revision of the grant, not a silent rename of an old
  scope to a replacement.
- Invariant to hold: **a stale desktop inventory must not restore a revoked grant.**

### 2. Offline leases / expiry
- A disconnected executor cannot observe a revocation immediately, so define an
  explicit lease/expiry policy instead of implying instant revocation.

### 3. One-use approval for high-risk runs
- Show the concrete operation **and** the resolved target before approval.
- Consume the one-use approval **before** dispatch, and also on **failed** attempts,
  so a retry/replay cannot reuse it.

### 4. Reference fixture (both replies converge on this)
- Install a scoped connector → exercise one grant → revoke mid-run. Concretely:
  queue a request to `api.example.test`, revoke the egress scope, drop the inventory
  event, then reconnect.
- Assert **together**: (a) the polling refresh blocks dispatch, and (b) the audit
  trail records the revocation against the current grant revision — not a stale
  permission set.
- A generic "run failed" log must **not** be able to pass this fixture.

### External reference raised
- A bounded **one-use call-grant** model delivered through an explicit stdio gateway
  was offered as a reference only (MIT, Windows-first, untested against idexal Os).
  Useful for the exact-grant piece; not a substitute for native plugin scopes or
  organization-wide revocation.

## Next actions (not started)

- [ ] Permission/scope schema: `fs:read:<path>`, `net:egress:<host>`, `tools:invoke`
      plus a grant-revision field.
- [ ] Lease/expiry semantics for offline executors.
- [ ] One-use approval consumption, including the failure path.
- [ ] Mid-run revocation fixture as a feature test (item 4).
- [ ] Audit trail records grant revision + revocation reason, never a stale set.
