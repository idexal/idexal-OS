#!/bin/sh
# Refresh desktop/MIME caches after removal. User workspaces and app data are
# intentionally untouched.
set +e

if command -v update-desktop-database >/dev/null 2>&1; then
  update-desktop-database /usr/share/applications >/dev/null 2>&1
fi
if command -v update-mime-database >/dev/null 2>&1; then
  update-mime-database /usr/share/mime >/dev/null 2>&1
fi

exit 0
