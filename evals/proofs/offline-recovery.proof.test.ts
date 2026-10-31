import { expect } from "vitest";
import { spec } from "@idexal/testkit";
import { offlineRecoveryWorld, MCP_PROMPT, MCP_REPLY, APP_CONNECTION_ERROR } from "../worlds/offline-recovery.ts";

const test = spec.world(offlineRecoveryWorld, {
  resources: { surfaces: ["appWeb"], services: ["den", "mock"] }, timeout: 480_000,
});

test("a member can distinguish an unavailable App view from a failed tool call in OpenCode v2", async ({ world, user, probe, step, evidence }) => {
  await step("before: an App connection failure fills the conversation with transport details", async () => {
    await user.see("composer", { editable: true });
    await user.type("composer", MCP_PROMPT);
    await probe.eventually(() => probe.dom('button[aria-label="Run task"]:not(:disabled)'), {
      within: 30_000, label: "the conversation is ready to run the task", until: (value) => value.elements.length === 1,
    });
    await user.click("Run task");
    await user.see({ text: MCP_REPLY }, { timeoutMs: 90_000 });
    await user.see({ text: new RegExp(APP_CONNECTION_ERROR) }, { timeoutMs: 30_000 });
    const calls = await world.toolCalls();
    expect(calls.filter(call => call.name === "read_report")).toHaveLength(1);
    expect(calls.filter(call => call.name === "update_note")).toHaveLength(1);
    await world.offline(true);
    await user.notSee({ text: "You’re offline" });
    evidence.recordAssertionEvidence("The view fails separately from one completed read and one timed-out tool call", JSON.stringify(calls.map(call => call.name)), calls.length === 2);
    await user.screenshot();
  });

  await step("after: the enabled feature keeps the App failure compact and its diagnostics collapsed", async () => {
    await world.enable();
    await user.see({ text: "View unavailable" }, { timeoutMs: 30_000 });
    await user.see("Retry view");
    await user.notSee({ text: new RegExp(APP_CONNECTION_ERROR) });
    await user.see({ text: MCP_REPLY });
    await user.notSee({ text: "Change model" });
    const counts = await world.counts();
    expect(counts.views).toBeGreaterThan(0);
    expect(counts.probes).toBe(0);
    evidence.recordAssertionEvidence("A failed App view leaves the completed work and actual tool outcome intact", JSON.stringify(counts), counts.views > 0 && counts.probes === 0);
    await user.screenshot();
  });

  await step("retrying the view never repeats the tool call or restarts the task", async () => {
    const before = await world.counts();
    await user.click("Retry view");
    await probe.eventually(() => world.counts(), {
      within: 15_000, label: "the view is read again", until: (value) => value.views > before.views,
    });
    await user.see("Retry view", { timeoutMs: 30_000 });
    const after = await world.counts();
    const calls = await world.toolCalls();
    expect(calls).toHaveLength(2);
    expect(after.prompts).toBe(0);
    expect(after.stops).toBe(0);
    evidence.recordAssertionEvidence("Retry view reloads only the view, with no tool replay, new prompt, or task stop", JSON.stringify({ before, after, tools: calls.map(call => call.name) }), calls.length === 2 && after.prompts === 0 && after.stops === 0);
    await user.screenshot();
  });

  await step("offline is one bottom-right network hint that preserves the draft", async () => {
    await world.offline(true);
    await user.see({ text: "You’re offline" });
    await user.type("composer", "Check whether the client note was saved.");
    await user.see("composer", { text: "Check whether the client note was saved." });
    const notices = await probe.dom('[data-testid="offline-recovery"]');
    expect(notices.elements).toHaveLength(1);
    const notice = notices.elements[0];
    const send = (await probe.dom('button[aria-label="Run task"]')).elements[0];
    if (!notice || !send) throw new Error("The offline indicator and Send must both remain visible");
    expect(notice.rect.right).toBeLessThanOrEqual(notices.viewportWidth);
    expect(notice.rect.bottom).toBeLessThanOrEqual(send.rect.top);
    expect(notice.rect.top).toBeGreaterThan(send.rect.top / 2);
    if (notices.viewportWidth >= 768) expect(notice.rect.left).toBeGreaterThan(notices.viewportWidth / 2);
    evidence.recordAssertionEvidence("The offline indicator is discreet and leaves the draft and Send clear", JSON.stringify({ notice: notice.rect, send: send.rect }), notices.elements.length === 1);
    await user.screenshot();
  });

  await step("reconnecting only clears the indicator and leaves recovery with OpenCode v2", async () => {
    const before = await world.counts();
    await world.offline(false);
    await user.notSee({ text: "You’re offline" });
    await user.notSee({ text: "Connection restored" });
    await user.see("composer", { text: "Check whether the client note was saved." });
    const after = await world.counts();
    const calls = await world.toolCalls();
    expect(after.prompts).toBe(before.prompts);
    expect(after.stops).toBe(0);
    expect(after.probes).toBe(0);
    expect(calls).toHaveLength(2);
    evidence.recordAssertionEvidence("Network recovery does not replay the timed-out action", JSON.stringify({ before, after, tools: calls.length }), after.prompts === before.prompts && after.stops === 0 && calls.length === 2);
    await user.screenshot();
  });
});
