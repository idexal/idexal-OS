import type { AutomationNeedsAttentionReason } from "@idexal/types/automations"

export type CloudArtifactStateUpdate =
  | {
      kind: "succeeded"
      state: "active"
      needsAttentionReason: null
      latestSuccessfulRunId: string
      latestSuccessfulResult: unknown
    }
  | {
      kind: "failed"
      state: "needs_attention"
      needsAttentionReason: AutomationNeedsAttentionReason
    }

export function cloudArtifactStateUpdate(input: {
  runId: string
  result: { ok: true; value: unknown } | { ok: false; message: string }
  now: number
}): CloudArtifactStateUpdate {
  if (input.result.ok) {
    return {
      kind: "succeeded",
      state: "active",
      needsAttentionReason: null,
      latestSuccessfulRunId: input.runId,
      latestSuccessfulResult: input.result.value,
    }
  }
  return {
    kind: "failed",
    state: "needs_attention",
    needsAttentionReason: {
      code: "connect_access_unavailable",
      message: input.result.message,
      occurredAt: input.now,
    },
  }
}
