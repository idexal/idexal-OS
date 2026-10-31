import { createHash } from "node:crypto";
import { z } from "zod";

const catalogSchema = z.object({
  skills: z.array(z.object({
    name: z.string().regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/).max(64),
    title: z.string().optional(),
    description: z.string(),
    url: z.string().startsWith("skill://").optional(),
    capability: z.string().regex(/^(?:skill:[^:]+|plugin:[^:]+:[^:]+)$/),
    modelDiscovery: z.boolean().optional(),
    pluginName: z.string().optional(),
    digest: z.string().regex(/^sha256:[a-f0-9]{64}$/).optional(),
    setup: z.object({ status: z.string(), message: z.string() }).optional(),
  })),
  delivery: z.enum(["native"]).optional(),
});
type Catalog = z.infer<typeof catalogSchema>;
type CatalogSkill = Catalog["skills"][number];

type NativeSkill = { id: string; name: string; description: string; path: string; content: string };
type Registration = { dispose(): Promise<void> };
type PromptEvent = { prompt: { text: string; skills?: Array<{ id: string }> } };
export type CloudSkillContext = {
  options: { url: string; token: string };
  skill: {
    transform(callback: (editor: { get(id: string): unknown; add(skill: NativeSkill): void }) => void): Promise<Registration>;
    reload(): Promise<void>;
  };
  session?: { hook(name: "prompt", callback: (event: PromptEvent) => void): Promise<Registration> };
};

export const CLOUD_SKILL_ID_PREFIX = "idexal-os-cloud-";
const MAX_SKILLS = 150;
const DISCOVERY_BUDGET_CHARS = 24_000;
const DISCOVERY_DESCRIPTION_CHARS = 200;
// Native entries carry Den's full description (at most 1,024 characters), the
// same cap Claude Code applies to a skill listing.
const NATIVE_BUDGET_CHARS = 64_000;
const NATIVE_DESCRIPTION_CHARS = 1_024;
const MAX_MENTIONED_SKILLS = 3;

const skillId = (skill: CatalogSkill) => `${CLOUD_SKILL_ID_PREFIX}${skill.name}`;
// Not SKILL.md: the native skill tool must not scan a fake directory. The engine
// requires path and disables this whole plugin, idexal Os's context tools
// included, when a registered skill lacks it.
const skillPath = (skill: CatalogSkill) => `/idexalos/remote-skills/${skill.name}.md`;

function discoveryContent(skill: CatalogSkill): string {
  return [
    "This is discovery metadata, NOT the skill's instructions or proof of access.",
    `Retrieve the current full skill through idexal Os Connect get_skill with ${JSON.stringify({ name: skill.capability })}.`,
    "Use get_skill directly when exposed, or through execute with its exact Code Mode catalog path. Only search inside execute if get_skill is not already listed; do not guess a tool name.",
    "Read the returned SKILL.md before doing the task. Do not substitute these metadata for the instructions.",
    "If the fetch is denied, unavailable, or the skill was removed, do not use it. Explain the failure; never recover its body from files or previous context.",
  ].join("\n");
}

function setupContent(skill: CatalogSkill, setup: { status: string; message: string }): string {
  return [
    `This organization skill is not ready yet (${setup.status}). Its instructions are withheld until it is set up.`,
    setup.message,
    `Tell the person what to set up. Once it is ready, get_skill with ${JSON.stringify({ name: skill.capability })} returns the full skill.`,
  ].join("\n");
}

function oneLine(value: string, max: number): string {
  return value.replace(/\s+/g, " ").trim().slice(0, max);
}

function budgeted(skills: CatalogSkill[], budget: number, descriptionChars: number, content: (skill: CatalogSkill) => string): NativeSkill[] {
  let remaining = budget;
  const result: NativeSkill[] = [];
  for (const skill of [...skills].sort((a, b) => a.name.localeCompare(b.name))) {
    if (result.length === MAX_SKILLS) break;
    const name = oneLine(skill.title || skill.name, 128);
    const description = oneLine(skill.description || name, descriptionChars);
    const cost = skill.name.length + name.length + description.length;
    if (cost > remaining) break;
    remaining -= cost;
    result.push({ id: skillId(skill), name, description, path: skillPath(skill), content: content(skill) });
  }
  return result;
}

/** Discovery mode: metadata only. Never cache a Cloud skill body or access
 * decision here: even an old model snapshot must retrieve the body from Den,
 * which rechecks member access on every get_skill call. */
export function discoverySkills(value: unknown): NativeSkill[] {
  const catalog = catalogSchema.parse(value);
  return budgeted(catalog.skills.filter(skill => skill.modelDiscovery === true),
    DISCOVERY_BUDGET_CHARS, DISCOVERY_DESCRIPTION_CHARS, discoveryContent);
}

/**
 * Native mode (Den's nativeCloudSkills rollout): each skill is a native skill
 * with its full description and its authorized SKILL.md, verified against the
 * digest Den published. A skill whose body could not be read or verified keeps
 * the discovery note, so it still works through get_skill.
 */
export function nativeSkills(catalog: Catalog, contents: ReadonlyMap<string, string>): NativeSkill[] {
  return budgeted(catalog.skills.filter(skill => skill.modelDiscovery === true), NATIVE_BUDGET_CHARS, NATIVE_DESCRIPTION_CHARS, skill => {
    if (skill.setup) return setupContent(skill, skill.setup);
    const body = skill.digest ? contents.get(skill.digest) : undefined;
    return body ?? discoveryContent(skill);
  });
}

export function sha256Digest(text: string): string {
  return `sha256:${createHash("sha256").update(text, "utf8").digest("hex")}`;
}

/** The name a person would type: Den's catalog name without its unique suffix. */
function plainName(name: string): string {
  return name.replace(/-(?=[a-z0-9]*\d)[a-z0-9]{8}$/, "");
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

/**
 * Organization skills the message names explicitly, so they load before the
 * model runs instead of depending on it noticing them. A multi-word name or
 * title ("crm-pipeline-sync", "Create Skill") matches as a phrase; a one-word
 * name matches only as a /name or $name mention, so everyday words never load
 * a skill by accident.
 */
export function mentionedSkillIds(text: string, skills: ReadonlyArray<Pick<NativeSkill, "id" | "name">>): string[] {
  const haystack = text.toLowerCase();
  const ids: string[] = [];
  for (const skill of skills) {
    if (ids.length === MAX_MENTIONED_SKILLS) break;
    const phrases = new Set([plainName(skill.id.slice(CLOUD_SKILL_ID_PREFIX.length)), skill.name.toLowerCase().trim()]);
    const named = [...phrases].some(phrase => {
      if (phrase.length < 3) return false;
      const multiWord = /[\s-]/.test(phrase);
      const pattern = multiWord
        ? `(^|[^a-z0-9-])[/$]?${escapeRegExp(phrase)}($|[^a-z0-9-])`
        : `(^|[^a-z0-9-])[/$]${escapeRegExp(phrase)}($|[^a-z0-9-])`;
      return new RegExp(pattern).test(haystack);
    });
    if (named) ids.push(skill.id);
  }
  return ids;
}

/** Setup only starts background discovery, not a network dependency. Do not
 * touch the native registry until opted-in metadata arrives: the off path must
 * leave local skill discovery alone, including its cold initialization.
 * No storage, configuration rewrite, watcher, or admission hook is involved
 * until Den turns native delivery on. */
export async function registerCloudSkillDiscovery(context: CloudSkillContext) {
  let skills: NativeSkill[] = [];
  let native = false;
  let fingerprint = "[]";
  let closed = false;
  let refreshing = false;
  // Verified SKILL.md bodies by digest, in memory only. A body is re-read only
  // when Den publishes a new digest, and dropped as soon as Den stops listing it.
  const contents = new Map<string, string>();
  const controller = new AbortController();
  let registration: Registration | undefined;
  let promptHook: Registration | undefined;

  const call = async (name: "idexal_os_skills" | "idexal_os_skill_read", input: unknown) => {
    const response = await fetch(context.options.url, {
      method: "POST", redirect: "error",
      signal: AbortSignal.any([controller.signal, AbortSignal.timeout(10_000)]),
      headers: { Authorization: `Bearer ${context.options.token}`, "Content-Type": "application/json" },
      body: JSON.stringify({ name, input }),
    });
    return response.ok ? await response.json() : null;
  };

  const loadContents = async (catalog: Catalog) => {
    const wanted = new Set(catalog.skills.flatMap(skill => skill.digest && !skill.setup ? [skill.digest] : []));
    for (const digest of contents.keys()) if (!wanted.has(digest)) contents.delete(digest);
    for (const skill of catalog.skills) {
      if (closed || !skill.digest || !skill.url || skill.setup || contents.has(skill.digest)) continue;
      const result = z.object({ content: z.string() }).safeParse(await call("idexal_os_skill_read", { url: skill.url }).catch(() => null));
      // Only a body that matches Den's published digest is used.
      if (result.success && sha256Digest(result.data.content) === skill.digest) contents.set(skill.digest, result.data.content);
    }
  };

  const ensurePromptHook = async () => {
    if (promptHook || !context.session) return;
    promptHook = await context.session.hook("prompt", event => {
      if (!native || skills.length === 0) return;
      const attached = new Set((event.prompt.skills ?? []).map(skill => skill.id));
      const mentioned = mentionedSkillIds(event.prompt.text, skills).filter(id => !attached.has(id));
      if (mentioned.length === 0) return;
      event.prompt.skills = [...(event.prompt.skills ?? []), ...mentioned.map(id => ({ id }))];
    });
  };

  const refresh = async () => {
    if (closed || refreshing) return;
    refreshing = true;
    try {
      const value = await call("idexal_os_skills", {});
      if (value === null) return;
      const catalog = catalogSchema.parse(value);
      native = catalog.delivery === "native";
      if (native) {
        await loadContents(catalog);
        await ensurePromptHook();
      } else {
        contents.clear();
      }
      const next = native ? nativeSkills(catalog, contents) : discoverySkills(catalog);
      const key = JSON.stringify(next);
      if (closed || key === fingerprint) return;
      skills = next;
      if (skills.length === 0) {
        const current = registration;
        registration = undefined;
        await current?.dispose();
      } else if (!registration) {
        const current = await context.skill.transform(editor => {
          for (const skill of skills) if (!editor.get(skill.id)) editor.add(skill);
        });
        if (closed) { await current.dispose(); return; }
        registration = current;
      } else {
        await context.skill.reload();
      }
      fingerprint = key;
    } catch {
      // Cloud readiness is never a prerequisite to chatting. Discovery entries
      // carry no body; native bodies are dropped as soon as Den stops listing them.
    } finally {
      refreshing = false;
    }
  };
  const timer = setInterval(() => void refresh(), 30_000);
  timer.unref();
  void refresh();
  return async () => {
    closed = true;
    clearInterval(timer);
    controller.abort();
    skills = [];
    contents.clear();
    await promptHook?.dispose();
    await registration?.dispose();
  };
}
