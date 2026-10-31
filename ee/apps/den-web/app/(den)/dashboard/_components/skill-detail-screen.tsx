"use client";

import { useRouter } from "next/navigation";
import { getEditPluginSkillRoute, getPluginRoute } from "../../_lib/den-org";
import { useOrgDashboard } from "../_providers/org-dashboard-provider";
import { useDeleteSkill, useSkill } from "./skill-data";
import { ItemHeader, ItemPage, SectionTitle } from "./item-header";
import { DetailRows, ItemMenu, ItemPanel, ItemRowsSkeleton, removeEntry } from "./item-list";
import { KindTile } from "./item-logo";
import { skillTitle } from "./plugin-page-screen";

export function SkillDetailScreen({ pluginId, skillId }: { pluginId: string; skillId: string }) {
  const router = useRouter();
  const { orgSlug } = useOrgDashboard();
  const { data: skill, isLoading, error } = useSkill(pluginId, skillId);
  const deleteSkill = useDeleteSkill(pluginId);

  if (isLoading && !skill) {
    return <ItemPage><ItemPanel><ItemRowsSkeleton label="Loading skill" /></ItemPanel></ItemPage>;
  }

  if (!skill) {
    return <DetailMessage error>{error instanceof Error ? error.message : "That skill could not be found."}</DetailMessage>;
  }

  return (
    <ItemPage testId="skill-detail">
      <ItemHeader compact back={{ href: getPluginRoute(orgSlug, pluginId), label: "Back to plugin" }} logo={<KindTile kind="skill" />} title={skillTitle(skill.name)} actions={<ItemMenu size="md" label={`More for ${skillTitle(skill.name)}`} entries={[
        { label: "Edit", href: getEditPluginSkillRoute(orgSlug, pluginId, skill.id) },
        removeEntry(skillTitle(skill.name), async () => {
          await deleteSkill.mutateAsync(skill.id);
          router.push(getPluginRoute(orgSlug, pluginId));
        }),
      ]} />} />
      {skill.description ? <DetailRows rows={[{ label: "When to use it", value: skill.description, wrap: true }]} /> : null}
      <section className="flex flex-col gap-3">
        <SectionTitle title="Skill steps" />
        <pre className="whitespace-pre-wrap break-words text-[13px] leading-6 text-[var(--dls-text-primary)]">{skill.body}</pre>
      </section>
    </ItemPage>
  );
}

function DetailMessage({ children, error = false }: { children: React.ReactNode; error?: boolean }) {
  return (
    <div className="mx-auto max-w-[900px] px-6 py-8 md:px-8">
      <div className={`rounded-2xl border px-5 py-8 text-[14px] ${error ? "border-red-200 bg-red-50 text-red-700" : "border-gray-100 bg-white text-gray-500"}`}>
        {children}
      </div>
    </div>
  );
}
