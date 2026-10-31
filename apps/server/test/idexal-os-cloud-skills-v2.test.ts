import { afterEach, describe, expect, test } from "bun:test";
import {
  discoverySkills,
  mentionedSkillIds,
  nativeSkills,
  registerCloudSkillDiscovery,
  sha256Digest,
  type CloudSkillContext,
} from "../src/opencode-plugins/idexal-os-cloud-skills-v2.js";

const longDescription = `Sync the CRM with the latest pipeline updates. ${"Triggers when the user mentions: update the CRM, sync the CRM, refresh the pipeline. ".repeat(4)}`;
const crmBody = "---\nname: crm-pipeline-sync-7y86nr3j\n---\n\nRead Gmail, then update Notion.";
const catalog = {
  delivery: "native" as const,
  skills: [
    { name: "crm-pipeline-sync-7y86nr3j", title: "crm-pipeline-sync", description: longDescription, url: "skill://crm-pipeline-sync-7y86nr3j/SKILL.md",
      capability: "plugin:plg_1:cob_1", modelDiscovery: true, digest: sha256Digest(crmBody) },
    { name: "salesforce-notes-ab12cd34", title: "Salesforce notes", description: "Log call notes.", url: "skill://salesforce-notes-ab12cd34/SKILL.md",
      capability: "plugin:plg_2:cob_2", modelDiscovery: true, setup: { status: "needs_connection", message: "Connect Salesforce from Your Connections." } },
    { name: "release", title: "Release", description: "Cut a release.", url: "skill://release/SKILL.md",
      capability: "skill:release", modelDiscovery: true, digest: sha256Digest("tampered") },
  ],
};

describe("discovery mode", () => {
  test("keeps metadata-only stubs with 200-character descriptions", () => {
    const [crm] = discoverySkills({ skills: catalog.skills });
    expect(crm?.id).toBe("idexal-os-cloud-crm-pipeline-sync-7y86nr3j");
    expect(crm?.description.length).toBe(200);
    expect(crm?.content).toContain("discovery metadata, NOT the skill's instructions");
  });
});

describe("native mode", () => {
  test("lists full descriptions and loads verified bodies, setup notes, or the discovery note", () => {
    const skills = nativeSkills(catalog, new Map([[sha256Digest(crmBody), crmBody]]));
    const byId = new Map(skills.map(skill => [skill.id, skill]));
    const crm = byId.get("idexal-os-cloud-crm-pipeline-sync-7y86nr3j");
    expect(crm?.description).toBe(longDescription.replace(/\s+/g, " ").trim());
    expect(crm?.content).toBe(crmBody);
    expect(byId.get("idexal-os-cloud-salesforce-notes-ab12cd34")?.content).toContain("Connect Salesforce from Your Connections.");
    expect(byId.get("idexal-os-cloud-release")?.content).toContain("discovery metadata");
  });

  test("a message that names a multi-word skill loads it; everyday single words do not", () => {
    const skills = nativeSkills(catalog, new Map());
    expect(mentionedSkillIds("please run crm-pipeline-sync for this week", skills)).toEqual(["idexal-os-cloud-crm-pipeline-sync-7y86nr3j"]);
    expect(mentionedSkillIds("Add these Salesforce notes to the account", skills)).toEqual(["idexal-os-cloud-salesforce-notes-ab12cd34"]);
    expect(mentionedSkillIds("we should release on friday", skills)).toEqual([]);
    expect(mentionedSkillIds("/release v1.2", skills)).toEqual(["idexal-os-cloud-release"]);
    expect(mentionedSkillIds("the crm-pipeline-syncing job", skills)).toEqual([]);
  });
});

describe("registerCloudSkillDiscovery", () => {
  let stop: (() => Promise<void>) | undefined;
  let server: ReturnType<typeof Bun.serve> | undefined;
  afterEach(async () => { await stop?.(); server?.stop(true); });

  test("native delivery registers bodies once per digest and attaches skills the prompt names", async () => {
    const reads: string[] = [];
    server = Bun.serve({ port: 0, async fetch(request) {
      const body: { name: string; input: { url?: string } } = await request.json();
      if (body.name === "idexal_os_skills") return Response.json(catalog);
      reads.push(body.input.url ?? "");
      return Response.json({ ok: true, content: body.input.url?.startsWith("skill://crm") ? crmBody : "tampered body" });
    } });
    const registered: Array<{ id: string; content: string }> = [];
    let promptHook: ((event: { prompt: { text: string; skills?: Array<{ id: string }> } }) => void) | undefined;
    const context: CloudSkillContext = {
      options: { url: `http://127.0.0.1:${server.port}/read`, token: "t" },
      skill: {
        async transform(callback) {
          callback({ get: () => undefined, add: skill => registered.push(skill) });
          return { dispose: async () => {} };
        },
        async reload() {},
      },
      session: { async hook(_name, callback) { promptHook = callback; return { dispose: async () => {} }; } },
    };
    stop = await registerCloudSkillDiscovery(context);
    for (let attempt = 0; attempt < 50 && registered.length === 0; attempt++) await Bun.sleep(20);

    expect(registered.find(skill => skill.id === "idexal-os-cloud-crm-pipeline-sync-7y86nr3j")?.content).toBe(crmBody);
    expect(registered.find(skill => skill.id === "idexal-os-cloud-release")?.content).toContain("discovery metadata");
    // Withheld skills are never read; mismatched bodies are read but not used.
    expect(reads.sort()).toEqual(["skill://crm-pipeline-sync-7y86nr3j/SKILL.md", "skill://release/SKILL.md"]);

    const event = { prompt: { text: "run crm-pipeline-sync now", skills: [] } };
    promptHook?.(event);
    expect(event.prompt.skills).toEqual([{ id: "idexal-os-cloud-crm-pipeline-sync-7y86nr3j" }]);
  });
});
