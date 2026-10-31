import { expect } from "vitest";
import { spec } from "@idexal/testkit";
import type { BrowserTaskInput } from "@idexal/testkit";
import { browserWithoutPermissionPrompts } from "../worlds/browser-without-permission-prompts.ts";

const test = spec.world(browserWithoutPermissionPrompts, {
  timeout: 420_000,
  resources: {
    surfaces: ["desktop"], services: [],
    nativeReason: "Browser tasks use Electron's native page view and the Take over and Resume browser controls.",
  },
});

test("a conversation uses page controls and website tools without prompts while other conversations stay private", async ({ world, user, agent, probe, step, evidence }) => {
  const sessionId = world.session.sessionId;
  const task = (operation: BrowserTaskInput["operation"], args: BrowserTaskInput["args"] = {}) => agent.browserTask({ sessionId, operation, args });
  const witness = () => probe.browserFixtureState(world.origin);
  const prompts = /Allow this agent to use the browser\?|Allow for this session|Allow browser control for this thread\?|Allow browser action\?|Allow website action\?|Allow once|Share result/;

  await step("before: the conversation has no browser page open", async () => {
    await user.click({ text: world.session.title });
    await user.see("composer", { editable: true });
    const state = await probe.browserState();
    expect(state.tabs).toHaveLength(0);
    expect((await witness()).pageRequests).toHaveLength(0);
    evidence.recordAssertionEvidence("No page was opened during setup", `${state.tabs.length} browser tabs`, state.tabs.length === 0);
    await user.screenshot();
  });

  const tabId = await step("after: the requested page opens and lists website tools without a permission prompt", async () => {
    const opened = await task("open", { url: world.origin });
    expect(opened.ok).toBe(true);
    if (!opened.tabId) throw new Error("The browser opened no tab.");
    await user.see({ role: "button", label: "Take over" }, { timeoutMs: 30_000 });
    const state = await probe.browserState();
    expect(state.tabs.find(tab => tab.id === opened.tabId)?.ownerSessionId).toBe(sessionId);
    const requested = await probe.eventually(witness, {
      within: 10_000, label: "the site receives the first navigation",
      until: value => value.pageRequests.some(request => request.path === "/"),
    });
    const listed = await task("site_tools", { tabId: opened.tabId });
    expect(listed.ok).toBe(true);
    expect(listed.tools?.map(tool => tool.name)).toContain("save_draft");
    expect(await probe.text()).not.toMatch(prompts);
    evidence.recordAssertionEvidence("The first browser request reaches the site directly", `${requested.pageRequests.length} page request; website tools available: ${listed.tools?.length ?? 0}`, requested.pageRequests.length === 1 && listed.ok);
    await user.screenshot();
    return opened.tabId;
  });

  await step("page controls fill, type and save without a separate confirmation", async () => {
    const observed = await task("observe", { tabId });
    const inputRef = observed.elements?.find(element => element.name === "Draft title")?.ref;
    if (!inputRef) throw new Error("The page has no observed draft field.");
    expect(await task("act", { tabId, observationId: observed.observationId, action: { type: "fill", ref: inputRef, text: "Draft" } }))
      .toMatchObject({ ok: true, dispatched: true });
    await probe.eventually(witness, { within: 5_000, label: "the field receives the fill", until: value => value.inputValue === "Draft" });
    const filled = await task("observe", { tabId });
    expect(await task("act", { tabId, observationId: filled.observationId, action: { type: "key", key: "Backspace" } }))
      .toMatchObject({ ok: true, dispatched: true });
    await probe.eventually(witness, { within: 5_000, label: "the key reaches the field", until: value => value.inputValue === "Draf" });
    const fresh = await task("observe", { tabId });
    const saveRef = fresh.elements?.find(element => element.name === "Save draft")?.ref;
    if (!saveRef) throw new Error("The page has no observed save button.");
    expect(await task("act", { tabId, observationId: fresh.observationId, action: { type: "click", ref: saveRef } }))
      .toMatchObject({ ok: true, dispatched: true });
    const saved = await probe.eventually(witness, { within: 5_000, label: "the site records the click", until: value => value.records.length === 1 });
    expect(saved.records).toEqual([{ method: "dom", count: 1, signedIn: false }]);
    expect((await task("observe", { tabId })).text).toContain("Saved 1");
    expect(await probe.text()).not.toMatch(prompts);
    await user.see({ role: "button", label: "Take over" });
    evidence.recordAssertionEvidence("The page receives the fill, key and click", `Field: ${saved.inputValue}; recorded saves: ${saved.records.length}`, saved.inputValue === "Draf" && saved.records.length === 1);
    await user.screenshot();
  });

  const toolId = await step("a website tool runs once and returns its result without a sharing prompt", async () => {
    const listed = await task("site_tools", { tabId });
    const save = listed.tools?.find(tool => tool.name === "save_draft");
    if (!save) throw new Error("The website has no save tool.");
    const result = await task("site_tool", { tabId, toolId: save.toolId, input: { confirm: true } });
    expect(result).toMatchObject({ ok: true, dispatched: true, result: { saved: 2, signedIn: false } });
    const saved = await probe.eventually(witness, { within: 5_000, label: "the site records the website tool", until: value => value.records.length === 2 });
    expect(saved.records).toEqual([{ method: "dom", count: 1, signedIn: false }, { method: "webmcp", count: 2, signedIn: false }]);
    const observed = await task("observe", { tabId });
    expect(observed.text).toContain("Saved 2");
    expect(await probe.text()).not.toMatch(prompts);
    await user.see({ role: "button", label: "Take over" });
    evidence.recordAssertionEvidence("The callback result and the page agree", `Recorded saves: ${saved.records.length}; page reports Saved 2`, saved.records.length === 2 && observed.text?.includes("Saved 2") === true);
    await user.screenshot();
    return save.toolId;
  });

  await step("a different conversation cannot read the page or reuse its website tool", async () => {
    const before = await witness();
    const foreign = (operation: BrowserTaskInput["operation"], args: BrowserTaskInput["args"]) => agent.browserTask({ sessionId: world.neighbor.sessionId, operation, args });
    const refused = await foreign("observe", { tabId });
    expect(refused).toMatchObject({ ok: false, code: "wrong_conversation" });
    expect(await foreign("site_tool", { tabId, toolId, input: { confirm: true } }))
      .toMatchObject({ ok: false, code: "wrong_conversation" });
    expect((await witness()).records).toEqual(before.records);
    await user.see({ role: "button", label: "Take over" });
    evidence.recordAssertionEvidence("Removing prompts preserves conversation ownership", `Other conversation: ${refused.code}; recorded saves: ${before.records.length}`, refused.code === "wrong_conversation" && before.records.length === 2);
    await user.screenshot();
  });

  await step("Take over pauses browser tasks and Resume browser restores access without another permission prompt", async () => {
    await user.click({ role: "button", label: "Take over" });
    const paused = await task("observe", { tabId });
    expect(paused).toMatchObject({ ok: false, code: "paused" });
    expect(await task("site_tool", { tabId, toolId, input: { confirm: true } }))
      .toMatchObject({ ok: false, code: "paused" });
    expect((await witness()).records).toHaveLength(2);
    await user.click({ role: "button", label: "Resume browser" });
    const resumed = await task("observe", { tabId });
    expect(resumed).toMatchObject({ ok: true, tabId, text: expect.stringContaining("Saved 2") });
    expect(await probe.text()).not.toMatch(prompts);
    evidence.recordAssertionEvidence("Human takeover remains effective", `Paused: ${paused.code}; resumed: ${resumed.ok}; recorded saves: 2`, paused.code === "paused" && resumed.ok);
    await user.screenshot();
  });
});
