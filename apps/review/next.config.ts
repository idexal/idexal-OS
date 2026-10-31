import { resolve } from "node:path";
import type { NextConfig } from "next";

const config: NextConfig = {
  outputFileTracingRoot: resolve(import.meta.dirname, "../.."),
  transpilePackages: ["@idexal/review", "@idexal/evidence-provider", "@idexal/daytona-evidence", "@idexal/freestyle"],
  outputFileTracingIncludes: { "/*": ["../../packages/freestyle/src/*.mjs", "../../packages/freestyle/src/builder.ts", "../../packages/freestyle/src/cache.ts", "../../packages/freestyle/src/build-recipes.ts"] },
};
export default config;
