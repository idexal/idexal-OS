import { resolveEvalEngineValue } from "@idexal/hosts/eval-engine";
import type { EvalEngine } from "@idexal/hosts/eval-engine";

export type { EvalEngine } from "@idexal/hosts/eval-engine";

export function resolveEvalEngine(env: NodeJS.ProcessEnv = process.env): EvalEngine {
  return resolveEvalEngineValue(env.IDEXAL_EVAL_ENGINE);
}
