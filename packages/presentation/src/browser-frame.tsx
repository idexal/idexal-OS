import React from "react";
import { interpolate, Easing } from "remotion";
export const ease = Easing.bezier(0.2, 0.85, 0.2, 1);
export const mix = (f: number, a: number, b: number, x = 0, y = 1) =>
  interpolate(f, [a, b], [x, y], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: ease,
  });
export function Mark({ size = 24 }: { size?: number }) {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 377 380"
      width={size}
      height={size}
      fill="none"
    >
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
  );
}
export function Icon({ name, size = 18 }: { name: string; size?: number }) {
  const paths: Record<string, React.ReactNode> = {
    arrow: <path d="M5 12h14m-6-6 6 6-6 6" />,
    check: <path d="m5 12 4 4L19 6" />,
    down: <path d="M12 3v12m-5-5 5 5 5-5M5 17v4h14v-4" />,
    plus: <path d="M12 5v14M5 12h14" />,
    lock: (
      <>
        <rect x="6" y="10" width="12" height="10" rx="2" />
        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
      </>
    ),
    chevron: <path d="m9 5 7 7-7 7" />,
    refresh: (
      <>
        <path d="M19 8a8 8 0 1 0 1 7M19 3v5h-5" />
      </>
    ),
    grid: (
      <>
        <rect x="4" y="4" width="6" height="6" rx="1" />
        <rect x="14" y="4" width="6" height="6" rx="1" />
        <rect x="4" y="14" width="6" height="6" rx="1" />
        <rect x="14" y="14" width="6" height="6" rx="1" />
      </>
    ),
    chat: <path d="M5 4h14v12H9l-4 4V4Z" />,
    settings: (
      <>
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2" />
      </>
    ),
    folder: <path d="M3 6h7l2 3h9v11H3V6Z" />,
  };
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      {paths[name]}
    </svg>
  );
}
export function BrowserFrame({
  children,
  section = "Get started",
  address = "idexal Os",
  download,
}: {
  children: React.ReactNode;
  section?: string;
  address?: string;
  download?: React.ReactNode;
}) {
  return (
    <div
      style={{
        width: 1600,
        height: 1000,
        background: "#fff",
        overflow: "hidden",
        position: "relative",
      }}
    >
      <div
        style={{
          height: 60,
          background: "linear-gradient(#f4f4f2,#e9eae7)",
          borderBottom: "1px solid #d8dad5",
          display: "flex",
          alignItems: "center",
          gap: 22,
          padding: "0 23px",
          color: "#73776e",
          position: "relative",
          zIndex: 4,
        }}
      >
        <div style={{ display: "flex", gap: 8 }}>
          {["#c4c8bf", "#c4c8bf", "#c4c8bf"].map((c, i) => (
            <div
              key={i}
              style={{
                width: 10,
                height: 10,
                borderRadius: "50%",
                background: c,
                border: "1px solid #aeb4a660",
              }}
            />
          ))}
        </div>
        <div
          style={{
            display: "flex",
            gap: 18,
            alignItems: "center",
            marginLeft: 8,
          }}
        >
          <span style={{ transform: "rotate(180deg)", display: "flex" }}>
            <Icon name="chevron" size={16} />
          </span>
          <Icon name="chevron" size={16} />
          <Icon name="refresh" size={16} />
        </div>
        <div
          style={{
            width: 240,
            display: "flex",
            gap: 10,
            alignItems: "center",
            color: "#333a30",
            fontSize: 13,
            fontWeight: 550,
          }}
        >
          <Mark size={17} />
          idexal Os{" "}
          <span style={{ color: "#a5aa9e", fontSize: 12, fontWeight: 400 }}>
            {" "}
            / {section}
          </span>
        </div>
        <div
          style={{
            position: "absolute",
            left: 585,
            top: 12,
            width: 430,
            height: 35,
            borderRadius: 8,
            border: "1px solid #d1d5ca",
            boxShadow: "inset 0 1px 2px #00000004",
            background: "#f9faf7",
            display: "flex",
            gap: 9,
            alignItems: "center",
            justifyContent: "center",
            fontSize: 12,
            color: "#646d5c",
          }}
        >
          <Icon name="lock" size={12} />
          {address}
        </div>
        <div
          style={{
            marginLeft: "auto",
            display: "flex",
            alignItems: "center",
            gap: 25,
          }}
        >
          <Icon name="down" size={17} />
          <span style={{ fontSize: 20, lineHeight: 1 }}>⋯</span>
        </div>
      </div>
      <div style={{ height: 940, position: "relative", overflow: "hidden" }}>
        {children}
      </div>
      {download}
    </div>
  );
}
export function DownloadToast({
  f,
  progress,
  complete,
  bytes,
}: {
  f: number;
  progress: number;
  complete: boolean;
  bytes: number;
}) {
  return (
    <div
      style={{
        position: "absolute",
        right: 24,
        top: 73,
        width: 368,
        padding: 21,
        borderRadius: 15,
        background: "#fffffff5",
        border: "1px solid #dce1d5",
        boxShadow: "0 20px 70px #1c2d2126,0 3px 10px #1c2d210b",
        zIndex: 20,
        transform: `translateY(${mix(f, 0, 18, -12, 0)}px)`,
        opacity: mix(f, 0, 12),
        color: "#253124",
      }}
    >
      <div style={{ display: "flex", gap: 15, alignItems: "center" }}>
        <div
          style={{
            width: 45,
            height: 45,
            borderRadius: 12,
            background: complete ? "#e7efdf" : "#f1f3ec",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            color: "#526c3d",
          }}
        >
          <Icon name={complete ? "check" : "down"} size={23} />
        </div>
        <div>
          <div style={{ fontSize: 16, fontWeight: 600 }}>
            {complete ? "idexal Os downloaded" : "Downloading idexal Os"}
          </div>
          <div style={{ fontSize: 12, color: "#8a9381", marginTop: 5 }}>
            Linux desktop app · {(bytes / 1e6).toFixed(1)} MB
          </div>
        </div>
      </div>
      <div
        style={{
          height: 3,
          borderRadius: 3,
          background: "#edf0e7",
          marginTop: 18,
          overflow: "hidden",
        }}
      >
        <div
          style={{
            height: 3,
            width: `${progress * 100}%`,
            background: "#74896b",
          }}
        />
      </div>
      {complete && (
        <div
          style={{
            marginTop: 16,
            padding: "12px 16px",
            borderRadius: 8,
            background: "#253322",
            color: "#f7faf2",
            fontSize: 13,
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
          }}
        >
          Open idexal Os
          <Icon name="arrow" size={15} />
        </div>
      )}
    </div>
  );
}
