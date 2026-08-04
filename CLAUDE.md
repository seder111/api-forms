# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A minimal, reusable PHP backend for receiving HTML forms without JavaScript (Flight PHP micro-framework + phpdotenv). Designed to be deployed as a private application outside the public webroot, with only a thin entry point exposed publicly. Meant to be reused across multiple static/Astro sites — each deployment gets its own `.env`.

## Commands

```bash
composer install --no-dev --optimize-autoloader --no-interaction   # production install (respects composer.lock)
composer dump-autoload --optimize                                   # after adding classes or changing namespaces
```

There is no test suite, linter, or build step configured in this repo.

Local smoke test (from repo root, matches the checks the README documents):

```bash
php -S localhost:8000 -t doc_public/api   # or point a local Apache vhost at doc_public/api
curl -i http://localhost:8000/                                       # expect {"status":"ok","service":"api-forms"}
curl -i -X POST http://localhost:8000/contact \
  --data-urlencode "name=Anna" --data-urlencode "email=anna@example.com" \
  --data "privacy=1" --data "newsletter=1"                           # expect 303 -> CONTACT_SUCCESS_URL
```

## Architecture

**Split deployment model** — the private app (`bootstrap.php`, `src/`, `vendor/`, `storage/`) lives *outside* the public webroot; only `doc_public/api/` (entry point `index.php` + `.htaccess`) gets copied into `public_html/api/`. The `.env` lives *next to* the app folder (recommended, so updating = replacing the whole `api-forms/` folder), with an in-folder `.env` still supported and taking priority if both exist. `doc_public/api/index.php` locates `bootstrap.php` two directories up (falls back to same-directory for local dev testing). This means routing/business logic changes belong in `src/`, but a change to the public entry contract belongs in `doc_public/api/`.

**Request flow**: `doc_public/api/.htaccess` rewrites all non-file/non-dir requests to `index.php` → `bootstrap.php` loads `.env` via `Dotenv::createImmutable([__DIR__, dirname(__DIR__)])->safeLoad()` (first file found wins: app dir, then parent), sets error display based on `APP_DEBUG`, requires `src/routes.php`, then calls `Flight::start()`. Routes are defined with Flight's static `Flight::route()` API and dispatch to controller static methods (see `src/routes.php`, `src/Http/ContactController.php`).

**Controllers are static, single-purpose classes** under `App\Http` (PSR-4 autoload from `src/`). `ContactController::store()` is the pattern to follow for new form endpoints: read `$_POST` directly, validate synchronously, redirect (never render) on both success and failure, using explicit local-URL allow-listing (`redirectUrl()` rejects protocol-relative/external URLs and falls back to safe defaults) to avoid open-redirect issues. Validation failure and success both end in a `303 See Other` redirect (`Cache-Control: no-store`) since the frontend is plain HTML forms with no JS/AJAX.

**Configuration is entirely env-driven** — redirect targets (`CONTACT_SUCCESS_URL`, `CONTACT_ERROR_URL`), Plunk transactional email settings (`PLUNK_API_KEY`, `PLUNK_FROM`, `PLUNK_NAME_FROM`, `CONTACT_RECIPIENT`), and the per-feature toggles (`PLUNK_SEND_EMAIL`, `PLUNK_SAVE_CONTACTS`, `PLUNK_CONTACT_FIELDS`) are read from `$_ENV` per-request, not cached/injected. All `$_ENV` reads go through the `App\Support\Env` helper (`Env::string()` trimmed-with-default, `Env::list()` comma-separated, `Env::bool()` with an optional default for opt-out flags) — don't read `$_ENV` directly in new code. Missing Plunk config degrades gracefully (logs via `error_log`, does not fail the request) — email sending is best-effort and must never block the user-facing redirect. All Plunk HTTP access is centralized in `App\Plunk\Client` (raw `curl` against `https://next-api.useplunk.com`, with timeouts, no SDK): email notifications go to `/v1/send` (`App\Mail\PlunkMailer`), and form submitters are saved to the Plunk contact list via `/contacts` (`App\Plunk\Contacts::save()`). Both Plunk features are per-deployment toggles with opposite defaults: the notification email is opt-out (`PLUNK_SEND_EMAIL=false` disables it — for contacts-only deployments, which then don't need `PLUNK_FROM`/`CONTACT_RECIPIENT`; absent means emails are sent), while contact saving is opt-in (`PLUNK_SAVE_CONTACTS=true`; absent means contacts are not saved). Contact saving rules: `email` identifies the record and the `newsletter` checkbox drives `subscribed` (absent checkbox = not subscribed); which extra form fields land in the contact's `data` is configured per-deployment via `PLUNK_CONTACT_FIELDS` (comma-separated, `field` or `field:plunkKey` to rename, e.g. `name,phone:telefono`). New emails are created; existing contacts are only ever subscribed via `/contacts/subscribe` when the submission checks newsletter — their `data` is never modified and they are never unsubscribed from here (an existence check runs first since the Plunk create endpoint upserts by email).

**Database submission log is optional and PDO-based** (`App\Database`, no ORM/query builder): `DB_SAVE_SUBMISSIONS=true` opts a deployment in (absent = no connection is ever opened); `ContactController::store()` then logs every valid submission via `App\Database\Submissions::save()` into a `submissions` table (email, all fields as a JSON `payload`, `created_at`), auto-created with `CREATE TABLE IF NOT EXISTS` on first use — no manual schema step. `App\Database\Connection::pdo()` is the single PDO access point (lazy, cached per request, `ERRMODE_EXCEPTION` + real prepared statements): `DB_DRIVER=mysql` (default, also MariaDB — `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASSWORD`, utf8mb4) or `sqlite` (`DB_SQLITE_PATH`, relative paths resolve against the app root, default `storage/database.sqlite`). Same best-effort contract as Plunk: connection/insert failures are caught, logged via `error_log`, and never block the redirect.

**Field contract is env-driven, not hardcoded**: the controller reads every `$_POST` field dynamically (all of them go into the notification email) and has no field names in code. The special roles come from the `.env`: `CONTACT_EMAIL_FIELD` (default `email`) is the always-required identifier, `CONTACT_NEWSLETTER_FIELD` (default `newsletter`, checkbox `value="1"`, absent = not subscribed) drives the Plunk subscription, `CONTACT_REQUIRED_FIELDS` lists extra required fields, and `CONTACT_CHECKBOX_FIELDS` lists checkboxes shown as Sí/No in the email. Adapting a new form/site means matching the HTML `name` attributes with these env values — no PHP changes. HTML `required`/`type` attributes are UX only, real validation always happens again in PHP (valid email ≤254 chars, required fields non-empty, any field ≤5000 chars).

**Cloudflare Turnstile verification is the one non-best-effort external check, and it fails silently, not loudly**: `App\Turnstile\Verifier` (opt-in via `TURNSTILE_SECRET_KEY`, `Verifier::enabled()`) POSTs the widget's `cf-turnstile-response` field to `https://challenges.cloudflare.com/turnstile/v0/siteverify`. A missing token, curl error, non-2xx response, or negative verdict all make `Verifier::verify()` return `false` — but `ContactController::store()` deliberately does **not** treat that like a normal validation failure: `passesTurnstile()` is checked separately from `isValid()`, and on failure the request is redirected to `CONTACT_SUCCESS_URL` (the exact same 303 a real submission gets), just with the email/Plunk-contact/database-row side effects skipped. The point is that a bot must not be able to tell a Turnstile rejection apart from a genuine success — only a `CONTACT_REQUIRED_FIELDS`/email validation failure (a real user mistake) still redirects to `CONTACT_ERROR_URL`. Runs only when `Verifier::enabled()`, i.e. deployments without the widget are unaffected. The widget itself (site key, `<div class="cf-turnstile">`, Cloudflare's script tag) lives in each site's frontend, outside this repo — only the secret-key verification is implemented here. The token field is stripped from `$fields` (`unset($fields[Verifier::FIELD])`) right before it would otherwise leak into the notification email, Plunk contact data, or the `submissions` table.

## Security constraints (from README, keep these invariants)

- `.env`, `vendor/`, `src/`, and `storage/` must never be reachable from the public webroot — only `doc_public/api/` is public. That includes the SQLite database file (default `storage/database.sqlite`) — never point `DB_SQLITE_PATH` inside `public_html`.
- `APP_DEBUG` must stay `false` in production (controls PHP `display_errors`).
- Redirect targets must be validated as same-site local paths (see `ContactController::redirectUrl()`) — don't trust env values as raw redirect targets without this check.
- Don't expose exception details or credentials to the visitor; log errors server-side instead (`storage/logs/`, currently just a `.gitkeep` placeholder — no file logging implemented yet beyond PHP's own `error_log`).
