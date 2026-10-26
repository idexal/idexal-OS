# Hybrid Combos

A **hybrid combo** â€” a *combo* â€” is a model that is defined as a set of other
models plus a routing strategy. To a client it is a single model id; underneath
it is several providers.

## Why combos exist

Provider neutral is a claim about capability, not convenience. Without combos a
user who wants "the cheapest model that still passes my tests" has to switch
models by hand. A combo encodes that decision once and resolves it at call time.

## Anatomy

```jsonc
{
  "id": "idexal-coding",
  "name": "idexal Coding",
  "strategy": "failover",          // round_robin | role | failover
  "members": [
    { "model": "claude-opus",   "role": "coding"   },
    { "model": "gpt-5",         "role": "research" },
    { "model": "gpt-4o-mini",   "role": "fallback" }
  ]
}
```

| Strategy | Behaviour |
| --- | --- |
| `round_robin` | members are used in turn |
| `role` | the request's role selects the member |
| `failover` | the first member is primary; the rest are used on failure |

## What a combo is not

- A combo **cannot** include another combo as a member.
- A combo **cannot** take the public id of a live provider model.

Both rules keep resolution a single hop and keep ids unambiguous.

## Member visibility

While a combo exists, its **member models are hidden from the catalog**. A client
that asks for a member by name gets the combo's routing instead. When the combo
is deleted the members return automatically â€” nothing about them was mutated;
they were only claimed by the combo.

## Client expectations

- A combo appears in the picker as one entry, with its strategy and members
  visible so the user knows what they are selecting.
- Token accounting attributes usage to the member that served the request, and
  the combo id as the requested model.
- Deleting a combo is a normal inventory change and announces itself on the
  sequence (see [Sync Contract](./sync-contract.md)).

## Operator notes

- Name combos by **intent**, not by vendor: `idexal-coding` ages better than
  `opus-plus-gpt`.
- A combo that rotates across providers is easy to run away with; treat spend
  ceilings as a first-class follow-up (see the roadmap).

## Related reading

- [Sync Contract](./sync-contract.md) â€” how a combo change reaches a desktop.
- [Extension Catalog](./extension-catalog.md) â€” how models are surfaced alongside
  skills and connectors.
