---
name: idexal-gateway-cookbook
description: Working recipes for the idexal Os gateway API. Use when writing code that calls the gateway, switching models, or debugging usage and limits.
---

# idexal Os Gateway Cookbook

One endpoint, every model your plan allows. Base URL is always the platform
origin: `https://os.idexal.com/v1`.

## 1. List available models

```bash
curl https://os.idexal.com/v1/models \
  -H "Authorization: Bearer YOUR_API_KEY"
```

The response lists only the models your plan enables. Model ids that belong to
a combo (for example `idexal-coding`) appear once, never as separate members.

## 2. Chat completion (OpenAI format)

```bash
curl https://os.idexal.com/v1/chat/completions \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "MODEL_ID",
    "messages": [{"role": "user", "content": "Hello"}]
  }'
```

## 3. Anthropic-style messages

`POST /v1/messages` accepts the Anthropic payload shape with the same key.

## 4. Streaming

Add `"stream": true`. The response is an SSE stream of
`data: {...}` lines ending with `data: [DONE]` (OpenAI format) or
`event: message_stop` (Anthropic format).

## 5. Usage and limits

```bash
curl https://os.idexal.com/v1/gateway/usage-limits/me \
  -H "Authorization: Bearer YOUR_API_KEY"
```

Returns the organization limit, what was used, and when it resets.

## Rules that save debugging time

- Keys carry abilities. A key without `gateway:chat` is rejected on
  `/chat/completions` even if valid.
- Quota errors come back as HTTP 429 with a JSON `error` body; back off and
  retry once before surfacing the failure to the user.
- Model ids are case-sensitive and provider-prefixed when needed; copy the
  exact `id` from `/v1/models`.
