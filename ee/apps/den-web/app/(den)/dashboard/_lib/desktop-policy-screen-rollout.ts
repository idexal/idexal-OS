import { getOrgAccessFlags, orgFeatureEnabled, type DenOrgContext } from "../../_lib/den-org";

/** Never remove the only editor a viewer can reach. Killing either rollout restores it. */
export function desktopPolicyScreenRemoved(context: DenOrgContext | null): boolean {
  if (!context) return false;
  const access = getOrgAccessFlags(context.currentMember.role, context.currentMember.isOwner, context.currentMember.permissions);
  return orgFeatureEnabled(context, "desktopPolicyScreenRemoval")
    && orgFeatureEnabled(context, "teamPageTabs")
    && access.canViewSettings && access.canViewTeams && access.canViewDesktopPolicies;
}
