# Secure keys in chat (`secureSecretFields`, off by default)

An agent sets something up, a tool needs a key to continue, the person types
the key into a private field in the same chat, and the agent carries on. The
key never reaches the model, the transcript or a tool argument. The same
private field is used for every key typed in idexal Os and idexal Os Cloud.

## Pieces

1. **Credential prompt** (`packages/types/src/den/credential-prompt.ts`): Den
   tool-result metadata naming the saved resource and where the key goes. The
   schema is strict and has no value field. The fields drawn for each target
   are fixed in the app, never taken from the result.
2. **Shared secret field** (`packages/ui/src/react/secret-input.ts`): the
   privacy and no-autofill attributes, reveal only while typing, the fixed
   `••••••••` saved state, and key validation. The desktop `SecretField` and
   den-web `DenSecretInput` both build on it.
3. **Human-only writes in Den**: agents (`mcp_internal`) are always refused
   when they send a key, and API keys are refused while the flag is on
   (`secure_entry_required`, `middleware/refuse-machine-secrets.ts`).

## Keys the agent can ask for in chat

| Target | Saved through (people only) |
| --- | --- |
| Shared API key of an org MCP connection | `PUT /v1/mcp-connections/:id/shared-credential` (hidden) |
| Key of a plugin's declared MCP server | `POST /v1/plugins/:id/mcp-connections` (the plugin's own setup) |
| AI Gateway provider: single API key | `PATCH /v1/inference-providers/:id/credential-sets/:setId` |
| AI Gateway provider: AWS keys (Bedrock), Google service-account key (Vertex), or several named keys | same route, structured body |

The app shows a prompt only for results of Den's own operations
(`postMcpConnections`, `postPluginsMcpConnections`, `postInferenceProviders`),
so another MCP server can't make it draw a key field. A key the server refuses
is not kept, and provider error text is never echoed or logged, because it
can repeat the key.

## Forms

The forms below use the shared field. With the flag on, they offer "Show while
typing", and keys that are already saved appear as a `••••••••` row with a
Replace action:
- den-web: connectors (API keys and OAuth app secrets), native Google and
  Microsoft clients, personal keys, marketplace plugin setup, the Slack
  signing secret, AI Gateway providers (OAuth secret, service-account JSON,
  which is now masked, AWS keys, env keys, single key), LiteLLM, legacy custom
  LLM providers, Langfuse, the egress token and the OIDC client secret.
- Desktop: personal keys, Library connectors, new library items, OAuth client
  secrets in Add MCP, and local provider keys.

Account passwords keep normal password-manager support.

## Not in scope

Arbitrary custom headers, OAuth app credentials requested by an agent in chat
(set them in the connection form), LiteLLM in chat, and local environment
variables. Environment variables are arbitrary values with their own reveal
control.

## Proof

`evals/specs/secure-keys-in-chat.e2e.test.ts` runs on engines v1 and v2. It
covers the MCP key, a rejected key, the AI Gateway provider key, a Vertex
service-account key file, the refused legacy LLM provider key, the den-web
saved and Replace row, and the rollout turned off. It also checks that no key
appears in the page, any model request or Den's log.
