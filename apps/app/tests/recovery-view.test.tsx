import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { afterAll, beforeAll, expect, test } from "bun:test";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderToStaticMarkup } from "react-dom/server";

import { RecoveryView } from "../src/react-app/domains/settings/pages/recovery-view";

beforeAll(() => {
  GlobalRegistrator.register();
  Object.assign(window, { __IDEXAL_ELECTRON__: {} });
});

afterAll(() => {
  GlobalRegistrator.unregister();
});

function buttonFor(html: string, label: string): string {
  const match = html.match(new RegExp(`<button[^>]*>${label}</button>`));
  expect(match).not.toBeNull();
  return match?.[0] ?? "";
}

function hasDisabledAttribute(button: string): boolean {
  return /\sdisabled(?:="")?[\s>]/.test(button);
}

test("all recovery actions are enabled in the desktop settings page", () => {
  const queryClient = new QueryClient();
  const html = renderToStaticMarkup(
    <QueryClientProvider client={queryClient}>
      <RecoveryView
        anyActiveRuns={false}
        workspaceConfigPath={"C:\\workspace\\.opencode\\idexal-os.json"}
        resetConfigBusy={false}
        onResetAppConfigDefaults={() => undefined}
        configActionStatus={null}
        cacheRepairBusy={false}
        cacheRepairResult={null}
        onRepairOpencodeCache={() => undefined}
        dockerCleanupBusy={false}
        dockerCleanupResult={null}
        onCleanupIdexalOsDockerContainers={() => undefined}
      />
    </QueryClientProvider>,
  );

  for (const label of ["Reset to defaults", "Repair cache", "Remove all"]) {
    expect(hasDisabledAttribute(buttonFor(html, label))).toBe(false);
  }
});

test("active runs keep the destructive recovery actions disabled", () => {
  const queryClient = new QueryClient();
  const html = renderToStaticMarkup(
    <QueryClientProvider client={queryClient}>
      <RecoveryView
        anyActiveRuns
        workspaceConfigPath={"C:\\workspace\\.opencode\\idexal-os.json"}
        resetConfigBusy={false}
        onResetAppConfigDefaults={() => undefined}
        configActionStatus={null}
        cacheRepairBusy={false}
        cacheRepairResult={null}
        onRepairOpencodeCache={() => undefined}
        dockerCleanupBusy={false}
        dockerCleanupResult={null}
        onCleanupIdexalOsDockerContainers={() => undefined}
      />
    </QueryClientProvider>,
  );

  expect(hasDisabledAttribute(buttonFor(html, "Reset to defaults"))).toBe(true);
  expect(hasDisabledAttribute(buttonFor(html, "Remove all"))).toBe(true);
  expect(hasDisabledAttribute(buttonFor(html, "Repair cache"))).toBe(false);
});
