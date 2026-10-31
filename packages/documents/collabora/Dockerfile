# Supply a pinned, unbranded engine image (upstream nobrand=yes or a source build).
# There is deliberately no vendor binary-image default; see ../THIRD_PARTY_NOTICES.md.
# Build the tiny TypeScript bridge before building this image:
# pnpm --filter @idexal/documents build:collabora-theme
ARG DOCUMENTS_EDITOR_BASE_IMAGE
FROM ${DOCUMENTS_EDITOR_BASE_IMAGE}
USER root
COPY dist/branding.js /tmp/idexal-os-branding.js
COPY branding.css /tmp/idexal-os-branding.css
# Preserve the engine's notices, About screen, and original branding source.
# Content-address our assets: the engine otherwise caches branding under its own version.
RUN command -v dpkg-query >/dev/null \
    && for package in code-brand collabora-online-brand; do \
      case "$(dpkg-query -W -f='${db:Status-Status}' "$package" 2>/dev/null)" in \
        installed) echo "Use an open-source, unbranded engine base; $package is proprietary." >&2; exit 1 ;; \
      esac; \
    done \
    && theme_version="$(cat /tmp/idexal-os-branding.js /tmp/idexal-os-branding.css | sha256sum | cut -c1-12)" \
    && mv /tmp/idexal-os-branding.js /usr/share/coolwsd/browser/dist/idexal-os-$theme_version.js \
    && mv /tmp/idexal-os-branding.css /usr/share/coolwsd/browser/dist/idexal-os-$theme_version.css \
    && sed -i -e "s|<!--%BRANDING_JS%-->|<!--%BRANDING_JS%--><script src=\"%SERVICE_ROOT%/browser/%VERSION%/idexal-os-$theme_version.js\"></script>|" \
        -e "s|<!--%BRANDING_CSS%-->|<!--%BRANDING_CSS%--><link rel=\"stylesheet\" href=\"%SERVICE_ROOT%/browser/%VERSION%/idexal-os-$theme_version.css\" />|" \
        /usr/share/coolwsd/browser/dist/cool.html \
    && test -s /usr/share/coolwsd/browser/dist/idexal-os-$theme_version.js
USER cool
