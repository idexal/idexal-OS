# idexal Os — Platform

The web platform behind **idexal Os**: accounts, workspaces, team management,
billing, invoices, developer API keys, webhooks, an OpenAI/Anthropic-compatible
AI gateway, and the desktop sign-in handoff.

The desktop app itself is local-first and works without an account. This
platform is optional cloud infrastructure.

## Stack

- Laravel 13 / PHP 8.4
- Blade, Tailwind CSS 4, Alpine.js, Chart.js
- MySQL 8 / MariaDB 10.4
- Stripe Checkout, subscriptions, invoices, and webhooks
- mPDF for Arabic/RTL invoice PDFs
- Arabic-first UI with an English switcher

## Local development

```bash
cp .env.example .env
php artisan key:generate
composer install
npm ci
npm run build
php artisan migrate --seed
php artisan storage:link
php artisan serve --port=8000
```

Open `http://127.0.0.1:8000`.

The seeded administrator is `admin@idexal.com`; the password comes from
`ADMIN_PASSWORD` in `.env`, or is generated once by the seeder when that value
is empty. Change it after signing in.

## Main surfaces

| Path | Purpose |
| --- | --- |
| `/` | Public marketing site (Arabic/English) |
| `/pricing` | Plans and pricing |
| `/docs` | Gateway API documentation |
| `/dashboard` | Account, usage, API keys, team, billing, invoices, desktop devices |
| `/admin` | Platform administration |
| `/v1/*` | Desktop API and AI gateway |
| `/webhooks/stripe` | Stripe webhook receiver |

## Desktop sign-in contract

The desktop app opens:

```text
https://os.idexal.com/?mode=sign-in&desktopAuth=1&desktopScheme=idexalos
```

After the user signs in, authorizes a workspace, and clicks **Open idexal Os**,
the browser opens:

```text
idexalos://den-auth?grant=<single-use-grant>&denBaseUrl=https%3A%2F%2Fos.idexal.com
```

The app exchanges the grant at `POST /v1/auth/desktop-handoff/exchange`. Grants
expire after 10 minutes and are single-use.

## Tests

```bash
php artisan test
vendor/bin/pint --dirty
```

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for XAMPP setup, production upload, database
import, `.env`, Stripe, AI providers, cron/queues, domain/TLS, and desktop
sign-in verification.
