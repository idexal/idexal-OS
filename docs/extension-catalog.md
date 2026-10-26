# Extension Catalog

The extension catalog is the canonical index of the **connectors**, **skills**
and **plugins** that idexal Os can surface in the library. Its repository is
[idexal/extensions-catalog](https://github.com/idexal/extensions-catalog), and
its contract is documented in
[that wiki](https://github.com/idexal/extensions-catalog/blob/main/docs/README.md).

## Why it exists

A desktop, an admin console and an MCP gateway all need to agree on what is
installable. Without one index, each surface grows its own hand-maintained list
and they drift. The catalog is the single source of truth; a parallel list is
treated as a defect.

## Entry types

| Type | What it is |
| --- | --- |
| `connector` | An MCP server or integration (Google Workspace, Microsoft 365, community servers) |
| `skill` | Task-scoped instructions the agent can load, delivered as Markdown |
| `plugin` | A bundle that ships several components together |

## How a catalog entry reaches a desktop

```text
extensions-catalog  â”€â”€â–¶  platform catalog snapshot  â”€â”€â–¶  desktop library
        â”‚                          â”‚                          â”‚
   entry identity          marketplace revision       install / uninstall
   (slug, type, version)   (feeds the inventory       keyed on the slug
                            revision)
```

The **slug** is the stable identity. Installations, capability lookup and
uninstall all key on it, which is why renaming a slug is a new entry rather than
an edit.

## Organisation assignment

An organisation can assign entries centrally. A member then sees exactly the
assigned set, and the desktop inventory carries it. Because assignment is an
admin write, it announces itself on the sync sequence â€” see
[Sync Contract](./sync-contract.md).

## Authoring an entry

Start from the catalog's
[submission guidelines](https://github.com/idexal/extensions-catalog/blob/main/docs/README.md/Submitting-Entries).
In short:

1. Give the entry a unique, lowercase, dash-separated slug.
2. Declare its type, version and source.
3. Write the description for a user, not a machine.
4. Validate against the schema before opening a pull request.

## Related reading

- [Extension Catalog wiki](https://github.com/idexal/extensions-catalog/blob/main/docs/README.md)
- [Sync Contract](./sync-contract.md)
- [Architecture](./architecture.md)
