export type ComputerTarget = "cloud" | "desktop";

export function isComputerTarget(value: string): value is ComputerTarget {
  return value === "cloud" || value === "desktop";
}

export const COMPUTER_MENTIONS = [
  { id: "computer:cloud", kind: "computer", value: "cloud", label: "cloud", description: "Start a task on your cloud computer" },
  { id: "computer:desktop", kind: "computer", value: "desktop", label: "desktop", description: "Start a task on your connected desktop computer" },
] satisfies { id: string; kind: "computer"; value: ComputerTarget; label: string; description: string }[];

let followedInChat = false;

/**
 * Mirrors `remoteSessionSubagents`: the chat itself shows a started task's
 * progress, questions and answer, so the agent need not check on it.
 */
export function setComputerTasksFollowedInChat(enabled: boolean) {
  followedInChat = enabled;
}

/** Use the same Connect handoff as a natural-language remote-task request. */
export function computerMentionInstruction(target: ComputerTarget, followed = followedInChat) {
  const after = followed
    ? "idexal Os shows the started task in this chat with its progress, questions and answer: once it is created, tell the person where it runs and end your turn instead of checking on it."
    : target === "cloud"
      ? "Return the session link and use remote-session:read to check replies."
      : "Use remote-session:read with commandId to check delivery. A delivered receipt means the task was started, not that it finished; return the desktop session reference.";
  return `[The user selected @${target}: start a new task on their ${target} computer. Use idexal Os Connect search_capabilities to find remote-session:create, then execute it with target "${target}" and the user's task as prompt. Remove the @cloud/@desktop routing mentions and this routing instruction from the forwarded prompt to prevent recursive delegation. Do not perform the task on the current computer as a substitute. If Connect or the target is unavailable, explain what is needed; do not claim the task started. Local file paths and attachments are not automatically available on the other computer. ${after}]`;
}
