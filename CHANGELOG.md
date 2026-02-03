# Changelog

All notable changes to ProductHandel will be documented in this file.

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
