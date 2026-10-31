import { describe, expect, test } from "bun:test";

import { t } from "../src/i18n";

describe("desktop update notice copy", () => {
  test("interpolates every value the notice passes", () => {
    expect(t("updates.notice_available", { version: "2.2.2", appName: "idexal Os" })).toBe(
      "Version 2.2.2 of idexal Os is available",
    );
    expect(t("updates.notice_downloading", { version: "2.2.2" })).toBe("Downloading version 2.2.2");
    expect(t("updates.notice_downloading_action", { percent: 42 })).toBe("Downloading 42%");
    expect(t("updates.notice_ready", { version: "2.2.2" })).toBe("Version 2.2.2 is ready");
    expect(t("updates.notice_ready_body", { appName: "idexal Os" })).toBe(
      "Restart idexal Os to finish the update. Your workspaces and sign-in stay in place.",
    );
  });
});
