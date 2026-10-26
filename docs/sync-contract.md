# Sync Contract

The sync contract answers one question: **when does a running desktop learn that
an administrator changed something it is allowed to offer?**

## The short answer

Every admin write that can change what a desktop may offer advances a single
shared *inventory revision*. A desktop compares that revision on a schedule and
subscribes to an event stream so it reacts immediately. When the revision
differs from the last one it synced, it reloads its inventory. When it has not
synced for longer than a maximum age, it reloads anyway â€” so a change the
platform could not attribute still arrives.

## What the desktop reads

```http
GET /v1/org/inventory-revision
Authorization: Bearer <desktop credential>
```

```json
{
  "revision": "d41d8cd98f00b204e9800998ecf8427e",
  "generatedAt": "2026-10-26T13:55:33Z"
}
```

The revision is a digest over every input that can change inventory:

```text
revision = md5(
    marketplace revision      # the catalog snapshot identity
  | provider stamp            # newest change across providers
  | model stamp               # newest change across models
  | DesktopSync revision      # the announced change sequence
  | organization               # the tenant the credential belongs to
)
```

## The event stream

```http
GET /v1/inference-providers/inventory/events
Accept: text/event-stream
```

When the shared sequence moves, the server emits:

```text
event: inventory.changed
data: {"action":"provider.updated","revision":"d41d8â€¦"}
```

A running app reacts without waiting out its poll interval. The stream is an
optimisation, never the only path: polling remains the fallback, which is why a
dropped connection degrades latency rather than correctness.

## What announces itself

Every admin write path below announces on the shared sequence:

| Area | Write paths |
| --- | --- |
| Providers | create, update, delete, key rotation |
| Models | create, update, delete, alias change |
| Combos | create, update, delete, member change |
| Plans | create, update, delete, entitlement change |
| Settings | any key that changes what a desktop may offer |
| Proxy providers | create, update, delete |
| Organisations | create, status change, credit change |
| Provider keys | create, revoke |
| Extensions | install, update, uninstall, assignment |

## Why a shared sequence, not timestamps

A deletion can leave a table's newest `updated_at` unchanged â€” the row that
disappeared no longer contributes a timestamp. A sequence that every write
advances catches deletions that a timestamp-only scheme misses. This is the
reason the design exists in this shape; see below for the bug that proved the
point.

## A correctness note

On the production cache store (the database), an increment against a **missing**
key returned `false` and left the key missing rather than starting it at zero.
The sequence therefore never existed, so the first change â€” and every change â€”
was invisible. The sequence is now seeded with an atomic add before it is
incremented, and a cold-cache regression test keeps it that way.

## Client obligations

- Compare revisions; do not diff payloads to decide whether to sync.
- Honour the maximum age even when the revision matches.
- Treat the event stream as an accelerator, not a guarantee.
- Surface a sync failure to the user; do not silently serve stale entitlements.

## Server obligations

- Advance the sequence on **every** write that can change inventory.
- Keep the revision stable when nothing changed.
- Never require the desktop to know the shape of a table to detect a change.
