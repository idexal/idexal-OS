import { mountMcpApp } from "./shared/bridge"
import { ConnectionView, connectionResultSchema } from "./connection-view"

mountMcpApp({
  name: "idexal Os Connection",
  schema: connectionResultSchema,
  acceptError: payload => payload.state !== "connected" && payload.action !== null,
  render: props => <ConnectionView {...props} />,
})
