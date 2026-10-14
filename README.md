# Sticky.io Order Report Management Dashboard

PHP 8.1+, MySQL 8/MariaDB, jQuery, HTML/CSS. This project includes login, role checks, user management, role permissions, brand CRUD/deactivation, API settings, initial-order and subsequent-rebill reports, column preferences, order details, profile/password update, and expiring single-use password-reset tokens.

## Project and database names
- Project folder: `sticky-order-dashboard`
- Database: `sticky_order_dashboard`
- SQL schema: `sql/schema.sql`
- Configuration: copy `config/config.example.php` to `config/config.php`
- First Super Admin: `sql/create_super_admin.php` (CLI only)

## Local setup (XAMPP / LAMP)
1. Install PHP 8.1+ with PDO MySQL, cURL, OpenSSL, Fileinfo and MySQL 8/MariaDB.
2. Copy this folder into your web server document root (for XAMPP: `htdocs/sticky-order-dashboard`). Prefer configuring the virtual host document root to this folder and deny direct access to `config/`, `sql/`, and `tests/` in production.
3. Create a database user and database, or import schema which creates `sticky_order_dashboard`:
   ```sh
   mysql -u root -p < sql/schema.sql
   ```
4. Copy `config/config.example.php` to `config/config.php`. Set DB host/name/user/password, `app.base_url`, timezone, and reset URL. Generate an encryption key:
   ```sh
   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
   ```
   Put that value in `encryption_key`. Store this key outside the public web root and source repository in production. The sample config is only for local development.
5. Create the first Super Admin securely from the project directory:
   ```sh
   php sql/create_super_admin.php
   ```
   The script refuses to create a second Super Admin. Do not seed a default password.
6. Open `http://localhost/sticky-order-dashboard/login.php` (or the configured base URL) and sign in.
7. Add brands and campaign IDs under **Brands**. Give standard users brand access by inserting rows into `user_brands` for their user IDs and brand IDs. Super Admin/Admin users with brand-management permission can see all active brands.
8. Under **API Settings**, set the endpoint (default is the endpoint from the requirements), username, password, and product ID lists. Password is encrypted using AES-256-GCM; the encryption key must remain private.
9. Configure outbound server email (`mail()`/MTA or adapt the reset handler to your SMTP provider) and confirm delivery before relying on forgot-password flow.

## Schema summary
- `users`: accounts, password hashes, role and active flag.
- `brands`: logo, name, campaign IDs, active/deactivated status.
- `user_brands`: many-to-many user-to-brand access.
- `settings`: Sticky endpoint, username, encrypted password, and normalized product ID lists.
- `permissions`: per-role action permissions.
- `password_resets`: SHA-256 token hashes, expiry, and single-use timestamp.
- `audit_log`: non-sensitive security/admin events.

## Sticky.io API request
The server posts JSON to the configured endpoint using HTTP Basic authentication:
```json
{
  "campaign_id": "151,150",
  "start_date": "10/13/2026",
  "end_date": "10/13/2026",
  "criteria": "all",
  "search_type": "all",
  "return_type": "order_view"
}
```
The campaign IDs are normalized without spaces and sorted descending numerically. TLS certificate validation is enabled. Timeouts are configurable in `config/config.php`. Rate-limit (429), non-2xx, invalid JSON, and API response-code errors are surfaced without logging credentials or full customer payloads.

## AJAX reports and large result sets
- Dashboard page rendering does not load either table report. Each table fetches independently when its own **Apply** button is clicked; changing fields saves the field selection and reloads only that report. The summary loads separately over AJAX.
- A per-report loading overlay is shown during report fetches and field updates.
- Sticky.io order-view results can be capped at 500 detailed orders. The integration attempts to fetch remaining order IDs in batches; `sticky.max_pages` in `config/config.php` is the maximum number of follow-up batches and `sticky.order_ids_batch_size` defaults to 400. A timeout of at least 120 seconds is used for each Sticky.io call.
- If your installed Sticky.io endpoint does not support `order_ids` with `order_find`, the dashboard displays a clear error rather than silently counting missing orders; ask Sticky.io support which supported method returns `order_view` details for a supplied list of IDs.

## Order classification rules
The reusable `classify_order()` service in `includes/sticky.php` is shared by both reports:
- **Initial:** `order_id === parent_id` and at least one `products[].product_id` matches configured main IDs.
- **Subscription rebill:** `order_id !== parent_id` and at least one product matches configured subscription IDs.
- Root subscription signup is not a rebill. Upsell-only transactions are not initial orders. Orders without required IDs/products or with no known matching IDs are not counted.
- Product ID lists are numeric, de-duplicated, no-space comma-separated values sorted descending numerically.

**Production validation required:** The sample response shows root orders and a child order, but real Sticky.io behavior can differ. In particular, when an order has overlapping product IDs or a child transaction contains both main and subscription products, classification must be confirmed against known account examples. The current deterministic rule prioritizes subscription IDs for child orders; such mixed cases should be reviewed before production. Report date uses `time_stamp` only; confirm this field aligns with the Sticky.io account timezone. The dashboard now uses the returned `remaining_order_ids` (and IDs listed in `order_ids` without matching detail records) to request missing order details in batches of up to 400 IDs using the `order_ids` parameter. It deduplicates by `order_id` and returns an error instead of silently reporting an incomplete set if Sticky.io fails to return requested details. Verify this behavior against your Sticky.io instance because endpoint versions may differ. The report date is based on `time_stamp` only, with URL-encoded timestamp values decoded before date filtering.

## Security / deployment notes
- Use HTTPS, set a strong private encryption key, use a dedicated DB user with least privilege, and keep `config/config.php` outside public web access/source control.
- Uses PDO prepared statements, password hashing, strict session settings, session ID regeneration, CSRF tokens on forms, output escaping, upload MIME/size checks, and server-side authorization guards.
- Configure a proper mail transport for password resets. Reset links are random, stored as hashes, expire in 30 minutes, and are single-use.
- Protect or deny web access to `sql/`, `tests/`, `config/`, and logs. Consider moving uploads outside the web root and scanning uploads in production.
- Card and personal fields may be returned by Sticky.io. This dashboard escapes output and does not log API payloads. Limit access and retention in accordance with your privacy/security policies.
- The current version stores column preferences in the session. Persistent per-user preferences can be added if required.

## Tests and checks
Run local classifier tests:
```sh
php tests/classifier_test.php
```
Check PHP syntax:
```sh
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```
Checklist before production:
- [ ] Create first Super Admin; verify there is no default account/password.
- [ ] Login/logout, session regeneration, invalid password, inactive account.
- [ ] Admin/User role permissions are enforced on direct page and POST requests.
- [ ] Assign brands through `user_brands`; test brand switching and deactivated brand behavior.
- [ ] Save API endpoint, username, encrypted password and sorted product IDs.
- [ ] Validate initial, upsell, subscription signup and subsequent rebill examples against real account data.
- [ ] Test missing/malformed API response, invalid credentials, timeout, HTTP 429, and empty results.
- [ ] Test today's date, custom date range, account timezone, and `time_stamp` report-date filtering.
- [ ] Test AJAX Apply independently for both tables, field changes, loading indicators, and a date range with more than 500 orders.
- [ ] Test column selection, details expansion, order details, long/horizontal/vertical scrolling.
- [ ] Test logo upload type/size errors and brand soft deactivation.
- [ ] Test profile update, password change, reset expiry/single-use, and actual email delivery.
- [ ] Confirm the endpoint supports `order_ids` batching and verify counts for a range with more than 500 orders.

## Scope caveats
The archive is a runnable project baseline, but **live Sticky.io integration has not been verified** because no production credentials/account were supplied. Role permissions are available for brand, user and API settings management; fine-grained per-action permissions and user-brand assignment UI can be expanded as needed. Do not use production data until the checklist is passed.
