# Changelog

All notable changes to ProductHandel will be documented in this file.

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
- `update_order()` method now supports `buyer_name` and `buyer_email` fields

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
