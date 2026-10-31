---
name: idexal-os-connect
description: Search and use the skills, MCP connections, and connected services available through the user's idexal Os organization.
---

# idexal Os Connect

Use the idexal Os MCP server when the user asks for an organizational skill,
shared MCP tool, connected service, or idexal Os Cloud operation.

## Skills

1. Call `list_skills` to see every skill the member may use; no keywords are
   needed. Pass `query` only to narrow a long catalog.
2. Call `get_skill` with the skill's name or exact capability, read the whole
   returned SKILL.md, then follow it.

## Workflow

1. Call `search_capabilities` with a short description of the outcome unless
   the exact capability name is already available in the current context.
2. Select an exact capability name from the search result.
3. Call `execute_capability` with that exact name and only the parameters
   required for the requested outcome.
4. If idexal Os returns a connection, authentication, permission, or admin
   setup state, relay that state and its next action accurately. Do not claim
   that an unavailable capability ran.

Search results reflect the signed-in member's organization, grants, teams, and
connection readiness. Do not infer access from a capability that was visible
in another organization or an earlier authorization context.

idexal Os Connect performs OAuth through the MCP client. Never request, embed,
or persist idexal Os access tokens in plugin files or chat content.
