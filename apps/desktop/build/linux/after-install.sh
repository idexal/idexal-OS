#!/bin/sh
# Runs as root after a .deb/.rpm package is installed. Never fails the package
# transaction: a missing optional helper must not leave the package half
# configured.
set +e

# electron-builder installs the bundle under /opt. Keep Chromium's sandbox
# helper usable even when a previous package or an archive extraction lost the
# setuid bit.
for sandbox in /opt/*/chrome-sandbox /opt/*/*/chrome-sandbox; do
  [ -f "$sandbox" ] || continue
  case "$sandbox" in
    *idexal*) chown root:root "$sandbox" 2>/dev/null; chmod 4755 "$sandbox" 2>/dev/null ;;
  esac
done

if command -v update-desktop-database >/dev/null 2>&1; then
  update-desktop-database /usr/share/applications >/dev/null 2>&1
fi
if command -v update-mime-database >/dev/null 2>&1; then
  update-mime-database /usr/share/mime >/dev/null 2>&1
fi

exit 0
