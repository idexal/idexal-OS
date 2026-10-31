/** @jsxImportSource react */
import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { afterAll, afterEach, expect, mock, test } from "bun:test";
import { act, type ReactNode } from "react";
import { createRoot, type Root } from "react-dom/client";
import type { ModelPickerPanelProps } from "../src/react-app/domains/models/model-picker-panel";
import type { ModelPickerCatalogState } from "../src/react-app/domains/models/model-catalog";

const ownedDom = typeof window === "undefined";
if (ownedDom) GlobalRegistrator.register({ url: "http://localhost/" });
const previousAct = Reflect.get(globalThis, "IS_REACT_ACT_ENVIRONMENT");
Reflect.set(globalThis, "IS_REACT_ACT_ENVIRONMENT", true);

// The popover is not under test; rendering its children inline keeps the test
// off Radix's DOM requirements. Every other export of the real module stays.
const popoverActual = await import("../src/components/ui/popover");
mock.module("../src/components/ui/popover", () => ({
  ...popoverActual,
  Popover: ({ children }: { children: ReactNode }) => <>{children}</>,
  PopoverTrigger: ({ children }: { children: ReactNode }) => <>{children}</>,
  PopoverContent: ({ children }: { children: ReactNode }) => <>{children}</>,
}));

const workspaceActual = await import("../src/react-app/shell/workspace-provider");
mock.module("../src/react-app/shell/workspace-provider", () => ({
  ...workspaceActual,
  useWorkspace: () => ({ client: undefined, opencodeBaseUrl: "", selectedWorkspaceRoot: "" }),
}));

const modelDefaultActual = await import("../src/react-app/kernel/use-workspace-model-default");
mock.module("../src/react-app/kernel/use-workspace-model-default", () => ({
  ...modelDefaultActual,
  useSetWorkspaceDefaultModel: () => () => {},
}));

const desktopConfigActual = await import("../src/react-app/domains/cloud/desktop-config-provider");
mock.module("../src/react-app/domains/cloud/desktop-config-provider", () => ({
  ...desktopConfigActual,
  useCheckDesktopRestriction: () => () => false,
}));

const catalogActual = await import("../src/react-app/domains/models/use-model-catalog");
let baseRetry: ReturnType<typeof mock> = mock(() => {});
mock.module("../src/react-app/domains/models/use-model-catalog", () => ({
  ...catalogActual,
  useModelCatalog: () => ({
    options: [],
    knownOptions: [],
    actionOptions: [],
    catalogState: { state: "ready", onRetry: baseRetry } as ModelPickerCatalogState,
    retainedSelection: undefined,
    restrictToCloud: false,
    autoVisible: false,
  }),
  useModelChoice: () => ({
    choose: (_option: unknown, commit: () => void) => commit(),
    loginOpen: false,
    pendingOptions: [],
    disabledProviders: [],
  }),
}));

// The picker panel is captured instead of rendered: this proves exactly what
// the composer's inline picker hands the shared list.
let captured: ModelPickerPanelProps | null = null;
const panelActual = await import("../src/react-app/domains/models/model-picker-panel");
mock.module("../src/react-app/domains/models/model-picker-panel", () => ({
  ...panelActual,
  ModelPickerPanel: (props: ModelPickerPanelProps) => {
    captured = props;
    return null;
  },
  ModelPickerFooter: () => null,
}));

const { ModelSelect } = await import("../src/components/model-select");

type Props = Parameters<typeof ModelSelect>[0];

let root: Root | null = null;
let host: HTMLDivElement;
let onOpenChange: ReturnType<typeof mock>;

async function mount(patch: Partial<Props> = {}) {
  captured = null;
  onOpenChange = mock(() => {});
  host = document.createElement("div");
  document.body.append(host);
  root = createRoot(host);
  await act(async () => root.render(<ModelSelect open value={{ providerID: "", modelID: "" }} onOpenChange={onOpenChange} onChange={() => {}} {...patch} />));
}

afterEach(async () => {
  await act(async () => root?.unmount());
  root = null;
  host.remove();
  baseRetry.mockClear();
});
afterAll(async () => {
  Reflect.set(globalThis, "IS_REACT_ACT_ENVIRONMENT", previousAct);
  if (ownedDom) await GlobalRegistrator.unregister();
});

test("an org-empty workspace reaches the picker and its retry is the organization refresh", async () => {
  const previousRetry = baseRetry;
  const onRefreshOrganizationModels = mock(() => {});
  await mount({ organizationModelsEmpty: true, onRefreshOrganizationModels });

  expect(captured?.organizationModelsEmpty).toBe(true);
  // The provider-list retry would change nothing here; the org sync is the fix.
  expect(captured?.catalogState.onRetry).toBe(onRefreshOrganizationModels);
  expect(captured?.catalogState.onRetry).not.toBe(previousRetry);
});

test("a workspace that can connect a provider keeps the local retry and no org-empty flag", async () => {
  await mount();

  expect(captured?.organizationModelsEmpty).toBe(false);
  expect(captured?.catalogState.onRetry).toBe(baseRetry);
});

test("an org admin's manage action reaches the inline picker and closes it first", async () => {
  const manage = mock(() => {});
  await mount({ organizationModelsEmpty: true, onManageOrganizationModels: manage });

  const action = captured?.onManageOrganizationModels;
  expect(typeof action).toBe("function");

  await act(async () => action?.());
  // The same action the dialog offers, and the popover must not stay open behind it.
  expect(manage).toHaveBeenCalledTimes(1);
  expect(onOpenChange).toHaveBeenCalledWith(false);
});

test("a member with no manage action is offered no such control", async () => {
  await mount({ organizationModelsEmpty: true });

  expect(captured?.onManageOrganizationModels).toBeUndefined();
});
