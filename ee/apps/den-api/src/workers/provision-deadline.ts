export function withProvisionDeadline<T>(input: {
  promise: Promise<T>
  deadlineMs: number
  label: string
}): Promise<T> {
  if (!Number.isFinite(input.deadlineMs) || input.deadlineMs <= 0) {
    return input.promise
  }

  let timer: ReturnType<typeof setTimeout> | undefined
  const deadline = new Promise<never>((_resolve, reject) => {
    timer = setTimeout(() => {
      reject(new Error(`${input.label} exceeded ${input.deadlineMs}ms provisioning deadline`))
    }, input.deadlineMs)
  })

  return Promise.race([input.promise, deadline]).finally(() => {
    if (timer !== undefined) clearTimeout(timer)
  })
}
