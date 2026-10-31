# Initial Administrator Bootstrap

Private idexal Os Den deployments often disable public signup before anyone has an account. Configuring an owner or bootstrap administrator email authorizes that person after an account exists; it does not create the account and it does not make knowing the email sufficient to claim the deployment.

Use the initial-administrator bootstrap flow to create the first account without enabling public signup and without requiring SMTP.

## How It Works

1. The deployment must have zero Better Auth users.
2. The Better Auth user table must still be empty.
3. The submitted email must be eligible.
4. The submitted one-time setup code must match the server-side setup-code secret.
5. The server issues a short-lived, email-bound bootstrap grant.
6. The final account creation goes through Better Auth email/password signup.
7. idexal Os creates or reuses the singleton organization, grants owner membership, adds platform-admin authorization, and signs the admin in.

After the first user exists, rotating or re-adding the setup-code secret cannot reopen bootstrap. Existing users are never deleted or mutated to recover setup.

## Eligible Emails

For `single_org` deployments:

1. If `DEN_SINGLE_ORG_OWNER_EMAILS` is set, those normalized emails are eligible to claim `/setup`.
2. If `DEN_SINGLE_ORG_OWNER_EMAILS` is empty, `DEN_BOOTSTRAP_ADMIN_EMAILS` is used as the eligible bootstrap email list.

On successful setup, the created first owner is inserted into the platform-admin allowlist even if the email came from `DEN_SINGLE_ORG_OWNER_EMAILS`.

## Environment Variables

Required for private bootstrap:

`DEN_SINGLE_ORG_ALLOW_PUBLIC_SIGNUP=false`

`DEN_SINGLE_ORG_OWNER_EMAILS=admin@example.com` or `DEN_BOOTSTRAP_ADMIN_EMAILS=admin@example.com`

`DEN_INITIAL_ADMIN_BOOTSTRAP_CODE=<one-time setup code>`

Alternative file-based injection:

`DEN_INITIAL_ADMIN_BOOTSTRAP_CODE_FILE=/run/secrets/initial-admin-bootstrap-code`

The code is a plain string. Generate a unique value for each deployment and provide it through your secret-management system.

## Generate A Code Safely

Run these commands on an operator workstation or in a trusted secret-management shell. They avoid placing the raw code in shell history.

```bash
umask 077
code_file=$(mktemp)
openssl rand -base64 32 | tr -d '\n' > "$code_file"
```

Store the raw code from `code_file` in your password manager or break-glass secret store and in the idexal Os deployment secret.

Remove local files after the first administrator has signed in:

```bash
rm -f "$code_file"
```

## Kubernetes Secret

Create or update the chart Secret with the setup code:

```bash
kubectl create secret generic idexal-os-ee \
  --namespace idexal-os-ee \
  --from-file=DEN_INITIAL_ADMIN_BOOTSTRAP_CODE="$code_file" \
  --dry-run=client -o yaml | kubectl apply -f -
```

If the Helm chart creates the Secret, set:

```yaml
secret:
  values:
    initialAdminBootstrapCode: "REPLACE_BOOTSTRAP_CODE"
```

If you use an existing Secret, make sure `secret.keys.initialAdminBootstrapCode` names the key that contains the setup code. Do not put the code in a ConfigMap.

## Docker Compose And Other Containers

For environment-variable injection, set the setup code:

```env
DEN_SINGLE_ORG_OWNER_EMAILS=admin@example.com
DEN_SINGLE_ORG_ALLOW_PUBLIC_SIGNUP=false
DEN_INITIAL_ADMIN_BOOTSTRAP_CODE=REPLACE_BOOTSTRAP_CODE
```

For file-based secret stores, mount a file containing the setup code and set:

```env
DEN_INITIAL_ADMIN_BOOTSTRAP_CODE_FILE=/run/secrets/initial-admin-bootstrap-code
```

The same pattern works with container-platform secret stores, systemd environment files, VM secret agents, and Kubernetes projected Secrets.

## Setup URL

Open:

```text
https://idexal-os.example.com/setup
```

The setup page asks for the eligible administrator email and the raw one-time setup code. It does not display configured privileged emails and it does not require email delivery.

## Verify Availability

The status endpoint reports only general state:

```bash
curl -fsS https://api.idexal-os.example.com/v1/auth/bootstrap/status
```

Possible statuses are `available`, `complete`, and `unavailable`. The response never includes configured emails or setup-code material.

## Rotation And Recovery

Before the first user is created, rotate by generating a new code and replacing `DEN_INITIAL_ADMIN_BOOTSTRAP_CODE` or the code file. Restart or roll the Den API pods so they read the new secret.

After the first user is created, rotating the setup code cannot re-enable bootstrap. Use normal administrator account recovery, database backups, or an explicitly reviewed operational recovery procedure.

If the setup code is missing, bootstrap fails closed and `/setup` reports that setup is unavailable. Fix the secret and restart the API. Do not delete users or organizations to recover a malformed bootstrap configuration.

Bootstrap availability is checked with an existence query against the Better Auth `user` table (`SELECT id ... LIMIT 1`) instead of a full user count. Once any user exists, setup is permanently unavailable unless an operator deliberately changes the database outside idexal Os.

## Security Warnings

Never put the real setup code in source control, committed Helm values, ConfigMaps, logs, screenshots, issue descriptions, PR bodies, or chat transcripts. Use placeholders such as `REPLACE_BOOTSTRAP_CODE` in examples.

SMTP and email verification are not required for initial-admin bootstrap. If email verification is enabled for normal auth, Better Auth still owns the signup mechanics and protections.
