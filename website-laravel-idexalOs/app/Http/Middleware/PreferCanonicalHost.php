<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The platform answers on a canonical host plus any additional hostnames that
 * were added to the same installation. Only the canonical host is official:
 * browser surfaces are redirected there so sessions, generated links and the
 * desktop handoff all resolve to one origin. Machine API paths keep working on
 * every host, so a client can be pointed at an additional host on purpose.
 */
class PreferCanonicalHost
{
    /**
     * Path prefixes that stay on the host they were requested from.
     *
     * @var list<string>
     */
    private const API_PREFIXES = ['v1', 'api', 'webhooks', 'up'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldRedirect($request)) {
            return $next($request);
        }

        // 301 tells crawlers and browsers the canonical host is permanent;
        // anything that carries a body keeps a temporary redirect so a client
        // never caches a method-changing hop.
        $status = $request->isMethodSafe() ? 301 : 302;

        return redirect()->to($this->canonicalUrl($request), $status);
    }

    private function shouldRedirect(Request $request): bool
    {
        $canonical = $this->normalize(config('idexal.domains.canonical'));

        if ($canonical === '' || $this->normalize($request->getHost()) === $canonical) {
            return false;
        }

        if (! in_array($this->normalize($request->getHost()), $this->additionalHosts(), true)) {
            return false;
        }

        foreach (self::API_PREFIXES as $prefix) {
            if ($request->is($prefix, $prefix.'/*')) {
                return false;
            }
        }

        return true;
    }

    private function canonicalUrl(Request $request): string
    {
        // The canonical brand host is HTTPS-only (HSTS plus a server-side
        // redirect), so a hop from an additional host always lands on HTTPS.
        return 'https://'.config('idexal.domains.canonical').$request->getRequestUri();
    }

    /**
     * @return list<string>
     */
    private function additionalHosts(): array
    {
        $hosts = (array) config('idexal.domains.additional', []);

        return array_values(array_filter(array_map(
            fn (mixed $host): string => $this->normalize($host),
            $hosts,
        )));
    }

    private function normalize(mixed $host): string
    {
        return strtolower(trim((string) $host));
    }
}
