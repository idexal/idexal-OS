# Self-Hosting

The desktop consumes a *platform surface*: the inference gateway, entitlements,
the extension catalog and the admin console. This page is about running that
surface yourself.

## What you need

| Requirement | Notes |
| --- | --- |
| A PHP 8.4 host | The platform application, with a database |
| A database | MySQL/MariaDB or equivalent |
| TLS | The desktop requires HTTPS in production |
| A desktop build | Pointed at your base URL |

## Steps

1. **Deploy the application** and run its migrations.
2. **Configure the gateway** and add at least one provider with a working key.
3. **Create an organisation and a member**, then sign in from the desktop.
4. **Confirm health**:

   ```bash
   curl -fsS https://your-host/up
   curl -fsS https://your-host/v1/app-version
   ```

5. **Confirm the inventory revision responds** to an authenticated request:

   ```bash
   curl -fsS -H "Authorization: Bearer $TOKEN" \
     https://your-host/v1/org/inventory-revision
   ```

## Desktop configuration

Point the app at your base URL and sign in. The upstream provider keys stay on
your server; the desktop receives a credential scoped to the account and
organisation.

## Operating it

- **Publish releases** by placing installers and the updater manifest where the
  desktop expects them; see [Release Process](./release-process.md).
- **Watch the revision** after an admin change: it must move. If it does not,
  the write path is silent and that is a bug.
- **Back up the database.** Provider keys and entitlements live there.

## Common failure modes

| Symptom | Likely cause |
| --- | --- |
| Desktop signs in but the picker is empty | No provider/key configured, or entitlements not assigned |
| Admin change does not reach the desktop | Sync sequence not advancing; check the cache store |
| `/v1/org/inventory-revision` returns 401 | Missing or expired desktop credential |
| Updater sees no new version | Manifest missing, or its version not newer than installed |

## Where to get help

Open a **Q&A** thread with your host, PHP version, and the step that failed.
Never paste secrets or connection strings.
