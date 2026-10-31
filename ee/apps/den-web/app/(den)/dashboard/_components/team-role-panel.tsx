"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Check, ShieldCheck } from "lucide-react";
import { getPermissionDefinition, permissionDefaultKeys, type PermissionKey } from "@idexal/types/den/permissions";
import { DenButton } from "../../_components/ui/button";
import { DenNotice } from "../../_components/ui/notice";
import { DenOptionCard } from "../../_components/ui/option-card";
import { DenSkeleton } from "../../_components/ui/skeleton";
import { getOrgAccessFlags, getPermissionSetRoute, orgFeatureEnabled, type DenOrgTeam } from "../../_lib/den-org";
import { useOrgDashboard } from "../_providers/org-dashboard-provider";
import { IconTile } from "./access-rows";
import { useDenToast } from "./den-toast";
import { ItemPanel, ItemRow, ItemRowsSkeleton, ItemSection, LinkButton } from "./item-list";
import { useApplyTeamRole, usePermissionSet, usePermissionSets } from "./permissions-data";
import {
  EVERYONE_CAN,
  TEAM_ROLE_DESCRIPTIONS,
  TEAM_ROLE_LABELS,
  allowedKeys,
  teamRole,
  teamRoleLockReason,
  teamRoleSteps,
  type TeamRole,
  type TeamRoleAbilities,
  type TeamRoleState,
} from "./team-roles";

const ROLE_ORDER: readonly TeamRole[] = ["member", "admin", "custom"];
/** Long lists (Admin allows dozens of permissions) start short; the rest is one click away (DESIGN.md P3). */
const ALSO_PREVIEW = 6;

/**
 * A team's one role, what it adds, and what everyone can do. Change role opens
 * the choice in place, in the role's own panel, so the page never moves the
 * person somewhere else to decide (DESIGN.md P3, P11).
 */
export function TeamRolePanel({ team }: { team: DenOrgTeam }) {
  const { orgContext, orgId, orgSlug } = useOrgDashboard();
  const permissionsFeature = orgContext ? orgFeatureEnabled(orgContext, "permissions") : false;
  const access = orgContext
    ? getOrgAccessFlags(orgContext.currentMember.role, orgContext.currentMember.isOwner, orgContext.currentMember.permissions)
    : null;
  const setsQuery = usePermissionSets(orgId, permissionsFeature);
  const sets = setsQuery.data ?? [];
  const teamSet = sets.find((set) => set.kind === "team" && set.team?.id === team.id) ?? null;
  const adminSet = sets.find((set) => set.kind === "admin_default") ?? null;
  const memberSet = sets.find((set) => set.kind === "member_default") ?? null;
  const state: TeamRoleState = { admin: team.grantsOrganizationAdmin, customSetId: teamSet?.id ?? null };
  const role = teamRole(state);
  const [editing, setEditing] = useState(false);
  const [showAll, setShowAll] = useState(false);

  const teamSetDetail = usePermissionSet(orgId, teamSet?.id ?? "", Boolean(teamSet));
  const adminSetDetail = usePermissionSet(orgId, adminSet?.id ?? "", Boolean(adminSet));
  const memberSetDetail = usePermissionSet(orgId, memberSet?.id ?? "", Boolean(memberSet && memberSet.allowedCount > 0));

  // With Permissions off, Admin and Member follow the shipped defaults.
  const adminKeys: PermissionKey[] = adminSetDetail.data ? allowedKeys(adminSetDetail.data.permissions) : permissionDefaultKeys("admin");
  const memberKeys: PermissionKey[] = memberSetDetail.data ? allowedKeys(memberSetDetail.data.permissions) : permissionDefaultKeys("member");
  const customKeys: PermissionKey[] = teamSetDetail.data ? allowedKeys(teamSetDetail.data.permissions) : [];
  const alsoKeys = [...new Set([...(role === "admin" ? adminKeys : []), ...customKeys])].filter((key) => !memberKeys.includes(key));
  const shownAlso = showAll ? alsoKeys : alsoKeys.slice(0, ALSO_PREVIEW);

  const loading = permissionsFeature && (setsQuery.isPending || (teamSet !== null && teamSetDetail.isPending));
  const description = role === "admin"
    ? `Everyone in ${team.name} is an admin`
    : role === "custom"
      ? `${customKeys.length} ${customKeys.length === 1 ? "permission" : "permissions"} chosen for ${team.name}`
      : TEAM_ROLE_DESCRIPTIONS.member;
  const abilities: TeamRoleAbilities = {
    canManageAdminTeams: access?.canManageAdminTeams ?? false,
    canManagePermissions: access?.canManagePermissions ?? false,
    permissionsFeature,
  };

  return (
    <div className="flex flex-col gap-6" data-testid="team-role" data-role={role}>
      {setsQuery.isError ? <DenNotice tone="error" message={setsQuery.error instanceof Error ? setsQuery.error.message : "Couldn't load this team's role."} /> : null}
      <ItemPanel>
        <div className="flex items-center gap-3.5 px-5 py-4">
          <IconTile icon={ShieldCheck} />
          <span className="flex min-w-0 flex-1 flex-col gap-0.5">
            <span className="text-[14px] font-medium leading-5 text-gray-900" data-testid="team-role-name">{TEAM_ROLE_LABELS[role]}</span>
            {loading ? <DenSkeleton className="h-[18px] w-48" /> : <span className="text-[13px] leading-[18px] text-gray-500">{description}</span>}
          </span>
          {editing ? null : (
            <span className="flex shrink-0 gap-2">
              {teamSet ? <LinkButton size="sm" variant="secondary" href={getPermissionSetRoute(orgSlug, teamSet.id)} data-testid="team-role-edit">Edit permissions</LinkButton> : null}
              <DenButton size="sm" variant="secondary" disabled={loading} onClick={() => setEditing(true)} data-testid="team-role-change">Change role</DenButton>
            </span>
          )}
        </div>
        {editing ? (
          <RoleChoice
            team={team}
            state={state}
            adminKeys={adminKeys}
            abilities={abilities}
            onDone={() => setEditing(false)}
          />
        ) : null}
      </ItemPanel>

      {loading ? (
        <ItemPanel><ItemRowsSkeleton label="Loading what this team can do" rows={3} /></ItemPanel>
      ) : alsoKeys.length > 0 ? (
        <ItemSection title={`${team.name} can also`} testId="team-role-also">
          <ItemPanel>
            {shownAlso.map((key) => <ItemRow key={key} logo={<CheckMark />} title={getPermissionDefinition(key).label} />)}
            {alsoKeys.length > ALSO_PREVIEW ? (
              <div className="px-5 py-2.5">
                <DenButton variant="ghost" size="xs" onClick={() => setShowAll(!showAll)} aria-expanded={showAll} data-testid="team-role-show-all">
                  {showAll ? "Show fewer" : `Show all ${alsoKeys.length}`}
                </DenButton>
              </div>
            ) : null}
          </ItemPanel>
        </ItemSection>
      ) : null}

      <ItemSection title="Everyone can" testId="team-role-everyone">
        <ItemPanel>
          {EVERYONE_CAN.map((label) => <ItemRow key={label} logo={<CheckMark />} title={label} />)}
          {memberKeys.map((key) => <ItemRow key={key} logo={<CheckMark />} title={getPermissionDefinition(key).label} />)}
        </ItemPanel>
      </ItemSection>
    </div>
  );
}

function CheckMark() {
  return <span aria-hidden className="flex h-8 w-8 items-center justify-center text-gray-900"><Check className="h-4 w-4" strokeWidth={2} /></span>;
}

/** The role choice, opened in place under the current role. */
function RoleChoice({ team, state, adminKeys, abilities, onDone }: {
  team: DenOrgTeam;
  state: TeamRoleState;
  adminKeys: readonly PermissionKey[];
  abilities: TeamRoleAbilities;
  onDone: () => void;
}) {
  const router = useRouter();
  const toast = useDenToast();
  const { orgId, orgSlug, updateTeam } = useOrgDashboard();
  const apply = useApplyTeamRole(orgId ?? "", team.id);
  const current = teamRole(state);
  const [choice, setChoice] = useState<TeamRole>(current);
  const lock = (role: TeamRole) => (role === current ? null : teamRoleLockReason(state, role, abilities));
  const changed = choice !== current;

  async function save() {
    if (!changed || lock(choice)) return;
    const previous = current;
    try {
      const createdSetId = await apply.mutateAsync(teamRoleSteps(state, choice, adminKeys));
      onDone();
      // Undo only where nothing is lost: turning the Admin flag on or off.
      const undoable = (previous === "member" && choice === "admin") || (previous === "admin" && choice === "member" && !state.customSetId);
      toast({
        title: `${team.name} is now ${TEAM_ROLE_LABELS[choice]}`,
        action: undoable
          ? { label: "Undo", onClick: () => updateTeam(team.id, { grantsOrganizationAdmin: previous === "admin" }) }
          : undefined,
      });
      if (choice === "custom" && createdSetId) router.push(getPermissionSetRoute(orgSlug, createdSetId));
    } catch {
      return;
    }
  }

  return (
    <fieldset className="flex flex-col gap-3 border-t border-gray-100 px-5 py-4" disabled={apply.isPending} data-testid="team-role-choice">
      <legend className="sr-only">Role for {team.name}</legend>
      <div className="flex flex-col gap-2">
        {ROLE_ORDER.map((role) => {
          const reason = lock(role);
          return (
            <DenOptionCard
              key={role}
              type="radio"
              name={`team-role-${team.id}`}
              checked={choice === role}
              onChange={(checked) => { if (checked) setChoice(role); }}
              title={role === current ? `${TEAM_ROLE_LABELS[role]} (now)` : TEAM_ROLE_LABELS[role]}
              description={reason ?? TEAM_ROLE_DESCRIPTIONS[role]}
              disabled={reason !== null}
              testId={`team-role-option-${role}`}
            />
          );
        })}
      </div>
      {changed && choice === "member" && current === "custom" ? (
        <p className="text-[13px] text-gray-600">{team.name}'s custom permissions are removed.</p>
      ) : null}
      {apply.error ? <DenNotice tone="error" message={apply.error.message} /> : null}
      <div className="flex justify-end gap-2">
        <DenButton size="sm" variant="secondary" onClick={onDone}>Cancel</DenButton>
        <DenButton size="sm" disabled={!changed || lock(choice) !== null} loading={apply.isPending} onClick={() => void save()} data-testid="team-role-save">
          {changed ? `Change to ${TEAM_ROLE_LABELS[choice]}` : "Change role"}
        </DenButton>
      </div>
    </fieldset>
  );
}
