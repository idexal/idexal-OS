import { GlobalRegistrator } from "@happy-dom/global-registrator";

// Happy DOM's unregister() keeps custom properties on the next window. A test
// that installs __IDEXAL_ELECTRON__ without restoring it therefore makes later
// files take desktop-only branches. Clear the bridge on every teardown.
const originalUnregister = GlobalRegistrator.unregister.bind(GlobalRegistrator);

Object.defineProperty(GlobalRegistrator, "unregister", {
  configurable: true,
  value: () => {
    const current = (globalThis as { window?: Window }).window;
    if (current && "__IDEXAL_ELECTRON__" in current) {
      Reflect.deleteProperty(current, "__IDEXAL_ELECTRON__");
    }
    originalUnregister();
  },
});
