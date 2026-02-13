# Changelog

All notable changes to ProductHandel will be documented in this file.

## [1.9.7] - 2026-02-13

### Changed
- **CSS directory consolidation** — Moved `assets/css/frontend-styles.css` and `admin/css/admin-styles.css` into a single `css/` directory; updated enqueue paths and Makefile
- **Plugin requirements** — Added `Requires at least: 6.0` and `Requires PHP: 8.0` to the plugin header
- **README badge** — Fixed last commit badge to point to the correct repository

## [1.9.6] - 2026-02-13

### Security
- **Escape thumbnail output** — Product thumbnail in the `[product_buy]` shortcode now wrapped with `wp_kses_post()` for proper output escaping
- **Sanitize `$_SERVER` superglobals** — `REMOTE_ADDR` in the IPN listener and PayPal handler, and `REQUEST_URI` in the invoice page fallback, are now sanitized with `sanitize_text_field(wp_unslash(...))`

## [1.9.5] - 2026-02-12

### Added
- **IP deny list** — IPs that fail reCAPTCHA verification or trigger the honeypot field are automatically blocked from the purchase form for 24 hours using WordPress transients

## [1.9.4] - 2026-02-12

### Added
- **reCAPTCHA v3** — Optional Google reCAPTCHA v3 integration on the purchase form with admin settings for enable/disable checkbox, site key, and secret key
- **Honeypot field** — Hidden "Zip/Post Code" field on the purchase form to catch bot submissions; triggers a silent redirect to the homepage

## [1.9.3] - 2026-02-12

### Changed
- **License key algorithm** — Transaction ID is now lowercased before hashing for consistent key generation regardless of case
- **License key algorithm** — Hash input reordered to first name, last name, email, transaction ID, salt

## [1.9.2] - 2026-02-12

### Changed
- **License key algorithm** — Keys now include the transaction ID in the hash, producing a unique key per purchase instead of a deterministic key per buyer

## [1.9.1] - 2026-02-12

### Security
- **Shortcode output escaping** — All dynamic HTML attributes in the purchase form now use `esc_attr()` and JavaScript element IDs use `wp_json_encode()` for defense-in-depth XSS prevention
- **Shortcode content filtering** — Product description rendered via the `[product_buy]` shortcode now uses `wp_kses_post()` to strip unsafe HTML tags

## [1.9.0] - 2026-02-12

### Security
- **IPN payment amount verification** — IPN listener now verifies that the paid amount and currency match the order before marking it as completed, preventing payment amount manipulation
- **Duplicate IPN detection** — IPN listener skips processing if the order is already completed with the same transaction ID, preventing duplicate emails and user creation from replayed notifications
- **Encryption fallback key** — Fallback encryption key is now randomly generated and persisted in the database instead of being derived from the publicly-known site URL
- **IPN rate limiting** — IPN endpoint limited to 20 requests per minute per IP and restricted to POST requests only
- **IPN data sanitization** — Transaction ID and receiver email are now sanitized at extraction; all IPN data values are sanitized before being stored as payment data

## [1.8.10] - 2026-02-11

### Changed
- **Purchase button label** — "Complete Purchase" button on the product page renamed to "Pay via Paypal"

## [1.8.9] - 2026-02-10

### Added
- **Custom thank you message** — New "Thank You Message" setting under Email Settings to customise the message shown in purchase confirmation emails. Defaults to "Thank you for your purchase."

### Changed
- **Email greeting** — Purchase confirmation emails now greet with "Hi [First Name]," instead of "Hi [First Name Last Name],"

## [1.8.8] - 2026-02-10

### Added
- **Product registration note** — New "Registration Note" textarea in the License Key Settings meta box on the product edit page. The note appears on the invoice and in purchase confirmation emails below the license key.

## [1.8.7] - 2026-02-10

### Changed
- **Buyer details on invoice** — The Order Details section on the invoice page and in purchase confirmation emails now shows three separate fields (First Name, Last Name, Email) instead of a single combined Buyer field

## [1.8.6] - 2026-02-10

### Added
- **Buyer email on invoice** — The buyer's email address is now displayed in the Order Details section on the invoice page and in purchase confirmation emails (both HTML and plain text)

## [1.8.5] - 2026-02-08

### Added
- **Free product support** — Products with a price of 0 now skip the PayPal transaction and go directly to post-payment actions (license key, email, user creation) with an auto-generated `FREE-` transaction ID
- **Buyer name in order details** — The buyer's full name is now shown as the first line in the Order Details section on the invoice page and in purchase confirmation emails (both HTML and plain text)

## [1.8.4] - 2026-02-08

### Fixed
- **PayPal IPN not working** — IPN listener was never triggered due to a timing issue where it registered on the `init` hook after it had already fired
- **IPN verification URL** — Changed to PayPal's dedicated IPN verification endpoints (`ipnpb.sandbox.paypal.com` / `ipnpb.paypal.com`)
- **IPN verification blocked** — Replaced `wp_safe_remote_post` with `wp_remote_post` to prevent PayPal's domain from being blocked
- **IPN verification data** — Send raw POST data to PayPal for verification instead of re-encoding, which could mangle special characters
- **Post-payment actions not running** — License key generation and confirmation emails were skipped because their handlers were registered after the IPN listener processed the payment; reordered initialization so all handlers are registered before the IPN listener runs

## [1.8.2] - 2026-02-07

### Changed
- **License key algorithm** — Keys are now generated from buyer first name + last name + email + salt instead of email only

## [1.8.1] - 2026-02-07

### Added
- **CSV export** — "Download CSV" button on the Product Orders admin page to export all order data as a CSV file

## [1.8.0] - 2026-02-07

### Added
- **HTML email** — New "HTML Email" setting under Email Settings to send styled HTML emails matching the invoice page design
- Purchase confirmation and account credentials emails both support HTML format
- HTML emails use inline CSS with the same visual style as the invoice page (blue header, white card, styled tables)

## [1.7.1] - 2026-02-07

### Added
- **Delete order** — Admins can delete orders from the Product Orders screen when test mode is enabled
- `delete_order()` method in Order Manager
- Server-side guard prevents deletion when test mode is off

## [1.7.0] - 2026-02-07

### Changed
- **Split buyer name into first and last name** — Purchase form, database, admin orders page, invoice, and emails now use separate first name and last name fields
- Database schema replaces `buyer_name` column with `buyer_first_name` and `buyer_last_name` columns
- Admin orders table displays first name and last name in separate columns
- Edit order modal now has separate first name and last name inputs
- WordPress user accounts created on purchase now set `first_name` and `last_name` fields correctly

### Added
- **PayPal buyer override** — On successful payment, the buyer's first name, last name, and email are overridden with PayPal's verified payer data from the IPN response

### Migration
- Existing `buyer_name` values are automatically migrated to `buyer_first_name`; the old column is dropped

## [1.6.5] - 2026-02-07

### Added
- **Product download link** — New "Download Settings" meta box on the product edit screen with a URL field and checkbox
- Download link displayed on the invoice page when enabled
- Download link included in the purchase confirmation email when enabled

## [1.6.0] - 2026-02-06

### Added
- **Resend Invoice** button on each completed order in the Product Orders admin screen

## [1.5.0] - 2026-02-06

### Added
- Purchase confirmation email now includes the license key when one has been generated for the product

### Changed
- License key generation now runs before the confirmation email is sent so the key is available to include

## [1.4.2] - 2026-02-04

### Changed
- Single product page featured image now displays at 320px max-width, centered

## [1.4.1] - 2026-02-03

### Added
- Invoice URL column on Product Orders admin page with "View" link to open invoice

## [1.4.0] - 2026-02-03

### Added
- **License key generation** - Products can now generate unique license keys for buyers
- New "License Key Settings" meta box on product edit screen with checkbox to enable and salt field
- License keys are generated from buyer email + product salt using SHA-256 hash
- License keys displayed in XXXX-XXXX-XXXX-XXXX format on the invoice page
- `generate_license_key()` method in Order Manager for deterministic key generation

### Changed
- Database schema extended with `license_key` column in orders table

## [1.3.0] - 2026-02-03

### Added
- **Edit buyer information** - Admins can now edit buyer name and email address from the Product Orders screen
- Modal popup for inline editing with AJAX save
- `update_order()` method now supports buyer name and `buyer_email` fields

## [1.2.0] - 2026-02-03

### Added
- **Post-purchase invoice page** - Buyers are now redirected to a dedicated invoice page (`/invoice/{token}`) after completing payment
- Invoice displays order details: product name, amount, transaction ID, purchase date, and buyer information
- **Password display on invoice** - When "Create User Account" is enabled, the buyer's generated password is shown on the invoice page with a warning to save it
- New setting: "Show Password on Invoice" to control whether passwords appear on the invoice page
- Token-based secure access to invoice pages (256-bit random tokens, 48-hour expiry)
- Encrypted temporary password storage using AES-256-CBC
- AJAX polling on invoice page to handle asynchronous PayPal IPN timing
- Print-friendly invoice styling

### Changed
- After payment, buyers are redirected to the invoice page instead of the generic return URL
- Database schema extended with `access_token`, `access_token_expires`, `temp_password`, and `temp_password_expires` columns

### Security
- Temporary passwords are encrypted at rest and automatically cleared after display or 30-minute expiry
- Invoice access tokens expire after 48 hours
- URL fallback parsing for environments where rewrite rules may not flush immediately

## [1.1.0] - 2026-02-03

### Added
- Database versioning and automatic schema migration on plugin updates

## [1.0.3] - Initial tracked version

### Features
- PayPal Standard integration for direct purchases
- Custom product post type with price metadata
- Product display shortcode `[product_buy id="123"]`
- PayPal IPN listener for payment verification
- Optional WordPress user account creation for buyers
- Purchase confirmation emails
- Account credentials email for new users
- Admin order management page
- Sandbox and test mode support
- Multi-currency support (16 currencies)
