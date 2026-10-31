import { AsyncLocalStorage } from "node:async_hooks"

/**
 * One request's shared reads. Several parts of a request often read the same
 * rows with the same inputs (the library, a connection list and its readiness
 * checks all list the member's connections); `requestRead` runs that query
 * once and hands every later or concurrent caller the same result.
 *
 * The memo lives for one request only: nothing crosses requests, and a nested
 * app.fetch gets its own. Sending any write statement drops the shared reads of
 * the request it runs in and of every request around it (see
 * forgetRequestReadsOnWrite), so a later read sees the write. A writer that
 * reads back its own row reads it directly: a concurrent caller may have shared
 * a read taken while the write was in flight. Use the memo only for reads
 * through the shared `db`, never inside a transaction or with locks, and never
 * mutate what it returns.
 */
type RequestReads = { shared: Map<string, Promise<unknown>>; outer: RequestReads | null }

const requestReads = new AsyncLocalStorage<RequestReads>()

export function withRequestReads<T>(run: () => Promise<T>): Promise<T> {
  return requestReads.run({ shared: new Map(), outer: requestReads.getStore() ?? null }, run)
}

/** Outside a request, this simply loads. */
export function requestRead<T>(key: string, load: () => Promise<T>): Promise<T> {
  const reads = requestReads.getStore()?.shared
  if (!reads) return load()
  const shared = reads.get(key)
  if (shared) return shared as Promise<T>
  const loading = load()
  reads.set(key, loading)
  // A failed read is not shared: the next caller tries again.
  loading.catch(() => {
    if (reads.get(key) === loading) reads.delete(key)
  })
  return loading
}

const ownerIds = new WeakMap<object, number>()
let lastOwnerId = 0

/**
 * requestRead keyed by an object every caller shares (a route's actor
 * context), for reads whose inputs are the whole object: callers holding the
 * same object share one read.
 */
export function requestReadFor<T>(owner: object, name: string, load: () => Promise<T>): Promise<T> {
  let id = ownerIds.get(owner)
  if (id === undefined) {
    id = ++lastOwnerId
    ownerIds.set(owner, id)
  }
  return requestRead(`${name}:#${id}`, load)
}

const WRITE_STATEMENT = /^\s*(insert|update|delete|replace)\b/i

/** Drizzle logger hook: a write drops every read shared so far, in this request and the ones around it. */
export function forgetRequestReadsOnWrite(query: string) {
  if (!WRITE_STATEMENT.test(query)) return
  for (let reads: RequestReads | null | undefined = requestReads.getStore(); reads; reads = reads.outer) reads.shared.clear()
}
