# ProductHandel

A simple WordPress e-commerce plugin that lets you create and sell products with PayPal Standard integration. No shopping cart required — customers buy directly from the product page.

## Features

- Custom "Products" post type with price management
- Free products (price of 0) skip PayPal and complete instantly
- PayPal Standard integration (no API keys needed, just your PayPal email)
- Sandbox mode for testing with PayPal sandbox accounts
- Test mode for development without PayPal
- Automatic WordPress user account creation for buyers (optional)
- Purchase confirmation emails with optional HTML formatting
- Post-purchase invoice page with print-friendly styling
- License key generation for products (SHA-256 based, displayed on invoice and in emails)
- Product download links (displayed on invoice and in emails)
- Order management in WordPress admin (edit buyer info, resend invoices, delete test orders, CSV export)
- Shortcode for embedding products on any page
- Automatic database versioning and schema migration
- Block theme compatible (Twenty Twenty-Three, Twenty Twenty-Four, etc.)

## Installation

### From Source

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/product-handel.git
   cd product-handel
   ```

2. Build the installable zip file:
   ```bash
   make build
   ```

3. In WordPress admin, go to **Plugins > Add New > Upload Plugin** and upload `build/product-handel.zip`

4. Activate the plugin

### Manual Installation

1. Download or clone this repository
2. Copy the `product-handel` folder to `wp-content/plugins/`
3. Activate the plugin from **Plugins** in WordPress admin

## Configuration

### Settings

Navigate to **Settings > ProductHandel** to configure:

- **PayPal Email**: Your PayPal account email address that will receive payments
- **Currency**: Select from USD, EUR, GBP, CAD, AUD, and other common currencies
- **Return URL**: Where customers are redirected after successful payment
- **Cancel URL**: Where customers are redirected if they cancel the payment
- **Sandbox Mode**: Enable to use PayPal sandbox for testing
- **Test Mode**: Skip PayPal entirely and simulate successful payments (development only)
- **Create User Account**: Automatically create a WordPress subscriber account for new buyers
- **Show Password on Invoice**: Display the generated password on the invoice page when user account creation is enabled
- **HTML Email**: Send styled HTML emails matching the invoice page design instead of plain text

The settings page also displays your **IPN Listener URL** — configure this in your PayPal account under IPN notification settings.

### Creating Products

1. Go to **Products > Add New Product** in WordPress admin
2. Enter the product title and description
3. Set a featured image (optional, displayed in shortcode)
4. Enter the price in the **Product Price** meta box on the sidebar
5. Optionally enable **License Key Generation** and provide a salt in the License Key Settings meta box
6. Optionally add a **Download Link** URL in the Download Settings meta box
7. Publish the product

### Shortcode

Use the shortcode to display a product on any page or post:

```
[product_buy id="123"]
```

Replace `123` with your product's ID. The shortcode displays:
- Featured image
- Product title
- Product description
- Price
- "Buy Now" button linking to the product page

On the product page itself, customers see a purchase form with first name, last name, and email fields, followed by a "Complete Purchase" button that initiates the PayPal payment flow.

### Invoice Page

After a successful payment, buyers are redirected to a secure invoice page (`/invoice/{token}`) that displays:
- Order details (buyer name, product name, amount, transaction ID, purchase date)
- License key (if enabled for the product)
- Download link (if configured for the product)
- Generated password (if user account creation and "Show Password on Invoice" are enabled)

Invoice access tokens expire after 48 hours. The page uses AJAX polling to handle asynchronous PayPal IPN timing and includes print-friendly styling.

### Product Orders

View all orders at **Product Orders** in the WordPress admin menu. Each order shows:
- Order ID
- Product name
- Buyer first name, last name, and email
- Amount and currency
- Status (pending, completed, failed, refunded)
- Transaction ID
- Invoice link
- Date

Admins can also:
- **Edit** buyer first name, last name, and email via an inline modal
- **Resend Invoice** email for completed orders
- **Delete** orders when test mode is enabled
- **Download CSV** to export all order data as a CSV file

## PayPal Setup

1. Log in to your PayPal account
2. Go to **Account Settings > Notifications > Instant Payment Notifications**
3. Enable IPN and set the notification URL to the IPN Listener URL shown on the plugin settings page
4. For testing, create a PayPal sandbox account at https://developer.paypal.com

## License

GPL v2 or later
