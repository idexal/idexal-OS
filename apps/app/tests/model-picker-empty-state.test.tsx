/** @jsxImportSource react */
import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { afterAll, afterEach, expect, mock, test } from "bun:test";
import { act } from "react";
import { createRoot, type Root } from "react-dom/client";

const ownedDom = typeof window === "undefined";
if (ownedDom) GlobalRegistrator.register({ url: "http://localhost/" });
const previousAct = Reflect.get(globalThis, "IS_REACT_ACT_ENVIRONMENT");
Reflect.set(globalThis, "IS_REACT_ACT_ENVIRONMENT", true);

const { ModelPickerList } = await import("../src/react-app/domains/models/model-picker-list");
const { t } = await import("../src/i18n");

type Props = Parameters<typeof ModelPickerList>[0];

let root: Root | null = null;
let host: HTMLDivElement;

const base: Props = {
  options: [],
  current: { providerID: "", modelID: "" },
  query: "",
  onQueryChange: () => {},
  onSelect: () => {},
  catalogState: { state: "ready" },
};

async function mount(patch: Partial<Props> = {}) {
  host = document.createElement("div");
  document.body.append(host);
  root = createRoot(host);
  await act(async () => root.render(<ModelPickerList {...base} {...patch} />));
}

function button(label: string): HTMLButtonElement | undefined {
  return Array.from(host.querySelectorAll("button")).find((candidate) => candidate.textContent === label) as HTMLButtonElement | undefined;
}

afterEach(async () => {
  await act(async () => root?.unmount());
  root = null;
  host.remove();
  mock.restore();
});
afterAll(async () => {
  Reflect.set(globalThis, "IS_REACT_ACT_ENVIRONMENT", previousAct);
  if (ownedDom) await GlobalRegistrator.unregister();
});

test("an empty managed workspace says so, offers refresh, and for an org admin the publishing screen", async () => {
  const onManageOrganizationModels = mock(() => {});
  await mount({
    organizationModelsEmpty: true,
    catalogState: { state: "ready", onRetry: () => {} },
    onManageOrganizationModels,
  });

  expect(host.textContent).toContain(t("models.organization_models_empty"));
  expect(host.querySelector('[data-testid="model-catalog-empty"]')).not.toBeNull();

  const manage = button(t("models.manage_organization_models"));
  expect(manage).toBeDefined();
  await act(async () => manage?.click());
  expect(onManageOrganizationModels).toHaveBeenCalledTimes(1);
});

test("a member without the admin surface is offered no publishing button", async () => {
  await mount({ organizationModelsEmpty: true, catalogState: { state: "ready", onRetry: () => {} } });

  expect(host.textContent).toContain(t("models.organization_models_empty"));
  expect(button(t("models.manage_organization_models"))).toBeUndefined();
});

test("a workspace that can connect a provider keeps the connect copy and no publishing button", async () => {
  const onConnectProvider = mock(() => {});
  await mount({ onConnectProvider, onManageOrganizationModels: mock(() => {}) });

  expect(host.textContent).toContain("Nothing is connected in this workspace.");
  expect(button(t("models.manage_organization_models"))).toBeUndefined();
});
