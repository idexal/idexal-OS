export const dynamic = "force-static"

const linkset = {
  linkset: [
    {
      anchor: "https://api.idexal.com",
      "service-desc": [
        {
          href: "https://api.idexal.com/openapi.json",
          type: "application/vnd.oai.openapi+json;version=3.1",
          title: "idexal Os Den API — OpenAPI 3.1 document",
        },
      ],
      "service-doc": [
        {
          href: "https://os.idexal.com/docs/api-reference",
          type: "text/html",
          title: "idexal Os Den API — human documentation",
        },
      ],
      status: [
        {
          href: "https://api.idexal.com/health",
          type: "application/json",
          title: "idexal Os Den API — health endpoint",
        },
      ],
      "service-meta": [
        {
          href: "https://os.idexal.com/llms.txt",
          type: "text/plain",
          title: "idexal Os llms.txt — agent-facing site guide",
        },
      ],
    },
  ],
}

export function GET() {
  return new Response(JSON.stringify(linkset, null, 2), {
    status: 200,
    headers: {
      "Content-Type": "application/linkset+json",
      "Cache-Control": "public, max-age=3600",
      "Access-Control-Allow-Origin": "*",
    },
  })
}
