import { cookies } from "next/headers";
import type { ReactNode } from "react";
import { AdminShell } from "../../components/admin/admin-shell";

export default async function AdminLayout({ children }: { children: ReactNode }) {
  const cookieStore = await cookies();
  // Only a UI hint. Each admin data endpoint verifies the session and allowlist.
  const hasSessionCookie = cookieStore.getAll().some(({ name, value }) =>
    Boolean(value) && /^(?:__Secure-)?(?:idexal-os-den|better-auth)\.session_token(?:\.\d+)?$/.test(name),
  );
  return <AdminShell hasSessionCookie={hasSessionCookie}>{children}</AdminShell>;
}
