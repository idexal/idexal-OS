# FAQ

### Does the desktop upload my files?

No. Desktop mode reads and writes files on your machine. Only entitlements,
inventory and inference traffic leave the app, and only when you run against a
platform.

### Do I need the platform at all?

No. Add a provider with your own key and the app works standalone. The platform
adds central credentials, organisation entitlements and a managed catalog.

### Which providers and models can I use?

Any model from 50+ providers. A [hybrid combo](./hybrid-combos.md) lets one public
model id route across several providers.

### Why is a model missing from the picker?

Three common causes, in order:

1. Your plan or organisation does not entitle you to it.
2. A combo claimed it â€” member models are hidden while the combo exists.
3. Your inventory is stale; the revision has not been re-read yet. See
   [Sync Contract](./sync-contract.md).

### I changed something in the admin console and nothing happened.

If the desktop is running, it should react within seconds. If it does not, the
write path may be silent â€” that is a bug. Confirm the inventory revision moves:

```bash
curl -fsS -H "Authorization: Bearer $TOKEN" \
  https://your-host/v1/org/inventory-revision
```

### Why do numbers not use my locale's digits?

By policy. Numbers render as ASCII digits with Latin separators in every
language; text, dates and currency names stay localised. See
[Numerals Policy](./numerals-policy.md).

### How do I report a bug?

Open an issue with: what you expected, what happened, your OS and app version,
and the exact steps to reproduce. Log lines help. Redact anything private.

### How do I request a feature?

Open an **Ideas** discussion. If it is picked up it becomes a draft item in
[Projects](https://github.com/idexal/idexal-OS/projects).

### Is the wiki the same as the docs site?

This wiki documents the platform's contracts in depth. The docs site is the
task-oriented guide. When they disagree, the contracts here win and the docs
site is the thing to fix.

### Can I run the whole thing on my own infrastructure?

Yes. See [Self-Hosting](./self-hosting.md).

### Where do skills and connectors come from?

The [extension catalog](./extension-catalog.md). An organisation can assign a set
centrally so every member sees the same entries.
