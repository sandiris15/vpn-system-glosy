# RUANG NOC NAT WAIPI

This repository contains the Blade UI and a Laravel 13 application foundation. Managed login, ordered RouterOS cluster sessions, account operations, Mandiri mode, display preferences, audit logging, and the policy-routing Script Generator are implemented. **It is not ready for production until it is deployed behind HTTPS and validated against the actual Ubuntu host and RouterOS devices.**

The target runtime is PHP 8.3 or newer. Laravel 11 is no longer receiving security fixes. Ubuntu 22.04 can run this stack with PHP 8.3 installed from a maintained package source or container; for a new server, Ubuntu 24.04 LTS is the simpler starting point.

## Files

- `resources/views/` — Blade pages for Managed login/dashboard/accounts/preferences and Mandiri connection/provisioning.
- `resources/css/app.css` — Tailwind directives and the pink/purple desktop glass theme.
- `resources/js/app.js` — Alpine.js entry point.
- `package.json`, `package-lock.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js` — frontend build configuration for a Laravel app using Vite.
- `composer.json`, `composer.lock`, `artisan`, `bootstrap/`, `config/`, `public/`, and `routes/` — Laravel 13 application foundation.

## Preview locally

Open `preview.html` in a browser to explore the UI. On macOS, from this folder, run `open preview.html#mandiri` to open the Mandiri connection screen directly. The preview is a client-side simulation: it does not connect to a router or persist credentials.

## Install dependencies

On a PHP 8.3+ host with Composer, Node.js, and npm installed:

```sh
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm install
npm run build
```

For local development, install the development dependencies with `composer install`, then run the Laravel server and `npm run dev` in separate terminals. Do not copy production secrets into this repository.

Do not expose login or router credentials over plain HTTP. Obtain a domain and configure HTTPS before using the application. Keep `APP_DEBUG=false` and `SESSION_SECURE_COOKIE=true` in production.

## Tests

Run the Laravel feature and unit tests with:

```sh
composer test
```

## Initial managed setup

Before seeding, set the database values and the `INITIAL_ADMIN_*` and `INITIAL_SERVER_*` values in the untracked `.env`. The initial admin password must be at least 16 characters. The initial server host must be a hostname or IP without a scheme or path; configure its RouterOS HTTPS REST port, PPP profile, and IP pool. `VPN_PRIMARY_ONLY=true` is the default and keeps VPN account changes on the primary CHR. `VPN_LOCAL_ADDRESS` defaults to the fixed PPP local address `10.20.188.1`; set it to the static local address used by the VPN. Set `INITIAL_SERVER_PUBLIC_IP` and both port-range values when managed DST-NAT is needed. Optional `CLUSTER_SERVER_2_*` and `CLUSTER_SERVER_3_*` values are for a later cluster rollout.

To add the username-only VPN operator, set `OPERATOR_PASSWORD` in the untracked `.env`, then run `php artisan db:seed --class=OperatorAccountSeeder`. The seeder creates the configured username (default `operator`), copies active CHR assignments from the first admin, and does not overwrite an existing operator password. Operators can view accounts, add accounts, and generate scripts, but cannot edit or delete VPN accounts. Existing users can continue signing in with email.

RouterOS REST API TLS verification is always enabled. If a router does not serve its complete certificate chain, set `ROUTEROS_CA_BUNDLE` to a PEM bundle containing the required trusted CA certificates; prefer fixing the router's served chain when possible. This CA setting does not control SSTP certificate verification in the generated script.

Then run:

```sh
php artisan migrate --seed
```

The RouterOS password is encrypted before it is stored in the database. After seeding, remove the `INITIAL_ADMIN_PASSWORD` and `INITIAL_SERVER_PASSWORD` values from `.env`; do not commit `.env` or paste those credentials into chat.

After adding or changing backup-node settings in `.env`, run `php artisan db:seed --class=ClusterServerSeeder --force`. This links the configured nodes to the same users and copies the primary node's stored RouterOS credentials without needing to put them back into `.env`. Cluster account replication is disabled by default; only set `VPN_PRIMARY_ONLY=false` after all nodes and their TLS/API configuration are verified. In that mode, `php artisan vpn:sync-cluster` copies only legacy marker-managed accounts and refuses username, address, password, pool, profile, or NAT conflicts.

Generated SSTP scripts use the configured hostname and TCP/443 endpoint. SSTP certificate validation is disabled as requested, so confirm the endpoint is trusted before using the script.

## Views and expected data

- `auth.login`: no server selection; the application picks the assigned cluster's lowest-priority node as primary.
- `managed.dashboard`: `$selectedServer`, `$stats`, `$recentAccounts`, optional `$activityLogs` and `$forumTopics` (activity data must never include passwords or generated credentials).
- `managed.workspaces.index`: active workspaces and planned tool workspaces.
- `managed.workspaces.hotspot-template`: local preview templates; customization/export is not yet available.
- `managed.forum.index` and `managed.forum.show`: authenticated discussion topics, replies, and admin moderation.
- `managed.vpn.index`: `$accounts` (already scoped to the selected CHR). Each account has `id`, `username`, `remote_address`, `is_managed`, `is_online`, `uptime`, and `nat_rules`; each NAT rule includes `protocol`, `public_port`, and `internal_port`. Secret search uses the `search` query parameter and DST-NAT search filters the rules on the current page. Only accounts created by this application can be changed or deleted.
- `managed.vpn.create`: `$selectedServer`
- `managed.vpn.edit`: `$account`, `$selectedServer`
- `managed.vpn.show`: `$account`, `$selectedServer`, optional `$generatedScript` and `$scriptMode` (`vpn` or `speedtest`)
- `mandiri.provision`: optional `$routerHost`, `$generatedScript`
- `preferences.index`: `$wallpapers`, `$preference`

Views render empty states when list data is not provided. Account and server fields are read using Laravel's `data_get()` helper.

## Backend status

Login verifies the user's server assignment, accepts either username or email, regenerates the session, and binds the selected CHR to it; logout invalidates that session. Managed account provisioning validates the configured PPP profile and pool, allocates and saves a static remote IPv4 address, uses the configured static PPP local address, automatically allocates a free public port, verifies created RouterOS resources, and attempts a rollback if a later operation fails. New single-CHR PPP Secrets have no comment; the application records ownership and associated NAT rule IDs in `managed_vpn_accounts`. Legacy marker-based accounts remain manageable. Provisioning is serialized per CHR. External SSTP secrets can generate client scripts but cannot be edited or deleted; operator users also cannot edit or delete VPN accounts.

Mandiri credentials are kept in the encrypted Laravel session for 15 minutes, cleared on logout/expiry, and never written to the application database. TLS verification is mandatory. Mandiri hostnames must resolve only to public IPs, and the selected address is pinned for the session to reduce DNS-rebinding/SSRF risk. Connection and provisioning endpoints are rate-limited.

Wallpaper uploads are limited to JPEG/PNG images up to 5 MB and 3840×2160, stored on the private local disk, and served only to the owning authenticated user with no-store headers. Preset wallpapers are static local assets.

Audit events are recorded for successful login, managed account creation, password changes, deletion, and script generation. Login metadata includes the request IP and reported user-agent; audit metadata excludes passwords, router credentials, and generated scripts. Recent events appear on the dashboard for the selected CHR.
Audit events have no automatic retention job; define a retention period and include this table in backups according to your operational requirements.

The discussion forum is limited to authenticated application users. Username/email login is active; Facebook and Telegram sign-in are not enabled until provider credentials and account-linking rules are configured.

Managed login has no per-router selector. VPN operations use only the assigned primary CHR while `VPN_PRIMARY_ONLY=true`; changes are not sent to backups. Cluster replication can be enabled later by explicitly setting `VPN_PRIMARY_ONLY=false` after the backup nodes are validated. The dashboard and account pages do not render PPP passwords.

The Script Generator offers an SSTP VPN-only script or an SSTP client with idempotent RouterOS v7 address-list, mangle, route, and source-NAT rules for the provided Speedtest destinations. The full PPP username and existing Secret password are used; the script preview is returned privately with no-store headers. `verify-server-certificate=no` is intentional per the requested SSTP configuration and disables server identity validation; use only with a trusted endpoint. Review the script and test it on a non-production router first because automated tests cannot validate RouterOS script semantics. Configure `IP-Lokal` with all internal subnets before relying on routing behavior.

## Ubuntu 22.04 deployment outline

Laravel 13 requires PHP 8.3 or newer; Ubuntu 22.04's default PHP packages are too old. Install PHP 8.3 from a maintained package source or use a maintained PHP 8.3 container. Do not mix PHP packages from untrusted sources. Install Nginx, MySQL, Composer, Node.js, npm, and PHP extensions required by Composer (`curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `xml`, and `zip`).

From the release directory:

```sh
cp .env.example .env
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate
```

Set a unique `APP_KEY`, `APP_URL`, MySQL credentials, and the initial admin/managed CHR values in `.env`. Set `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, and `SESSION_DRIVER=file` (or another supported secure server-side driver). Protect `.env` with restrictive filesystem permissions. Run `php artisan migrate --force`, then `php artisan db:seed --force` once to create the initial admin and server assignment. Remove the initial admin/router passwords from `.env` after seeding.

Use [the Nginx example](./deploy/nginx-vpn-management.conf.example) as a starting point. Replace its hostname, certificate paths, release path, and PHP-FPM socket before enabling it. Set Nginx's document root to the repository's `public/` directory only, run `nginx -t`, and redirect HTTP to HTTPS. Ensure Nginx/PHP-FPM can write to `storage/` and `bootstrap/cache/`, but keep application source and `.env` unreadable by the web server. Do not symlink the private wallpaper disk into `public/`. Restrict router `www-ssl` firewall access to the application server's public IP, and allow outbound HTTPS from the application server to the configured routers.

After configuration, run `php artisan optimize:clear`, `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`. Confirm `/up` returns healthy, login works over HTTPS, and uploads stay private. The automated test suite uses HTTP fakes; it does not prove a real CHR's REST permissions, certificate chain, pool format, NAT behavior, or SSTP routing.

The Script Generator's Speedtest mode targets web-based Speedtest and IP-check destinations, not MikroTik `/tool speed-test`. It routes only destinations in the generated `speedtest` address list through the SSTP interface; other internet traffic is unchanged. Confirm the SSTP endpoint is reachable on TCP 443 and verify the configured destination list matches the services you use. If FastTrack is enabled, ensure it does not bypass the generated routing policy. The CHR must forward the VPN traffic to its WAN and source-NAT it for internet return traffic.

## Remaining production checks

- Validate on the actual Ubuntu 22.04 host, including PHP 8.3 extensions, MySQL, Nginx, HTTPS, file permissions, backups, and firewall rules.
- Test each operation against the actual RouterOS v7 versions and least-privilege REST users; use test accounts and non-production NAT ports first.
- Configure and validate public hostnames/certificates before sharing the application. The backup routers were not online during cluster implementation, so no live cluster synchronization or failover test has been performed.
