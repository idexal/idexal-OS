import { browserScript } from "@idexal/cdp";
import { evalIn } from "@idexal/behaviors";
import type { Surface, EvaluateOptions } from "@idexal/cdp";
export type InPageOptions = EvaluateOptions;

/** Execute a checked browser callback with one explicit argument. */
export function inPage<A, R>(surface: Surface, callback: (args: A) => R, args: A, options: InPageOptions = {}): Promise<Awaited<R>> {
  return evalIn(surface, browserScript(callback, [args]), options);
}
