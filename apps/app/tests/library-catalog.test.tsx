import { expect, test } from "bun:test"
import { renderToStaticMarkup } from "react-dom/server"
import { MCP_QUICK_CONNECT, SUGGESTED_PLUGINS } from "../src/app/constants"
import { SUGGESTED_SKILLS } from "../src/app/skill-catalog"
import { ExtensionsView } from "../src/react-app/domains/settings/pages/extensions-view"
import type { PluginsExtensionsStore } from "../src/react-app/domains/settings/pages/plugins-view"
import { SuggestedSkillsView } from "../src/react-app/domains/settings/pages/suggested-skills-view"

test("every curated connector has a single installable transport", () => {
  const serverNames = new Set<string>()

  for (const entry of MCP_QUICK_CONNECT.filter((candidate) => candidate.kind === "mcp")) {
    const serverName = entry.serverName ?? entry.name
    expect(serverName.length).toBeGreaterThan(0)
    expect(serverNames.has(serverName)).toBe(false)
    serverNames.add(serverName)

    if (entry.type === "local") {
      expect(entry.command?.length ?? 0).toBeGreaterThan(0)
    } else {
      expect(entry.url).toMatch(/^https:\/\//)
    }
  }

  expect(serverNames.size).toBeGreaterThanOrEqual(15)
})

test("curated plugins are npm packages with copy and tags", () => {
  const packageNames = new Set<string>()

  for (const plugin of SUGGESTED_PLUGINS) {
    expect(plugin.name.trim().length).toBeGreaterThan(0)
    expect(plugin.description.trim().length).toBeGreaterThan(0)
    expect(plugin.tags.length).toBeGreaterThan(0)
    expect(plugin.packageName).toMatch(/^(@[a-z0-9-]+\/)?[a-z0-9._-]+$/i)
    expect(packageNames.has(plugin.packageName)).toBe(false)
    packageNames.add(plugin.packageName)
  }

  expect(packageNames.size).toBeGreaterThanOrEqual(6)
})

test("curated skills carry valid SKILL.md frontmatter and a unique name", () => {
  const names = new Set<string>()

  for (const skill of SUGGESTED_SKILLS) {
    expect(skill.description.trim().length).toBeGreaterThan(0)
    expect(skill.tags.length).toBeGreaterThan(0)
    expect(skill.content.startsWith("---\n")).toBe(true)
    expect(skill.content).toContain(`name: ${skill.name}`)
    expect(names.has(skill.name)).toBe(false)
    names.add(skill.name)
  }

  expect(names.size).toBeGreaterThanOrEqual(3)
})

test("the Skills tab renders the curated catalog with an install action", () => {
  const html = renderToStaticMarkup(
    <SuggestedSkillsView
      skills={SUGGESTED_SKILLS}
      installedNames={["code-review"]}
      busy={false}
      canInstall
      hasWorkspace
      status={null}
      onInstall={() => {}}
    />,
  )

  expect(html).toContain("Curated skills")
  expect(html).toContain("code-review")
  expect(html).toContain("regression-test")
  expect(html).toContain("Installed")
  expect(html).toContain("Install")
})

test("the Plugins tab shows the curated store before anything is installed", () => {
  const store: PluginsExtensionsStore = {
    pluginScope: "project",
    setPluginScope: () => {},
    refreshPlugins: () => {},
    pluginConfigPath: () => null,
    pluginConfig: () => null,
    pluginList: () => [],
    pluginInput: () => "",
    setPluginInput: () => {},
    pluginStatus: () => null,
    addPlugin: () => {},
    removePlugin: () => {},
    isPluginInstalledByName: () => false,
    activePluginGuide: () => null,
    setActivePluginGuide: () => {},
  }

  const html = renderToStaticMarkup(
    <ExtensionsView
      busy={false}
      selectedWorkspaceRoot="/tmp/workspace"
      canEditPlugins
      canUseGlobalScope
      suggestedPlugins={SUGGESTED_PLUGINS}
      skillsContent={<div>SKILL STORE MARKER</div>}
      extensions={store}
      mcpView={(routing) => <div>{routing.pluginsContent}{routing.skillsContent}</div>}
      onRefresh={() => {}}
      initialSection="plugin"
    />,
  )

  expect(html).toContain("idexal Os Plugins")
  expect(html).toContain("Suggested plugins")
  expect(html).toContain("Dynamic Context Pruning")
  expect(html).toContain("Plugins run locally with the same permissions")
  expect(html).toContain("SKILL STORE MARKER")
})
