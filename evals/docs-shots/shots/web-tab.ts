import { waitFor } from "@idexal/behaviors";
import { org } from "../seed.ts";
import { webTab } from "../surfaces.ts";
import { shot } from "./shot.ts";

const browser = webTab({ org });

async function waitForIdexalOsWeb(surface: Awaited<ReturnType<typeof browser.load>>): Promise<void> {
  await waitFor(surface, () => (Boolean(window.__idexalOsControl)), {
    timeoutMs: 120_000,
    label: "idexal Os Web booted",
  });
  await waitFor(surface, () => (document.body.innerText.includes("acme-robotics")
    && document.body.innerText.includes("Describe your task")
    && !document.body.innerText.includes("Pulling in the latest messages")), {
    timeoutMs: 120_000,
    label: "idexal Os Web settled on the demo workspace",
  });
}

export const idexalOsWebTab = shot("idexal-os-web-tab", {
  use: browser,
  at: "/",
  steps: [waitForIdexalOsWeb],
  expect: ["acme-robotics", "What do you need done?"],
  never: ["Something went wrong", "Unable to connect", "docs-3959-screenshots"],
  out: "packages/docs/images/idexal-os-web-browser-tab.png",
});
