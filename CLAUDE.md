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
  --data-urlencode "nom=Anna" --data-urlencode "email=anna@example.com" \
  --data "privacitat=1" --data "newsletter=1"                        # expect 303 -> CONTACT_SUCCESS_URL
```

## Architecture

**Split deployment model** — the private app (`bootstrap.php`, `src/`, `vendor/`, `storage/`) lives *outside* the public webroot; only `doc_public/api/` (entry point `index.php` + `.htaccess`) gets copied into `public_html/api/`. The `.env` lives *next to* the app folder (recommended, so updating = replacing the whole `api-forms/` folder), with an in-folder `.env` still supported and taking priority if both exist. `doc_public/api/index.php` locates `bootstrap.php` two directories up (falls back to same-directory for local dev testing). This means routing/business logic changes belong in `src/`, but a change to the public entry contract belongs in `doc_public/api/`.

**Request flow**: `doc_public/api/.htaccess` rewrites all non-file/non-dir requests to `index.php` → `bootstrap.php` loads `.env` via `Dotenv::createImmutable([__DIR__, dirname(__DIR__)])->safeLoad()` (first file found wins: app dir, then parent), sets error display based on `APP_DEBUG`, requires `src/routes.php`, then calls `Flight::start()`. Routes are defined with Flight's static `Flight::route()` API and dispatch to controller static methods (see `src/routes.php`, `src/Http/ContactController.php`).

**Controllers are static, single-purpose classes** under `App\Http` (PSR-4 autoload from `src/`). `ContactController::store()` is the pattern to follow for new form endpoints: read `$_POST` directly, validate synchronously, redirect (never render) on both success and failure, using explicit local-URL allow-listing (`redirectUrl()` rejects protocol-relative/external URLs and falls back to safe defaults) to avoid open-redirect issues. Validation failure and success both end in a `303 See Other` redirect (`Cache-Control: no-store`) since the frontend is plain HTML forms with no JS/AJAX.

**Configuration is entirely env-driven** — redirect targets (`CONTACT_SUCCESS_URL`, `CONTACT_ERROR_URL`), Plunk transactional email settings (`PLUNK_API_KEY`, `PLUNK_FROM`, `PLUNK_NAME_FROM`, `CONTACT_RECIPIENT`), and the contact-saving settings (`PLUNK_SAVE_CONTACTS`, `PLUNK_CONTACT_FIELDS`) are read from `$_ENV` per-request, not cached/injected. Missing Plunk config degrades gracefully (logs via `error_log`, does not fail the request) — email sending is best-effort and must never block the user-facing redirect. All Plunk HTTP access is centralized in `App\Plunk\Client` (raw `curl` against `https://next-api.useplunk.com`, with timeouts, no SDK): email notifications go to `/v1/send` (`App\Mail\PlunkMailer`), and form submitters are saved to the Plunk contact list via `/v1/contacts` (`App\Plunk\Contacts::save()`). Contact saving is opt-in per deployment (`PLUNK_SAVE_CONTACTS=true`; absent means only the notification email is sent). Contact saving rules: `email` identifies the record and the `newsletter` checkbox drives `subscribed` (absent checkbox = not subscribed); which extra form fields land in the contact's `data` is configured per-deployment via `PLUNK_CONTACT_FIELDS` (comma-separated, `field` or `field:plunkKey` to rename, e.g. `nom:name,telefon`). New emails are created; existing contacts are only ever subscribed via `/contacts/subscribe` when the submission checks newsletter — their `data` is never modified and they are never unsubscribed from here (an existence check runs first since the Plunk create endpoint upserts by email).

**Field contract is env-driven, not hardcoded**: the controller reads every `$_POST` field dynamically (all of them go into the notification email) and has no field names in code. The special roles come from the `.env`: `CONTACT_EMAIL_FIELD` (default `email`) is the always-required identifier, `CONTACT_NEWSLETTER_FIELD` (default `newsletter`, checkbox `value="1"`, absent = not subscribed) drives the Plunk subscription, `CONTACT_REQUIRED_FIELDS` lists extra required fields, and `CONTACT_CHECKBOX_FIELDS` lists checkboxes shown as Sí/No in the email. Adapting a new form/site means matching the HTML `name` attributes with these env values — no PHP changes. HTML `required`/`type` attributes are UX only, real validation always happens again in PHP (valid email ≤254 chars, required fields non-empty, any field ≤5000 chars).

## Security constraints (from README, keep these invariants)

- `.env`, `vendor/`, `src/`, and `storage/` must never be reachable from the public webroot — only `doc_public/api/` is public.
- `APP_DEBUG` must stay `false` in production (controls PHP `display_errors`).
- Redirect targets must be validated as same-site local paths (see `ContactController::redirectUrl()`) — don't trust env values as raw redirect targets without this check.
- Don't expose exception details or credentials to the visitor; log errors server-side instead (`storage/logs/`, currently just a `.gitkeep` placeholder — no file logging implemented yet beyond PHP's own `error_log`).
