import { Suspense } from "react";
import { AutomationsScreen } from "../_components/automations-screen";

export default function AutomationsPage() {
  return (
    <Suspense fallback={<div className="mx-auto max-w-[980px] px-6 py-8 text-[13px] text-gray-400">Loading Automations…</div>}>
      <AutomationsScreen />
    </Suspense>
  );
}
