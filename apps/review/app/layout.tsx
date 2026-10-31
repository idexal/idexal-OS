import type { ReactNode } from "react";
import "./style.css";

export const metadata = {
  title: "idexal Os Review",
  robots: { index: false, follow: false },
};

export default function Layout({ children }: { children: ReactNode }) {
  return (
    <html lang="en">
      <body>
        <header className="masthead">
          <a href="/">
            idexalos<span>/ review</span>
          </a>
        </header>
        {children}
      </body>
    </html>
  );
}
