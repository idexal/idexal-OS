/** @jsxImportSource react */
import { useCallback, useEffect, useRef, useState } from "react";
import { Brain, Cloud, Save } from "lucide-react";

import { Button } from "@/components/ui/button";
import { createDenClient, readDenSettings } from "../../../../app/lib/den";
import type { IdexalOsServerClient } from "../../../../app/lib/idexal-os-server";
import { t } from "../../../../i18n";
import { dateLocale } from "@/i18n";
import {
  LayoutSection,
  LayoutSectionDescription,
  LayoutSectionHeader,
  LayoutSectionItem,
  LayoutSectionTitle,
  LayoutStack,
} from "../settings-layout";

type Props = {
  client: IdexalOsServerClient | null;
  workspaceId: string | null;
  workspaceName: string;
  canWrite: boolean;
};

function denClient() {
  const settings = readDenSettings();
  const token = settings.authToken?.trim() ?? "";
  const orgId = settings.activeOrgId?.trim() ?? "";
  if (!token || !orgId) return null;
  return { client: createDenClient({ baseUrl: settings.baseUrl, token }), orgId };
}

export function MemoryView(props: Props) {
  const [content, setContent] = useState("");
  const [savedContent, setSavedContent] = useState("");
  const [status, setStatus] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [path, setPath] = useState<string | null>(null);
  const [updatedAt, setUpdatedAt] = useState<string | null>(null);
  const dirty = content !== savedContent;
  const busyRef = useRef(false);

  const load = useCallback(async (options?: { pullCloud?: boolean }) => {
    if (!props.client || !props.workspaceId) {
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      const local = await props.client.getWorkspaceMemory(props.workspaceId);
      const cloud = denClient();
      let nextContent = local.content;
      let nextUpdatedAt = local.updatedAt;
      if (cloud && options?.pullCloud) {
        try {
          const remote = await cloud.client.getWorkspaceMemory(cloud.orgId, props.workspaceId);
          const remoteTime = remote.updatedAt ? Date.parse(remote.updatedAt) : 0;
          const localTime = local.updatedAt ? Date.parse(local.updatedAt) : 0;
          if (remote.content && remoteTime >= localTime) {
            const written = await props.client.putWorkspaceMemory(props.workspaceId, remote.content);
            nextContent = written.content;
            nextUpdatedAt = written.updatedAt;
            setStatus(t("memory.pulled"));
          } else if (local.exists && local.content.trim() && local.content !== (remote.content ?? "")) {
            await cloud.client.putWorkspaceMemory(cloud.orgId, props.workspaceId, local.content);
            setStatus(t("memory.synced"));
          }
        } catch (error) {
          setStatus(error instanceof Error ? error.message : t("memory.cloud_unavailable"));
        }
      }
      setContent(nextContent);
      setSavedContent(nextContent);
      setPath(local.path);
      setUpdatedAt(nextUpdatedAt);
      if (!options?.pullCloud) setStatus(null);
    } catch (error) {
      setStatus(error instanceof Error ? error.message : t("memory.load_failed"));
    } finally {
      setLoading(false);
    }
  }, [props.client, props.workspaceId]);

  useEffect(() => {
    void load({ pullCloud: true });
  }, [load]);

  // Sync changes written by agents while this page is open. Local edits still
  // win until the person saves or explicitly pulls the cloud copy.
  useEffect(() => {
    if (!props.client || !props.workspaceId) return;
    const timer = window.setInterval(() => {
      if (busyRef.current || dirty) return;
      void (async () => {
        if (!props.client || !props.workspaceId) return;
        const local = await props.client.getWorkspaceMemory(props.workspaceId).catch(() => null);
        if (!local || local.content === savedContent) return;
        const cloud = denClient();
        if (!cloud) return;
        busyRef.current = true;
        try {
          await cloud.client.putWorkspaceMemory(cloud.orgId, props.workspaceId, local.content);
          setSavedContent(local.content);
          setContent(local.content);
          setUpdatedAt(local.updatedAt);
          setStatus(t("memory.synced"));
        } catch {
          setStatus(t("memory.cloud_unavailable"));
        } finally {
          busyRef.current = false;
        }
      })();
    }, 20_000);
    return () => window.clearInterval(timer);
  }, [dirty, props.client, props.workspaceId, savedContent]);

  const save = async () => {
    if (!props.client || !props.workspaceId || !props.canWrite) return;
    setBusy(true);
    busyRef.current = true;
    try {
      const local = await props.client.putWorkspaceMemory(props.workspaceId, content);
      setSavedContent(local.content);
      setUpdatedAt(local.updatedAt);
      const cloud = denClient();
      if (!cloud) {
        setStatus(t("memory.saved_local_only"));
        return;
      }
      await cloud.client.putWorkspaceMemory(cloud.orgId, props.workspaceId, content);
      setStatus(t("memory.saved_synced"));
    } catch (error) {
      setStatus(error instanceof Error ? error.message : t("memory.save_failed"));
    } finally {
      setBusy(false);
      busyRef.current = false;
    }
  };

  const pull = async () => {
    if (!props.client || !props.workspaceId) return;
    setBusy(true);
    busyRef.current = true;
    try {
      const cloud = denClient();
      if (!cloud) {
        setStatus(t("memory.sign_in_required"));
        return;
      }
      const remote = await cloud.client.getWorkspaceMemory(cloud.orgId, props.workspaceId);
      if (!remote.content) {
        setStatus(t("memory.no_cloud_copy"));
        return;
      }
      const local = await props.client.putWorkspaceMemory(props.workspaceId, remote.content);
      setContent(local.content);
      setSavedContent(local.content);
      setUpdatedAt(local.updatedAt);
      setStatus(t("memory.pulled"));
    } catch (error) {
      setStatus(error instanceof Error ? error.message : t("memory.pull_failed"));
    } finally {
      setBusy(false);
      busyRef.current = false;
    }
  };

  return (
    <LayoutStack>
      <LayoutSection>
        <LayoutSectionHeader>
          <LayoutSectionTitle>{t("memory.title")}</LayoutSectionTitle>
          <LayoutSectionDescription>{t("memory.subtitle")}</LayoutSectionDescription>
        </LayoutSectionHeader>
        <LayoutSectionItem>
          <div className="flex flex-wrap items-center gap-2 text-xs text-dls-secondary">
            <span className="inline-flex items-center gap-1.5"><Brain className="size-3.5" />{props.workspaceName || props.workspaceId || t("memory.no_workspace")}</span>
            {path ? <span className="font-mono text-[10px]" dir="ltr">{path}</span> : null}
            {updatedAt ? <span>· {new Date(updatedAt).toLocaleString(dateLocale())}</span> : null}
          </div>
          <p className="mt-2 max-w-3xl text-xs leading-6 text-dls-secondary">{t("memory.explanation")}</p>
          <textarea
            className="mt-4 min-h-[380px] w-full resize-y rounded-xl border border-dls-border bg-dls-surface p-4 font-mono text-[12px] leading-6 text-dls-text"
            value={content}
            onChange={(event) => setContent(event.target.value)}
            disabled={loading || !props.client || !props.workspaceId || !props.canWrite}
            spellCheck={false}
            aria-label={t("memory.title")}
          />
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <Button size="sm" onClick={() => void save()} disabled={busy || !dirty || !props.client || !props.workspaceId || !props.canWrite}>
              <Save className="me-1.5 size-3.5" />
              {busy ? t("memory.saving") : t("memory.save")}
            </Button>
            <Button size="sm" variant="outline" onClick={() => void pull()} disabled={busy || !props.client || !props.workspaceId}>
              <Cloud className="me-1.5 size-3.5" />
              {t("memory.pull")}
            </Button>
            <Button size="sm" variant="ghost" onClick={() => void load({ pullCloud: false })} disabled={busy || !props.client}>
              {t("memory.reload")}
            </Button>
            {dirty ? <span className="text-xs text-dls-secondary">{t("memory.unsaved")}</span> : null}
          </div>
          {status ? <div className="mt-3 text-xs leading-5 text-dls-secondary" role="status">{status}</div> : null}
          {!props.canWrite ? <div className="mt-2 text-xs text-dls-secondary">{t("memory.read_only")}</div> : null}
        </LayoutSectionItem>
      </LayoutSection>
    </LayoutStack>
  );
}
