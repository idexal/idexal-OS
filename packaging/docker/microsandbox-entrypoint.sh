#!/usr/bin/env sh
set -eu

IDEXAL_WORKSPACE="${IDEXAL_WORKSPACE:-/workspace}"
IDEXAL_DATA_DIR="${IDEXAL_DATA_DIR:-/data/idexal-os-server}"
IDEXAL_SIDECAR_DIR="${IDEXAL_SIDECAR_DIR:-/data/sidecars}"
IDEXAL_PORT="${IDEXAL_PORT:-8787}"
IDEXAL_TOKEN="${IDEXAL_TOKEN:-microsandbox-token}"
IDEXAL_HOST_TOKEN="${IDEXAL_HOST_TOKEN:-microsandbox-host-token}"
IDEXAL_APPROVAL_MODE="${IDEXAL_APPROVAL_MODE:-auto}"
IDEXAL_CORS_ORIGINS="${IDEXAL_CORS_ORIGINS:-*}"
IDEXAL_CONNECT_HOST="${IDEXAL_CONNECT_HOST:-127.0.0.1}"
IDEXAL_EXTENSIONS_PLUGIN_DIR="${IDEXAL_EXTENSIONS_PLUGIN_DIR:-/opt/idexalos/opencode-plugins}"
HOME="${HOME:-/root}"
USER="${USER:-root}"
SHELL="${SHELL:-/bin/sh}"
XDG_CONFIG_HOME="${XDG_CONFIG_HOME:-$HOME/.config}"
XDG_CACHE_HOME="${XDG_CACHE_HOME:-$HOME/.cache}"
XDG_DATA_HOME="${XDG_DATA_HOME:-$HOME/.local/share}"
XDG_STATE_HOME="${XDG_STATE_HOME:-$HOME/.local/state}"

if [ "$HOME" = "/" ]; then
  HOME=/root
  XDG_CONFIG_HOME="$HOME/.config"
  XDG_CACHE_HOME="$HOME/.cache"
  XDG_DATA_HOME="$HOME/.local/share"
  XDG_STATE_HOME="$HOME/.local/state"
fi

export HOME USER SHELL XDG_CONFIG_HOME XDG_CACHE_HOME XDG_DATA_HOME XDG_STATE_HOME
export IDEXAL_DATA_DIR IDEXAL_TOKEN IDEXAL_HOST_TOKEN IDEXAL_EXTENSIONS_PLUGIN_DIR
export IDEXAL_MANAGE_OPENCODE=1
export IDEXAL_OPENCODE_BIN=/usr/local/bin/opencode

mkdir -p "$IDEXAL_WORKSPACE" "$IDEXAL_DATA_DIR" "$IDEXAL_SIDECAR_DIR"
mkdir -p "$HOME" "$XDG_CONFIG_HOME" "$XDG_CACHE_HOME" "$XDG_DATA_HOME" "$XDG_STATE_HOME"

printf '%s\n' "Starting idexal Os micro-sandbox"
printf '%s\n' "- workspace: $IDEXAL_WORKSPACE"
printf '%s\n' "- home: $HOME"
printf '%s\n' "- idexalos url: http://$IDEXAL_CONNECT_HOST:$IDEXAL_PORT"
printf '%s\n' "- client token: $IDEXAL_TOKEN"
printf '%s\n' "- host token: $IDEXAL_HOST_TOKEN"
printf '%s\n' "- health: curl http://$IDEXAL_CONNECT_HOST:$IDEXAL_PORT/health"
printf '%s\n' "- auth test: curl -H \"Authorization: Bearer $IDEXAL_TOKEN\" http://$IDEXAL_CONNECT_HOST:$IDEXAL_PORT/workspaces"

exec idexal-os-server \
  --workspace "$IDEXAL_WORKSPACE" \
  --host 0.0.0.0 \
  --port "$IDEXAL_PORT" \
  --token "$IDEXAL_TOKEN" \
  --host-token "$IDEXAL_HOST_TOKEN" \
  --approval "$IDEXAL_APPROVAL_MODE" \
  --cors "$IDEXAL_CORS_ORIGINS" \
  --verbose
