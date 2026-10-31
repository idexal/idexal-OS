import type { Metadata } from "next";
import { GatewayConnect } from "./gateway-connect";

export const metadata: Metadata = {
  title: "Connect to AI Gateway — idexal Os",
  referrer: "no-referrer",
  robots: { index: false, follow: false },
};

export default function GatewayConnectPage() {
  return <GatewayConnect />;
}
