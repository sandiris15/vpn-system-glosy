# Simple VPN NAT

Standalone, single-CHR helper for creating SSTP PPP Secrets and optional DST-NAT rules. It uses its own `.env`, admin login, Composer dependencies, and session storage; it does not read the parent Laravel app's database or credentials.

## Features

- Separate admin login using a password hash from this app's `.env`.
- Allocates a free remote address from the configured RouterOS IP pool once and saves it directly on the PPP Secret, so it remains stable across reconnects.
- Saves the configured static PPP local address directly on each PPP Secret.
- Automatically allocates an unused public port from the configured range.
- Supports Winbox, MikroTik API, WebFig, and custom internal ports; standard-service comments are generated from the username, while custom ports require a manual comment.
- Rejects an existing PPP username and occupied ports.
- Creates the PPP Secret and optional DST-NAT rule on one configured CHR.
- Lists PPP Secrets and provides copyable DST-NAT access details.
- Previews a client-side SSTP and Speedtest-routing script without asking for a new password. It uses the existing PPP Secret password when the RouterOS API user can read it; otherwise it shows a placeholder. The app does not change or store the password.
- Rolls back the new Secret if RouterOS rejects or cannot confirm the NAT rule.
- Exports the DST-NAT command as a `.rsc` file. The export does not contain the PPP password.
- Does not configure or replicate to backup CHRs.

## Install

Requirements: PHP 8.3+, PHP cURL, and Composer.

```sh
cd simple-vpn-app
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Generate an admin password hash, then put the output in single quotes in `APP_ADMIN_PASSWORD_HASH` in this app's `.env`:

```sh
php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT), PHP_EOL;'
```

Set the CHR API user, password, host, port, PPP profile, IP pool, static `ROUTEROS_LOCAL_ADDRESS`, public IP, and allowed port range in `.env`. Choose a local address valid for the SSTP network and keep it outside the client address range. Use a RouterOS API account with only the permissions required to read PPP/IP configuration and add/delete PPP Secret and NAT entries. Keep `.env` out of Git.

The app validates the RouterOS TLS certificate. If the CHR uses a private CA, set `ROUTEROS_CA_BUNDLE` to its PEM file; do not disable TLS verification.

The generated client script follows the requested RouterOS v7 Speedtest routing setup. It disables SSTP server-certificate validation as requested, so only use it on a trusted network. If the RouterOS API user can read the PPP Secret password, the preview contains it in plain text.

## Run locally

From `simple-vpn-app/`:

```sh
php -S 127.0.0.1:8001 -t public
```

Open `http://127.0.0.1:8001`. For Ubuntu deployment, point the web server document root at this folder's `public/` directory, serve it over HTTPS, and set `SESSION_SECURE_COOKIE=true`.

The RouterOS endpoint must be reachable from the Ubuntu host. This app deliberately targets one CHR only; it does not provide cluster replication or failover.
