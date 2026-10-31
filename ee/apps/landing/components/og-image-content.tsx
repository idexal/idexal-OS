import { ChevronRight } from "lucide-react";

const demoData = {
  task: "Like Twitter replies and extract users to CSV.",
  chatHistory: [
    {
      role: "user" as const,
      content: "Like all the replies to this tweet and save the users to a CSV."
    },
    {
      role: "timeline" as const,
      items: ["Navigates to tweet URL", "Extracts bio data to tweet_replies.csv"]
    },
    {
      role: "agent" as const,
      content:
        'I liked 42 replies and saved the data to "tweet_replies.csv" on your desktop.'
    }
  ]
};

export function IdexalOsLogo(props: { size?: number; titleSize?: number }) {
  const size = props.size ?? 48;
  const titleSize = props.titleSize ?? 36;

  return (
    <div style={{ alignItems: "center", color: "#011627", display: "flex", gap: 12 }}>
      <svg width={size} height={(size * 380) / 377} viewBox="0 0 377 380" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="idexal-os-sliver" gradientUnits="userSpaceOnUse" x1="-34.478" y1="332.95" x2="-5.221" y2="50.423">
            <stop offset="0" stopColor="rgb(0,253,4)" />
            <stop offset="1" stopColor="rgb(61,253,4)" />
          </linearGradient>
          <linearGradient id="idexal-os-square" gradientUnits="userSpaceOnUse" x1="4.275" y1="-18.383" x2="17.544" y2="-75.436">
            <stop offset="0" stopColor="rgb(92,253,3)" />
            <stop offset="1" stopColor="rgb(121,253,3)" />
          </linearGradient>
          <linearGradient id="idexal-os-ne" gradientUnits="userSpaceOnUse" x1="-1.437" y1="1.712" x2="109.633" y2="-130.659">
            <stop offset="0" stopColor="rgb(9,253,3)" />
            <stop offset="1" stopColor="rgb(105,253,3)" />
          </linearGradient>
          <linearGradient id="idexal-os-se" gradientUnits="userSpaceOnUse" x1="-36.947" y1="39.2" x2="26.369" y2="-27.976">
            <stop offset="0" stopColor="rgb(0,252,1)" />
            <stop offset="1" stopColor="rgb(13,252,1)" />
          </linearGradient>
        </defs>
        <path fill="url(#idexal-os-sliver)" d="M 5.8939 337.1301 L 6.0367 51.5886 L 85.1534 146.8953 L 85.1246 243.1621 L 5.8939 337.1301 Z" />
        <path fill="url(#idexal-os-square)" d="M 304.39 2.64 L 352.625 2.64 A 5.45 5.45 0 0 1 358.075 8.09 L 358.075 45.82 A 5.45 5.45 0 0 1 352.625 51.27 L 304.39 51.27 A 5.45 5.45 0 0 1 298.94 45.82 L 298.94 8.09 A 5.45 5.45 0 0 1 304.39 2.64 Z" />
        <path fill="url(#idexal-os-ne)" d="M 354.0644 74.4388 L 267.5786 74.4647 A 3.9 3.9 0 0 0 263.6819 75.9995 L 214.8545 138.19 A 4.15 4.15 0 0 0 214.95 141.9381 L 245.6181 181.3625 A 22.3 22.3 0 0 1 247.4543 210.4422 L 354.0644 74.4388 Z" />
        <path fill="url(#idexal-os-se)" d="M 371.7243 336.362 L 265.2408 197.1722 A 59.8 59.8 0 0 1 258.7081 214.782 L 227.4575 265.1954 A 2 2 0 0 0 226.6662 267.178 L 278.8901 336.3564 A 2 2 0 0 0 278.1802 336.2213 L 353.1295 337.3427 A 118.5 118.5 0 0 1 371.7243 336.362 Z" />
        <path fill="#0B0B0B" d="M 94.5628 2.714 L 6.4295 2.6171 L 158.4632 188.8062 A 10.9 10.9 0 0 1 158.3804 202.1671 L 14.5998 376.1206 L 110.5818 376.0918 A 5.775 5.775 0 0 0 115.7849 374.2534 L 246.8612 209.9904 A 22.65 22.65 0 0 0 246.8982 180.6461 L 103.6775 6.8675 A 11 11 0 0 0 94.5628 2.714 Z" />
      </svg>
      <span style={{ fontSize: titleSize, fontWeight: 600, letterSpacing: -0.7, lineHeight: 1, textTransform: "lowercase" }}>
        idexal Os
      </span>
    </div>
  );
}

export function OGImageContent() {
  return (
    <div
      style={{
        width: "100%",
        height: "100%",
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        padding: "0 64px",
        position: "relative",
        zIndex: 1,
        color: "#011627",
        fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
      }}
    >
      <div style={{ width: 500, display: "flex", flexDirection: "column", gap: 32, paddingLeft: 32 }}>
        <IdexalOsLogo />

        <div
          style={{
            display: "flex",
            flexDirection: "column",
            fontSize: 62,
            fontWeight: 500,
            letterSpacing: -2.2,
            lineHeight: 1.05
          }}
        >
          <div>The team layer for your</div>
          <div style={{ display: "flex", alignItems: "baseline", gap: 12 }}>
            existing <span style={{ fontSize: 74 }}>agent</span> setup.
          </div>
        </div>

        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: 8,
            padding: "14px 20px",
            background: "rgba(255, 255, 255, 0.6)",
            border: "1px solid rgba(229, 231, 235, 0.6)",
            borderRadius: 9999,
            boxShadow: "0 1px 2px rgba(15, 23, 42, 0.06)",
            alignSelf: "flex-start",
            marginTop: 16
          }}
        >
          <span style={{ color: "#6b7280", fontSize: 15, fontWeight: 500 }}>Backed by</span>
          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
            <div
              style={{
                width: 22,
                height: 22,
                background: "#ff6600",
                borderRadius: 5,
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                color: "#fff",
                fontSize: 13,
                fontWeight: 700,
                lineHeight: 1
              }}
            >
              Y
            </div>
            <span style={{ color: "#374151", fontSize: 15, fontWeight: 600, letterSpacing: -0.2 }}>
              Combinator
            </span>
          </div>
        </div>
      </div>

      <div
        style={{
          width: 580,
          height: 480,
          background: "rgba(255, 255, 255, 0.8)",
          borderRadius: 24,
          boxShadow: "0 20px 60px -15px rgba(0, 0, 0, 0.15)",
          border: "1px solid rgba(255, 255, 255, 1)",
          overflow: "hidden",
          display: "flex",
          flexDirection: "column",
          transform: "translateY(48px)"
        }}
      >
        <div
          style={{
            height: 40,
            width: "100%",
            background: "linear-gradient(to bottom, rgba(255,255,255,0.9), rgba(255,255,255,0.6))",
            borderBottom: "1px solid #f3f4f6",
            display: "flex",
            alignItems: "center",
            padding: "0 16px",
            position: "relative",
            flexShrink: 0
          }}
        >
          <div style={{ display: "flex", gap: 6 }}>
            <div style={{ width: 12, height: 12, borderRadius: 9999, background: "rgba(255, 95, 86, 0.9)", border: "1px solid rgba(224, 68, 62, 0.2)" }} />
            <div style={{ width: 12, height: 12, borderRadius: 9999, background: "rgba(255, 189, 46, 0.9)", border: "1px solid rgba(222, 161, 35, 0.2)" }} />
            <div style={{ width: 12, height: 12, borderRadius: 9999, background: "rgba(39, 201, 63, 0.9)", border: "1px solid rgba(26, 171, 41, 0.2)" }} />
          </div>
          <div style={{ position: "absolute", left: "50%", transform: "translateX(-50%)", fontSize: 12, fontWeight: 500, color: "#6b7280", letterSpacing: 0.5 }}>
            idexal Os
          </div>
        </div>

        <div style={{ padding: 20, flex: 1, background: "#fff", display: "flex", gap: 16 }}>
          <div
            style={{
              width: 200,
              background: "#f9fafb",
              borderRadius: 12,
              padding: 12,
              display: "flex",
              flexDirection: "column",
              gap: 8,
              border: "1px solid #f3f4f6",
              flexShrink: 0
            }}
          >
            <div style={{ display: "flex", alignItems: "center", gap: 12, padding: 12, background: "#fff", boxShadow: "0 1px 2px rgba(15, 23, 42, 0.06)", borderRadius: 12, border: "1px solid #f3f4f6" }}>
              <div style={{ width: 24, height: 24, borderRadius: 9999, background: "linear-gradient(135deg, #34d399, #0f9f7f)", flexShrink: 0 }} />
              <div style={{ display: "flex", flexDirection: "column", minWidth: 0 }}>
                <div style={{ fontWeight: 500, fontSize: 14, color: "#011627", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>Sales Team</div>
                <div style={{ fontSize: 12, color: "#6b7280", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>Daily tasks</div>
              </div>
            </div>

            <div style={{ marginTop: 16, padding: "0 4px 4px" }}>
              <div style={{ display: "flex", flexDirection: "column", gap: 4, position: "relative", paddingLeft: 12 }}>
                <div style={{ position: "absolute", left: 0, top: 8, bottom: 8, width: 2, background: "#e5e7eb" }} />
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "10px 12px", borderRadius: 12, background: "rgba(229, 231, 235, 0.5)", fontSize: 13 }}>
                  <span style={{ marginRight: 8, color: "#374151", fontWeight: 500 }}>Twitter replies...</span>
                  <span style={{ color: "#9ca3af" }}>1s ago</span>
                </div>
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "10px 12px", borderRadius: 12, fontSize: 13 }}>
                  <span style={{ marginRight: 8, color: "#4b5563" }}>Q3 outliers</span>
                  <span style={{ color: "#9ca3af" }}>15m</span>
                </div>
              </div>
            </div>
          </div>

          <div
            style={{
              flex: 1,
              background: "#fff",
              borderRadius: 12,
              display: "flex",
              flexDirection: "column",
              border: "1px solid #f3f4f6",
              boxShadow: "0 1px 2px rgba(15, 23, 42, 0.06)",
              overflow: "hidden"
            }}
          >
            <div style={{ flex: 1, padding: 20, display: "flex", flexDirection: "column", gap: 24, fontSize: 14 }}>
              {demoData.chatHistory.map((msg, idx) =>
                msg.role === "user" ? (
                  <div key={idx} style={{ background: "rgba(243, 244, 246, 0.8)", borderRadius: 9999, padding: "12px 20px", color: "#1f2937", alignSelf: "center", maxWidth: "85%", textAlign: "center", marginTop: 8 }}>
                    {msg.content}
                  </div>
                ) : msg.role === "timeline" ? (
                  <div key={idx} style={{ display: "flex", flexDirection: "column", gap: 12, color: "#9ca3af", fontSize: 12, marginLeft: 8 }}>
                    {msg.items.map((item, itemIdx) => (
                      <div key={itemIdx} style={{ display: "flex", alignItems: "center", gap: 8 }}>
                        <ChevronRight size={12} color="#d1d5db" />
                        <span>{item}</span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div key={idx} style={{ color: "#1f2937", lineHeight: 1.6, marginLeft: 8, maxWidth: "95%", fontSize: 14, marginBottom: 8, fontWeight: 500 }}>
                    {msg.content}
                  </div>
                )
              )}
            </div>

            <div style={{ padding: 16, background: "#f9fafb", borderTop: "1px solid #f3f4f6", marginTop: "auto", display: "flex", flexDirection: "column" }}>
              <div style={{ fontSize: 12, color: "#9ca3af", marginBottom: 8, padding: "0 4px" }}>Describe your task</div>
              <div style={{ fontSize: 13, lineHeight: 1.5, color: "#011627", background: "#fff", padding: 12, borderRadius: 12, border: "1px solid #f3f4f6", boxShadow: "0 1px 2px rgba(15, 23, 42, 0.06)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                {demoData.task}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
