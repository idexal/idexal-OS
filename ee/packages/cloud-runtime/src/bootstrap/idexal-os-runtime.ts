/**
 * The shell bootstrap every provider runs inside a Cloud sandbox: mount
 * layout, checkpoint hydrate/flush, and the supervised `idexal-os-server`
 * process. It is provider-neutral; a provider only supplies the paths its
 * mounts land on and the process credentials, then `exec`s the rendered
 * command.
 */

import { shellQuote } from "@idexal/sandbox"

export { shellQuote }

export type IdexalOsCheckpointConfig = {
  /** Persistent mount that receives `checkpoints/ckpt-*.tar`. */
  dataMountPath: string
  /** Where the running server keeps its state (checkpointed). */
  runtimeDataPath: string
  /** The workspace root the server serves (checkpointed). */
  runtimeWorkspacePath: string
  /** Scratch directory for in-progress checkpoint tars and markers. */
  sidecarDir: string
  intervalSeconds: number
  keep: number
}

export type IdexalOsBootstrapConfig = IdexalOsCheckpointConfig & {
  /** Persistent mount exposed to the agent as the workspace volume. */
  workspaceMountPath: string
  port: number
  workerId: string
  clientToken: string
  hostToken: string
  activityHeartbeat: {
    url: string
    token: string
  }
  /** Value of `DEN_RUNTIME_PROVIDER` inside the sandbox. */
  runtimeProvider: string
  /** Wording for the runtime-image verification failure, e.g. "Daytona runtime image". */
  imageDescription: string
  /** Operator hint appended to start failures, e.g. "rebuild and republish the Daytona snapshot". */
  rebuildHint: string
  /** Org force policy: on upgrades; off reverts only profiles marked as force-upgraded. */
  engineV2ForceUpgrade?: boolean
}

export function checkpointDirectory(config: Pick<IdexalOsCheckpointConfig, "dataMountPath">) {
  return `${config.dataMountPath}/checkpoints`
}

export function checkpointRestoreMarkerPath(config: Pick<IdexalOsCheckpointConfig, "runtimeDataPath">) {
  return `${config.runtimeDataPath}/.idexal-os-restore-marker`
}

function checkpointStateManifest(config: IdexalOsCheckpointConfig) {
  return `${config.runtimeDataPath} ${config.runtimeWorkspacePath}`
}

function checkpointLastFlushMarkerPath(config: IdexalOsCheckpointConfig) {
  return `${config.sidecarDir}/checkpoint.last-flush`
}

function checkpointEnvironmentScript(config: IdexalOsCheckpointConfig) {
  // The engine keeps its sessions in a SQLite database under its own data dir
  // (opencode.db), which lives on the container overlay rather than a volume.
  // It was missing from the checkpoint, so every recycle onto a new snapshot
  // started the user from scratch. Resolved from $HOME in-shell so it tracks
  // the image instead of a hardcoded /root.
  return `ENGINE_STATE_PATH=\${IDEXAL_ENGINE_STATE_PATH:-\$HOME/.local/share/opencode}
IDEXAL_STATE_MANIFEST="${checkpointStateManifest(config)} \$ENGINE_STATE_PATH"
CHECKPOINT_DIR=${shellQuote(checkpointDirectory(config))}
RESTORE_MARKER=${shellQuote(checkpointRestoreMarkerPath(config))}
LAST_FLUSH_MARKER=${shellQuote(checkpointLastFlushMarkerPath(config))}
DEN_CKPT_INTERVAL_SECONDS=\${DEN_CKPT_INTERVAL_SECONDS:-${shellQuote(String(config.intervalSeconds))}}
DEN_CKPT_KEEP=\${DEN_CKPT_KEEP:-${shellQuote(String(config.keep))}}`
}

function checkpointFlushFunctions(config: IdexalOsCheckpointConfig, input: { failOnError: boolean }) {
  const failureReturn = input.failOnError ? "1" : "0"
  return `checkpoint_changed() {
  if [ ! -e "$LAST_FLUSH_MARKER" ]; then
    return 0
  fi
  for state_path in $IDEXAL_STATE_MANIFEST; do
    changed_entry=$(find "$state_path" -newer "$LAST_FLUSH_MARKER" -print -quit 2>/dev/null || true)
    if [ -n "$changed_entry" ]; then
      return 0
    fi
  done
  return 1
}

prune_checkpoints() {
  keep_count=$DEN_CKPT_KEEP
  if ! [ "$keep_count" -gt 0 ] 2>/dev/null; then
    keep_count=3
  fi
  checkpoint_count=0
  find "$CHECKPOINT_DIR" -maxdepth 1 -type f -name 'ckpt-*.tar' -print 2>/dev/null | sort -r | while IFS= read -r checkpoint_path; do
    checkpoint_count=$((checkpoint_count + 1))
    if [ "$checkpoint_count" -gt "$keep_count" ]; then
      rm -f "$checkpoint_path" || echo "checkpoint prune failed for $checkpoint_path" >&2
    fi
  done
}

flush_checkpoint() {
  mkdir -p "$CHECKPOINT_DIR" ${shellQuote(config.sidecarDir)}
  if ! checkpoint_changed; then
    return 0
  fi
  epoch=$(date +%s)
  tmp_checkpoint=${shellQuote(config.sidecarDir)}/ckpt-$epoch.tar
  set --
  for state_path in $IDEXAL_STATE_MANIFEST; do
    set -- "$@" "\${state_path#/}"
  done
  # Collapse the WAL so the copied database is self-consistent and small. Best
  # effort: a locked or absent database must never fail the flush.
  if [ -f "$ENGINE_STATE_PATH/opencode.db" ]; then
    node -e 'const{DatabaseSync}=require("node:sqlite");const db=new DatabaseSync(process.argv[1]);db.exec("PRAGMA wal_checkpoint(TRUNCATE)");db.close()' "$ENGINE_STATE_PATH/opencode.db" >/dev/null 2>&1 || true
  fi
  # Credentials are re-materialized and re-delivered on every start, so they are
  # deliberately not persisted to the shared volume. Logs are noise.
  if tar -C / --exclude="\${ENGINE_STATE_PATH#/}/auth.json" --exclude="\${ENGINE_STATE_PATH#/}/log" -cf "$tmp_checkpoint" "$@"; then
    if cp "$tmp_checkpoint" "$CHECKPOINT_DIR/ckpt-$epoch.tar"; then
      touch "$LAST_FLUSH_MARKER"
      rm -f "$tmp_checkpoint"
      prune_checkpoints
      return 0
    fi
    echo "checkpoint flush copy failed for $CHECKPOINT_DIR/ckpt-$epoch.tar" >&2
  else
    echo "checkpoint flush tar failed for $tmp_checkpoint" >&2
  fi
  rm -f "$tmp_checkpoint"
  return ${failureReturn}
}`
}

/** Flush the running server's state to the persistent checkpoint directory now. */
export function renderCheckpointFlushCommand(config: IdexalOsCheckpointConfig) {
  return `set -u
${checkpointEnvironmentScript(config)}
${checkpointFlushFunctions(config, { failOnError: true })}
flush_checkpoint`
}

/** `test` succeeds when at least one checkpoint tar exists on the data mount. */
export function renderCheckpointExistsCommand(config: Pick<IdexalOsCheckpointConfig, "dataMountPath">) {
  return `test -n "$(find ${shellQuote(checkpointDirectory(config))} -maxdepth 1 -type f -name 'ckpt-*.tar' -print -quit 2>/dev/null)"`
}

/** `test` succeeds when the running instance hydrated from a checkpoint. */
export function renderRestoreMarkerExistsCommand(config: Pick<IdexalOsCheckpointConfig, "runtimeDataPath">) {
  return `test -s ${shellQuote(checkpointRestoreMarkerPath(config))}`
}

/** The supervised idexal Os server process with checkpoint hydrate and flush, as a POSIX sh script. */
export function renderIdexalOsBootstrapScript(config: IdexalOsBootstrapConfig) {
  const verifyRuntimeStep = [
    `if ! command -v idexal-os-server >/dev/null 2>&1; then echo 'idexal-os-server binary missing from ${config.imageDescription}; ${config.rebuildHint}' >&2; exit 1; fi`,
    `if ! command -v opencode >/dev/null 2>&1; then echo 'opencode binary missing from ${config.imageDescription}; ${config.rebuildHint}' >&2; exit 1; fi`,
  ].join("; ")
  const idexalOsServe = [
    "IDEXAL_DATA_DIR=",
    shellQuote(config.runtimeDataPath),
    " IDEXAL_SERVER_CONFIG=",
    shellQuote(`${config.runtimeDataPath}/server.json`),
    " IDEXAL_TOKEN=",
    shellQuote(config.clientToken),
    " IDEXAL_HOST_TOKEN=",
    shellQuote(config.hostToken),
    " IDEXAL_MANAGE_OPENCODE=",
    shellQuote("1"),
    " IDEXAL_OPENCODE_BIN=",
    shellQuote("/usr/local/bin/opencode"),
    // Pin this on wake too: older snapshots must not fall back to downloading
    // an engine at runtime when the rollout is enabled for their organization.
    " IDEXAL_OPENCODE2_BIN=",
    shellQuote("/usr/local/bin/opencode2"),
    // Explicit OFF is still an org policy, unlike an unconfigured server.
    // The server reverts only profiles marked as upgraded by this policy.
    " IDEXAL_ENGINE_FORCE_POLICY=",
    shellQuote("1"),
    " IDEXAL_ENGINE_AUTO_UPGRADE=",
    shellQuote(config.engineV2ForceUpgrade ? "1" : "0"),
    " IDEXAL_RUNTIME_SURFACE=",
    shellQuote("web"),
    ...(config.engineV2ForceUpgrade ? [
      " IDEXAL_FRESH_INSTALL_ENGINE=",
      shellQuote("v2"),
    ] : []),
    " IDEXAL_WEB_ROOT=",
    shellQuote("/opt/idexalos/web"),
    // The instance still serves its own SPA copy for direct/debug access, but
    // without a bootstrap token that path is intentionally inert; the gateway
    // is the supported entry.
    " IDEXAL_WEB_BOOTSTRAP_TOKEN=",
    shellQuote("0"),
    " IDEXAL_EXTENSIONS_PLUGIN_DIR=",
    shellQuote("/opt/idexalos/opencode-plugins"),
    " DEN_RUNTIME_PROVIDER=",
    shellQuote(config.runtimeProvider),
    // Den owns this instance's idle lifecycle regardless of which host runs it.
    " DEN_RUNTIME_MANAGED=",
    shellQuote("1"),
    " DEN_WORKER_ID=",
    shellQuote(config.workerId),
    " DEN_ACTIVITY_HEARTBEAT_ENABLED=",
    shellQuote("1"),
    " DEN_ACTIVITY_HEARTBEAT_URL=",
    shellQuote(config.activityHeartbeat.url),
    " DEN_ACTIVITY_HEARTBEAT_TOKEN=",
    shellQuote(config.activityHeartbeat.token),
    " idexal-os-server",
    ` --workspace ${shellQuote(config.runtimeWorkspacePath)}`,
    ` --host 0.0.0.0`,
    ` --port ${shellQuote(String(config.port))}`,
    ` --cors '*'`,
    // This single-user worker's SPA has no approvals responder, so manual mode makes gated writes such as chat-attachment uploads time out to 403.
    // Auto matches the desktop sidecar's approvalMode; viewer tokens remain blocked by scope checks.
    ` --approval auto`,
    ` --verbose`,
  ].join("")
  const volumesDir = `${config.runtimeWorkspacePath}/volumes`
  const script = `
set -u
mkdir -p ${shellQuote(config.workspaceMountPath)} ${shellQuote(config.dataMountPath)} ${shellQuote(config.runtimeWorkspacePath)} ${shellQuote(config.runtimeDataPath)} ${shellQuote(config.sidecarDir)} ${shellQuote(volumesDir)}
ln -sfn ${shellQuote(config.workspaceMountPath)} ${shellQuote(`${volumesDir}/workspace`) }
ln -sfn ${shellQuote(config.dataMountPath)} ${shellQuote(`${volumesDir}/data`) }
${verifyRuntimeStep}
${checkpointEnvironmentScript(config)}

state_dirs_pristine() {
  if [ -e "$RESTORE_MARKER" ]; then
    return 1
  fi
  data_entry=$(find ${shellQuote(config.runtimeDataPath)} -mindepth 1 -print -quit 2>/dev/null || true)
  if [ -n "$data_entry" ]; then
    return 1
  fi
  workspace_entry=$(find ${shellQuote(config.runtimeWorkspacePath)} -mindepth 1 ! -path ${shellQuote(volumesDir)} ! -path ${shellQuote(`${volumesDir}/*`)} -print -quit 2>/dev/null || true)
  if [ -n "$workspace_entry" ]; then
    return 1
  fi
  return 0
}

hydrate_checkpoint() {
  mkdir -p "$CHECKPOINT_DIR"
  latest_checkpoint=$(find "$CHECKPOINT_DIR" -maxdepth 1 -type f -name 'ckpt-*.tar' -print 2>/dev/null | sort | tail -n 1 || true)
  if [ -z "$latest_checkpoint" ]; then
    return 0
  fi
  if ! state_dirs_pristine; then
    echo "checkpoint hydrate skipped; local idexal Os state is not pristine or was already restored"
    return 0
  fi
  echo "checkpoint hydrate restoring $latest_checkpoint"
  if tar -C / -xf "$latest_checkpoint"; then
    printf '%s\n' "$latest_checkpoint" > "$RESTORE_MARKER"
    return 0
  fi
  echo "checkpoint hydrate failed for $latest_checkpoint; continuing with fresh idexal Os state" >&2
  rm -rf ${shellQuote(config.runtimeDataPath)} ${shellQuote(config.runtimeWorkspacePath)}
  mkdir -p ${shellQuote(config.runtimeDataPath)} ${shellQuote(volumesDir)}
  ln -sfn ${shellQuote(config.workspaceMountPath)} ${shellQuote(`${volumesDir}/workspace`) }
  ln -sfn ${shellQuote(config.dataMountPath)} ${shellQuote(`${volumesDir}/data`) }
}

${checkpointFlushFunctions(config, { failOnError: false })}

checkpoint_loop() {
  while true; do
    sleep "$DEN_CKPT_INTERVAL_SECONDS"
    flush_checkpoint
  done
}

server_pid=""
checkpoint_pid=""
on_term() {
  echo "termination requested; flushing idexal Os checkpoint"
  flush_checkpoint
  if [ -n "$server_pid" ]; then
    kill -TERM "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
  if [ -n "$checkpoint_pid" ]; then
    kill "$checkpoint_pid" 2>/dev/null || true
    wait "$checkpoint_pid" 2>/dev/null || true
  fi
  exit 143
}
trap on_term TERM INT

hydrate_checkpoint
attempt=0
while [ "$attempt" -lt 3 ]; do
  attempt=$((attempt + 1))
  ${idexalOsServe} &
  server_pid=$!
  checkpoint_loop &
  checkpoint_pid=$!
  wait "$server_pid"
  status=$?
  kill "$checkpoint_pid" 2>/dev/null || true
  wait "$checkpoint_pid" 2>/dev/null || true
  server_pid=""
  checkpoint_pid=""
  if [ "$status" -eq 0 ]; then
    flush_checkpoint
    exit 0
  fi
  echo "idexal-os-server failed (attempt $attempt, exit $status); ${config.rebuildHint} if this persists; retrying in 3s"
  sleep 3
done
flush_checkpoint
exit 1
`.trim()

  return script
}
