/**
 * Shared completion page for OAuth callbacks. Used by both external MCP
 * connection callbacks and native provider callbacks.
 */

export function escapeHtml(value: string): string {
  return value.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;")
}

export function connectCallbackPage(input:
  | { ok: true; name: string }
  | { ok: false; name: string; message: string; referenceId?: string }): string {
  const title = input.ok ? "You're connected" : "Connection failed"
  const body = input.ok
    ? `<div class="status-row success">
        <span class="status-icon" aria-hidden="true">✓</span>
        <span><strong>${escapeHtml(input.name)} is connected to idexal Os.</strong><small>Connection complete</small></span>
      </div>
      <p>You can close this window and return to idexal Os.</p>`
    : `<div class="status-row failure">
        <span class="status-icon" aria-hidden="true">!</span>
        <span><strong>${escapeHtml(input.name)}</strong><small>${escapeHtml(input.message)}</small></span>
      </div>
      ${input.referenceId ? `<p class="reference">Diagnostic reference: <code>${escapeHtml(input.referenceId)}</code></p>` : ""}
      <p>Return to idexal Os to try connecting again. You can close this window.</p>`
  return `<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>${title} — idexal Os</title>
    <style>
      :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; --dls-surface: #ffffff; --dls-border: #e5e7eb; --dls-text-primary: #011627; --dls-text-secondary: #667085; --dls-hover: #f2f4f7; }
      * { box-sizing: border-box; }
      body { min-height: 100vh; min-height: 100dvh; margin: 0; display: grid; place-items: center; padding: 64px 24px; color: var(--dls-text-primary); background: var(--dls-surface); }
      body::before { position: fixed; inset: 0; z-index: -1; content: ""; background-image: radial-gradient(circle, #011627 .7px, transparent .8px); background-size: 5px 5px; opacity: .1; mask-image: radial-gradient(ellipse at center, transparent 20%, black); }
      main { width: min(100%, 720px); padding: 56px 64px 64px; border: 1px solid var(--dls-border); border-radius: 24px; background: var(--dls-surface); }
      .brand { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600; letter-spacing: -.025em; }
      .brand svg { width: 26px; height: 26px; flex: 0 0 auto; }
      h1 { margin: 56px 0 0; font-size: 38px; font-weight: 600; line-height: 46px; letter-spacing: -.03em; }
      p { margin: 24px 0 0; color: var(--dls-text-secondary); font-size: 15px; line-height: 23px; }
      .status-row { margin-top: 36px; display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid var(--dls-border); border-radius: 12px; }
      .status-row > span:last-child { min-width: 0; display: grid; gap: 3px; }
      .status-row strong { font-size: 15px; font-weight: 500; line-height: 22px; overflow-wrap: anywhere; }
      .status-row small { color: var(--dls-text-secondary); font-size: 13px; line-height: 20px; overflow-wrap: anywhere; }
      .status-icon { flex: 0 0 auto; width: 32px; height: 32px; display: grid; place-items: center; border-radius: 8px; color: var(--dls-text-secondary); background: var(--dls-hover); font-size: 17px; }
      .failure { border-color: #fecaca; background: #fef2f2; }
      .failure .status-icon { color: #b42318; background: #fee2e2; }
      .reference { margin-top: 18px; font-size: 13px; }
      code { font: 12px/1.5 ui-monospace, SFMono-Regular, Menlo, monospace; overflow-wrap: anywhere; user-select: all; }
      @media (max-width: 640px) { main { padding: 40px 32px 48px; } h1 { margin-top: 40px; font-size: 30px; line-height: 38px; } }
      @media (max-width: 380px) { body { padding: 32px 16px; } main { padding: 32px 24px; } }
    </style>
  </head>
  <body>
    <main role="${input.ok ? "status" : "alert"}" aria-live="${input.ok ? "polite" : "assertive"}">
      <div class="brand"><svg aria-hidden="true"
  xmlns="http://www.w3.org/2000/svg"
  viewBox="0 0 377 380"
  fill="none"
>
  <defs>
    <linearGradient id="idexal-os-sliver" gradientUnits="userSpaceOnUse" x1="-34.478" y1="332.95" x2="-5.221" y2="50.423">
      <stop offset="0" stop-color="rgb(0,253,4)"/>
      <stop offset="1" stop-color="rgb(61,253,4)"/>
    </linearGradient>
    <linearGradient id="idexal-os-square" gradientUnits="userSpaceOnUse" x1="4.275" y1="-18.383" x2="17.544" y2="-75.436">
      <stop offset="0" stop-color="rgb(92,253,3)"/>
      <stop offset="1" stop-color="rgb(121,253,3)"/>
    </linearGradient>
    <linearGradient id="idexal-os-ne" gradientUnits="userSpaceOnUse" x1="-1.437" y1="1.712" x2="109.633" y2="-130.659">
      <stop offset="0" stop-color="rgb(9,253,3)"/>
      <stop offset="1" stop-color="rgb(105,253,3)"/>
    </linearGradient>
    <linearGradient id="idexal-os-se" gradientUnits="userSpaceOnUse" x1="-36.947" y1="39.2" x2="26.369" y2="-27.976">
      <stop offset="0" stop-color="rgb(0,252,1)"/>
      <stop offset="1" stop-color="rgb(13,252,1)"/>
    </linearGradient>
  </defs>
  <path fill="url(#idexal-os-sliver)" d="M 5.8939 337.1301 L 6.0367 51.5886 L 85.1534 146.8953 L 85.1246 243.1621 L 5.8939 337.1301 Z"/>
  <path fill="url(#idexal-os-square)" d="M 304.39 2.64 L 352.625 2.64 A 5.45 5.45 0 0 1 358.075 8.09 L 358.075 45.82 A 5.45 5.45 0 0 1 352.625 51.27 L 304.39 51.27 A 5.45 5.45 0 0 1 298.94 45.82 L 298.94 8.09 A 5.45 5.45 0 0 1 304.39 2.64 Z"/>
  <path fill="url(#idexal-os-ne)" d="M 354.0644 74.4388 L 267.5786 74.4647 A 3.9 3.9 0 0 0 263.6819 75.9995 L 214.8545 138.19 A 4.15 4.15 0 0 0 214.95 141.9381 L 245.6181 181.3625 A 22.3 22.3 0 0 1 247.4543 210.4422 L 354.0644 74.4388 Z"/>
  <path fill="url(#idexal-os-se)" d="M 371.7243 336.362 L 265.2408 197.1722 A 59.8 59.8 0 0 1 258.7081 214.782 L 227.4575 265.1954 A 2 2 0 0 0 226.6662 267.178 L 278.8901 336.3564 A 2 2 0 0 0 278.1802 336.2213 L 353.1295 337.3427 A 118.5 118.5 0 0 1 371.7243 336.362 Z"/>
  <path fill="#0B0B0B" d="M 94.5628 2.714 L 6.4295 2.6171 L 158.4632 188.8062 A 10.9 10.9 0 0 1 158.3804 202.1671 L 14.5998 376.1206 L 110.5818 376.0918 A 5.775 5.775 0 0 0 115.7849 374.2534 L 246.8612 209.9904 A 22.65 22.65 0 0 0 246.8982 180.6461 L 103.6775 6.8675 A 11 11 0 0 0 94.5628 2.714 Z"/>
</svg><span>idexal Os</span></div>
      <h1>${title}</h1>
      ${body}
    </main>
  </body>
</html>`
}
