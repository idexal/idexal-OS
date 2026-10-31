export type DecodedFileContent =
  | { kind: "text"; content: string; truncated: boolean }
  | { kind: "binary"; contentBase64: string; byteSize: number }
  | { kind: "too_large"; byteSize: number }

export function truncateText(text: string, maxCharacters: number): { text: string; truncated: boolean } {
  if (text.length <= maxCharacters) {
    return { text, truncated: false }
  }
  return { text: text.slice(0, maxCharacters), truncated: true }
}

export function decodeFileContent(
  bytes: Uint8Array,
  options: { maxTextCharacters: number; maxBinaryBytes: number },
): DecodedFileContent {
  try {
    const decoded = truncateText(new TextDecoder("utf-8", { fatal: true }).decode(bytes), options.maxTextCharacters)
    return { kind: "text", content: decoded.text, truncated: decoded.truncated }
  } catch {
    if (bytes.length > options.maxBinaryBytes) {
      return { kind: "too_large", byteSize: bytes.length }
    }
    return { kind: "binary", contentBase64: Buffer.from(bytes).toString("base64"), byteSize: bytes.length }
  }
}

/** Reads at most maxBytes, so an oversized provider body never sits in memory whole. */
export async function readBoundedBytes(response: Response, maxBytes: number): Promise<{ bytes: Uint8Array; truncated: boolean }> {
  if (!response.body) return { bytes: new Uint8Array(0), truncated: false }
  const reader = response.body.getReader()
  const chunks: Uint8Array[] = []
  let length = 0
  let truncated = false
  try {
    while (true) {
      const { done, value } = await reader.read()
      if (done) break
      const room = maxBytes - length
      if (value.byteLength > room) {
        chunks.push(value.subarray(0, room))
        length += room
        truncated = true
        break
      }
      chunks.push(value)
      length += value.byteLength
    }
  } finally {
    if (truncated) await reader.cancel().catch(() => {})
    reader.releaseLock()
  }
  const bytes = new Uint8Array(length)
  let offset = 0
  for (const chunk of chunks) {
    bytes.set(chunk, offset)
    offset += chunk.byteLength
  }
  return { bytes, truncated }
}

/** Text from a read that may stop mid-character: a trailing partial character is dropped. */
export function decodeTruncatedText(bytes: Uint8Array, fatal: boolean): string {
  return new TextDecoder("utf-8", { fatal }).decode(bytes, { stream: true })
}
