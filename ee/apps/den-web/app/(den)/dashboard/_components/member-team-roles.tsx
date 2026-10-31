import { getOrgAccessFlags, type DenOrgMember, type DenOrgTeam } from "../../_lib/den-org";
import { teamRoleLabels } from "./team-roles";

/**
 * The roles a person holds through their teams (teamRoles), under their own
 * role wherever people are listed: "Admin via Sales, Engineering".
 */
export function MemberTeamRoles({ member, teams, customTeamIds, customRoleNameByTeam, exceptTeamId }: {
  member: DenOrgMember;
  teams: readonly DenOrgTeam[];
  customTeamIds: ReadonlySet<string>;
  /** sharedRoles: custom roles by their own name instead of the team's. */
  customRoleNameByTeam?: ReadonlyMap<string, string>;
  /** On a team's own page, its role goes without saying. */
  exceptTeamId?: string;
}) {
  // Their own role, without what teams add (effectiveRole includes Admin from a team).
  const ownRole = member.isOwner ? "owner" : getOrgAccessFlags(member.role, false).isAdmin ? "admin" : "member";
  const otherTeams = exceptTeamId ? teams.filter((team) => team.id !== exceptTeamId) : teams;
  const labels = teamRoleLabels({ memberId: member.id, ownRole, teams: otherTeams, customTeamIds, customRoleNameByTeam });
  if (labels.length === 0) return null;
  return <span className="mt-1 block text-[12px] text-gray-500" data-testid="member-team-roles">{labels.join(", ")}</span>;
}
