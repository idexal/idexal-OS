# Managed deployments: customer-cloud installers

idexal Os installed in a customer's own cloud account, launched and monitored
from hosted idexal Os. AWS uses CloudFormation/CodeBuild; GCP adds a
customer-approved Cloud Shell installer and Cloud Run platform behind the
default-off `managedDeploymentsGcp` rollout. Azure remains unsupported.
GCP offline validation is not a live-install or publication receipt; do not
roll it out before those gates pass.

```
contract/            Provider-neutral Terraform contract (inputs, naming, outputs)
aws/
  bootstrap/         CloudFormation the customer approves: runner + state only
  runner/            Installer run inside the customer's account (CodeBuild)
  health/            Read-only health agent (Lambda) reporting to idexal Os
  terraform/         AWS root: contract + network + platform + health agent
    modules/network/       AWS "network" building block
    modules/health-agent/  AWS "health agent" building block
    modules/idexal-os-aws-ecs/  Frozen pre-contract AWS platform
gcp/
  bootstrap/         Customer approval tutorial (immutable GCS artifact)
  runner/            Cloud Shell bootstrap and impersonated Terraform runner
  health/            Read-only Cloud Run health job
  terraform/         Cloud Run, Cloud SQL, HTTPS load balancer, DNS and secrets
  README.md          GCP permission, identity, state and rollout gates
tools/               Release build and publication (provider argument)
tests/               Installer, runner and health agent tests
```

The managed AWS platform is a frozen pre-contract copy at
`aws/terraform/modules/idexal-os-aws-ecs`. It is intentionally independent of
`infra/terraform/modules/idexal-os-aws-ecs`, which evolves for direct Terraform
users. Managed installs retain the current dev implementation, including the
runner behavior updates, without adopting the direct module's contract lifecycle.

## Frozen AWS platform boundary

The baseline is the shared module at source commit
`8b586b13ab89b78379fbecd8ddb9c7c7caba31f8`, Git tree
`561d5b63514a13df471ad7458eae2b620cfc5803`, including current runner behavior.
The TLS/ordering exception changes `main.tf` (preserved initial secret version),
`ecs.tf` (CA startup, retained definition addresses and steady-state barrier),
`variables.tf` (strict TLS default/validation), `versions.tf` (Terraform >=1.7
for removed blocks, plus HTTP provider), and adds `tls.tf` (regional CA trust,
runtime configuration marker, strict secret promotion and final deployment).
The other fourteen original module files remain byte-identical. The builder
packages eleven operational Terraform files plus the existing runner, excluding
examples/tests, the direct module and metadata sidecars. Regressions reconstruct
the exact baseline tree outside these five files and compare actual archive
members/checksums. GCP image/registry verification remains independent.

The original AWS5.100/random3.7.2/archive2.8.1 pins and bootstrap template/IAM
are unchanged. HTTP3.6.2 is added for verified public CA retrieval. This does not
adopt direct-user migration orchestration or write-only secret mode; the legacy
migration sidecar remains. No authentication/encryption/bootstrap key rotation
is intended. Strict-version creation for an existing install must pass the same
known-current-version/key/DB-identity comparison; unknowns or rotated fields fail
closed. Fresh creation is allowed only when both the parent secret and its initial
version are new. Protected data-resource deletes/replacements remain guarded.

### One-apply TLS ordering and revision retention

1. Preserve the original `app` SecretVersion. Fresh installs first create that
   version with the initial accept-mode URL; its secret values are then ignored
   for changes, rather than rotated or downgraded on later updates.
2. Forget the old `aws_ecs_task_definition.api` / `.web` addresses using
   `removed { lifecycle { destroy = false } }`. Create `api_ca` / `web_ca` with
   `skip_destroy = true` from their first creation. Both services must reach
   steady state on those CA-enabled revisions before the strict version exists.
3. `terraform_data.ca_ready` uses existing ECS Describe/wait permissions to
   confirm a single completed primary deployment on each expected CA revision,
   not running counts alone. The strict SecretVersion depends on this barrier.
   Existing keys and DB identity/credentials are unchanged; only URL mode changes.
4. `terraform_data.strict_redeploy`, triggered by that version ID, calls the
   packaged runner's `tls-redeploy` command. It uses only existing ECS UpdateService
   and Describe permissions, forces both services and waits for completed
   deployments on the expected CA revision. Any failed waiter/rollback/overlap
   fails apply and the installer run, even if old replicas are still healthy.

The plan graph enforces initial secret -> completed CA services -> strict
secret -> forced strict deployment. Before promotion, failures leave accept
mode; after promotion every installer/ECS automatic restart or circuit-breaker
rollback target is CA-enabled. This is not an atomic multi-service transaction:
a failed post-step is recorded as failed/tainted and retried, never reported as
Operational, while all restartable managed service definitions remain CA-safe.

New managed API/web definitions retain revisions on **future** image/version
updates too, fixing that general deregistration bug, not just this TLS update.
The installer never deregisters them. **Retained pre-TLS task-definition revisions
stay ACTIVE because the approved permissions cannot deregister them; don't start
them manually after the upgrade.** Arbitrary manual legacy RunTask is outside
the automatic-path guarantee. Independent lab cleanup may deregister owned
revisions only after services are destroyed; it is not a rollout workaround.

Each migrate/API/web startup validates the actual URI mode and parses the public
CA without printing credentials, then emits `IDEXAL_MANAGED_DB_TLS` with
`mode=bootstrap-accept ca=validated` or `mode=strict ca=validated`. This is a
configuration witness, not a pre-connection handshake claim. Only final strict
markers, actual new tasks and DB/readiness/session witnesses certify the upgrade.

### Controlled second-update proof input

`tls_task_revision` is an optional non-secret root/module input, empty by default.
It only changes `IDEXAL_MANAGED_TASK_REVISION` in DB-client container definitions.
For a lab-only second immutable bundle, copy
`tests/second-update_override.tf` into `aws/terraform/` under that same name before
running the ordinary release builder. It sets the default to `second-update`;
no image/version/database/credential/buildspec/permission override is involved.
Record the head, this single fixture delta and new artifact checksums, obtain the
normal run approval, and use the ordinary runner. Never ship the fixture in a
production release or use an administrator ECS mutation as the second-update proof.
Live preservation, second-update and cleanup results remain separate from offline
mocked/targeted tests.

Do not refresh the snapshot as part of unrelated work. Separate contract
adoption requires owner review and must handle supporting-file packaging, deployment-scoped
Lambda/IAM/log permissions and customer re-approval, legacy maintenance/drain
fencing, and update/version semantics.

## The contract every cloud implements

1. **Bootstrap** the customer approves in their own console. It creates only an
   installer identity, a runner and encrypted, retained Terraform state. AWS:
   CloudFormation quick-create. Azure: a "Deploy to Azure" ARM/Bicep template.
   GCP: Infrastructure Manager or a Cloud Shell tutorial.
2. **Runner** that enrolls with a cloud identity proof, verifies the release
   checksum, runs `terraform plan` + `apply` against the cloud root, refuses to
   delete data-bearing resources, verifies services and HTTPS health, and reports
   the six milestones (`runner_connected` … `health_verified`).
3. **Terraform root** that calls `contract/` with the neutral inputs
   (`deployment_id`, `domain_name`, `owner_email`, `idexal_os_version`,
   `control_plane_origin`, `size`), adds its own cloud inputs, and returns the
   neutral outputs (`web_url`, `api_url`, `setup_url`, `health_agent`). It
   composes the same building blocks: network, platform (containers, database,
   load balancer, certificate, DNS, secrets) and health agent.
4. **Health agent** that runs every 5 minutes with read-only permissions and
   posts the same allowlisted report (`packages/managed-deployments/src/schema.ts`,
   `healthReportSchema`) to `/v1/managed-deployments/:id/heartbeat`.
5. **Identity proof** the control plane can verify without stored secrets:
   AWS replays a signed STS `GetCallerIdentity`; Azure would verify a managed
   identity token and GCP a service-account ID token against their issuers.

Cloud-specific values (AWS account and Route 53 zone; later Azure subscription
or GCP project ID/number, region and DNS zone) live in the deployment's `target`,
never in the contract. API deployment views are discriminated by provider, so an
AWS target cannot be read as a GCP project. Both use the same ordered milestones,
allowlisted reports and customer-approved updates. GCP ID tokens are verified
against fixed Google JWKS, the exact service-account email, fresh issue time,
and an audience binding the enrollment challenge or exact heartbeat body hash.
Reports keep working when a feature is killed; launches fail closed.

## Security model

- A dedicated cloud account per deployment (default), or the customer's
  existing network: the AWS target's `network` is `dedicated` (own VPC and ECS
  cluster) or `existing` (customer VPC, subnets and optional ECS cluster). In
  an existing network the runner's permissions are limited to resources named
  with, or tagged at creation for, its deployment, and it checks the network
  (subnets, routes, DNS, cluster) before changing anything. No customer cloud
  keys are stored by idexal Os and no inbound access is needed: the runner and
  the agent call out.
- Release bundles and the Terraform binary are verified by SHA-256 before
  execution; published files are referenced by immutable S3 object versions.
  Terraform and provider versions are pinned with a committed lock file.
- State is customer-owned: versioned, encrypted S3 with S3-native locking,
  retained if the bootstrap stack is deleted.
- Only this stack's CodeBuild project can assume the runner role, the launcher
  cannot override the buildspec, image or privileged mode, and the runner cannot
  start or edit builds. In the dedicated account, grant `codebuild:StartBuild`
  on the installer project only to administrators.
- The runner role has no delete permissions except Terraform's own lock file, a
  certificate that failed validation (so a retry can request a new one), and
  superseded ECS task definitions. Plans that delete or replace databases,
  secrets, storage or the VPC are refused.
- Health reports contain only check ids, states, numbers and codes. No logs,
  hostnames, secrets or customer data leave the account.

## Run the checks

```bash
(cd contract && terraform init -backend=false && terraform test)
(cd aws/terraform && terraform init -backend=false -lockfile=readonly && terraform validate && terraform test)
(cd gcp/terraform && terraform init -backend=false -lockfile=readonly && terraform validate && terraform test)
node aws/bootstrap/template.mjs            # regenerates aws/bootstrap/cloudformation.json
python3 -m unittest discover -s tests       # needs boto3
```

Release and operator steps: `docs/managed-deployments.md`.
