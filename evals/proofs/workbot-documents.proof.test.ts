import { expect } from "vitest";
import { spec } from "@idexal/testkit";
import { workbotWorkspace } from "../worlds/workbot-workspace.ts";

const test = spec.world(workbotWorkspace, { timeout: 120_000, needs: { placement: "local" }, resources: { surfaces: [], services: [] } });

test("a member opens and edits a Drive document while unavailable access stays blocked", async ({ world, user, probe, step, evidence }) => {
  await user.resizeViewport({ width: 1440, height: 1000, deviceScaleFactor: 1 });
  await step("before: Documents is locked while the feature is off", async () => {
    await user.navigate(`${world.url}documents?documents=off&assistant=off`);
    await user.see({ text: "Documents is off for your workspace. An admin can turn it on." });
    await user.notSee({ role: "textbox", label: "Document text" });
    await user.notSee({ role: "button", label: /^Documents/ });
    evidence.recordAssertionEvidence("The feature starts off", "The locked state contains no document editor", (await probe.dom(".ow-editor-plain")).elements.length === 0);
    await user.screenshot();
    await user.navigate(`${world.url}calendar?documents=off`);
    await user.click({ role: "button", label: "Open workspace navigation" });
    await user.notSee({ role: "button", label: /^Documents/ });
    await user.notSee({ text: "Recent documents" });
    await user.screenshot();
  });
  await step("after: a connected member opens a recent Drive document in an editable tab", async () => {
    await user.navigate(`${world.url}documents?assistant=off`);
    await user.click({ role: "button", label: "Project brief.txt" });
    await user.see({ role: "textbox", label: "Document text" }, { value: "Project overview" });
    await user.type({ role: "textbox", label: "Document text" }, "Revised project overview", { replace: true });
    await user.see({ text: "Unsaved changes" });
    await user.see({ role: "textbox", label: "Document text" }, { value: "Revised project overview" });
    expect(world.messages()).toHaveLength(0);
    evidence.recordAssertionEvidence("Drive browsing and editing remain separate from chat", "The file opens as a tab and marks local changes without sending a message", world.messages().length === 0);
    await user.screenshot();
  });
  await step("turning the feature off masks a draft and turning it back on restores it", async () => {
    world.disableDocuments();
    await user.click({ role: "button", label: /^Files$/ });
    await user.click({ role: "button", label: "Refresh documents" });
    await user.see({ text: "Documents is off for your workspace. An admin can turn it on." });
    await user.notSee({ role: "textbox", label: "Document text" });
    world.enableDocuments();
    await user.click({ role: "button", label: "Try again" });
    await user.click({ role: "button", label: "Project brief.txt", nth: 0 });
    await user.see({ role: "textbox", label: "Document text" }, { value: "Revised project overview" });
    evidence.recordAssertionEvidence("The feature switch preserves a local draft", "The edited document survives a feature kill and restore", (await probe.dom(".ow-editor-plain")).elements.length === 1);
    await user.screenshot();
  });
  await step("revoked Drive access closes every cached document", async () => {
    world.revokeDrive();
    await user.click({ role: "button", label: /^Files$/ });
    await user.click({ role: "button", label: "Refresh documents" });
    await user.see({ text: "Connect Google Drive to browse and open your documents. Drive read permission is required." });
    await user.notSee({ role: "textbox", label: "Document text" });
    await user.see({ role: "link", label: "Connect Google Drive" });
    await user.notSee({ text: "Project brief.txt" });
    await user.notSee({ text: "Budget notes.txt" });
    await user.notSee({ text: "Revised project overview" });
    expect((await probe.dom(".ow-document-editor")).elements).toHaveLength(0);
    evidence.recordAssertionEvidence("Revocation clears open documents", "No document editor or cached filename remains visible", (await probe.dom(".ow-document-editor")).elements.length === 0);
    await user.screenshot();
  });
  await step("restored Drive access opens the saved version without retained editor data", async () => {
    world.restoreDrive();
    await user.click({ role: "button", label: "Try again" });
    await user.click({ role: "button", label: "Project brief.txt" });
    await user.see({ role: "textbox", label: "Document text" }, { value: "Project overview" });
    evidence.recordAssertionEvidence("Restored access fetches the saved document again", "The saved version replaces the discarded revoked session", (await probe.dom(".ow-editor-plain")).elements.length === 1);
    await user.screenshot();
  });
});
