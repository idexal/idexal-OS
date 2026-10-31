/** @jsxImportSource react */
import { t } from "../../../../i18n";
import { Button } from "@/components/ui/button";
import type { SuggestedSkill } from "../../../../app/skill-catalog";

export type SuggestedSkillsViewProps = {
  skills: SuggestedSkill[];
  installedNames: string[];
  busy: boolean;
  canInstall: boolean;
  hasWorkspace: boolean;
  status?: string | null;
  onInstall: (skill: SuggestedSkill) => void | Promise<void>;
};

export function SuggestedSkillsView(props: SuggestedSkillsViewProps) {
  return (
    <section className="w-full max-w-3xl">
      <div className="rounded-2xl border border-dls-border bg-dls-surface p-5">
        <div className="text-sm font-medium text-dls-text">{t("skills.catalog_heading")}</div>
        <div className="mt-1 text-xs text-dls-secondary">{t("skills.catalog_note")}</div>

        <div className="mt-4 grid gap-3">
          {props.skills.map((skill) => {
            const installed = props.installedNames.includes(skill.name);
            return (
              <div key={skill.name} className="rounded-xl border border-dls-border bg-dls-surface p-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="min-w-0">
                    <div className="font-mono text-sm font-medium text-dls-text">{skill.name}</div>
                    <div className="mt-1 text-xs text-dls-secondary">{skill.description}</div>
                    <div className="mt-2 flex flex-wrap gap-2">
                      {skill.tags.map((tag) => (
                        <span key={tag} className="rounded-full bg-dls-hover px-2 py-0.5 text-[10px] uppercase tracking-wide text-dls-secondary">
                          {tag}
                        </span>
                      ))}
                    </div>
                  </div>
                  <Button
                    variant={installed ? "outline" : "default"}
                    size="xs"
                    disabled={installed || props.busy || !props.canInstall || !props.hasWorkspace}
                    onClick={() => void props.onInstall(skill)}
                  >
                    {installed ? t("skills.installed_status") : t("skills.install")}
                  </Button>
                </div>
              </div>
            );
          })}
        </div>

        {!props.hasWorkspace ? (
          <div className="mt-4 text-xs text-dls-secondary">{t("skills.catalog_workspace_required")}</div>
        ) : null}
        {props.status ? <div className="mt-4 text-xs text-dls-secondary">{props.status}</div> : null}
      </div>
    </section>
  );
}
