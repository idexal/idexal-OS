import { execSync } from "node:child_process";

execSync("pnpm --filter @idexal/desktop build", { stdio: "inherit" });
