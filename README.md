# WooCommerce Egaline Calculator

WooCommerce Egaline Calculator adds a product-specific material calculator to WooCommerce. Customers can calculate the required material from thickness and surface area, while the plugin carries the calculated quantity and pricing context through the cart, checkout and order workflow.

The WordPress-style `readme.txt` is the canonical source for current compatibility and release metadata. This README provides the GitHub project overview.

## What it does

- Adds configurable calculator fields to selected WooCommerce products.
- Calculates area, required kilograms and bag count from the configured product values.
- Updates the effective product quantity and price through WooCommerce rather than bypassing the cart totals system.
- Preserves calculator details in cart and order-item metadata.
- Supports simple and variable product workflows.
- Provides a WordPress admin overview for products where the calculator is enabled.
- Declares WooCommerce HPOS compatibility.

## Requirements

- WordPress 6.8 or newer.
- PHP 8.2 or newer.
- WooCommerce 8.0 or newer.

The current release is `1.0.9`. The current compatibility matrix in `readme.txt` is tested through WordPress 7.1 and WooCommerce 11.1.0.

## Installation

1. Upload the plugin folder or packaged ZIP through **Plugins → Add New → Upload Plugin**.
2. Make sure WooCommerce is installed and active.
3. Activate **WooCommerce Egaline Calculator**.
4. Open a WooCommerce product and configure the calculator settings for that product.

For a manual installation, place the plugin folder under `wp-content/plugins/` and activate it from the WordPress Plugins screen.

## Usage

Configure the calculator only on products that need material-based ordering. The product settings control the calculator inputs and calculation behaviour. On the storefront, WooCommerce remains responsible for cart totals, taxes, coupons, fees, shipping and payment totals after the calculator has supplied the calculated product quantity/pricing data.

Variable products activate the calculator after WooCommerce has resolved a valid variation.

## Repository structure

- `woocommerce-egaline-calculator.php` — plugin bootstrap, metadata and integration entrypoint.
- `includes/` — calculator and WooCommerce logic.
- `assets/` — admin/frontend assets.
- `templates/` — plugin templates.
- `languages/` — translation files.
- `uninstall.php` — plugin cleanup logic.
- `readme.txt` — canonical WordPress distribution and compatibility metadata.

## License

GPL v2 or later. See `LICENSE` and the plugin metadata.