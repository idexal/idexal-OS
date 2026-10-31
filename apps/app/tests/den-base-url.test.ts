import { describe, expect, test } from "bun:test";

import { resolveDenBaseUrls } from "../src/app/lib/den";

describe("control plane URL resolution", () => {
  test("the official platform uses the same origin for its API", () => {
    const urls = resolveDenBaseUrls("https://os.idexal.com/");
    expect(urls.baseUrl).toBe("https://os.idexal.com");
    expect(urls.apiBaseUrl).toBe("https://os.idexal.com");
  });

  test("an explicit API base still wins", () => {
    const urls = resolveDenBaseUrls({
      baseUrl: "https://os.idexal.com",
      apiBaseUrl: "https://os.idexal.com",
    });
    expect(urls.apiBaseUrl).toBe("https://os.idexal.com");
  });

  test("an explicit API host is left as-is", () => {
    const urls = resolveDenBaseUrls("https://api.example.test");
    expect(urls.apiBaseUrl).toBe("https://api.example.test");
  });

  test("a stale hosted API base is repaired to the same origin", () => {
    const urls = resolveDenBaseUrls({
      baseUrl: "https://os.idexal.com",
      apiBaseUrl: "https://api.os.idexal.com",
    });
    expect(urls.apiBaseUrl).toBe("https://os.idexal.com");
  });
});
