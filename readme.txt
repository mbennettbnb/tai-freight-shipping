=== TAI Freight Shipping ===
Contributors: Michael Bennett
Tags: woocommerce, shipping, freight, ltl, tai-software
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Real-time freight shipping rates from the TAI Software Public API for WooCommerce.

== Description ==

TAI Freight Shipping connects your WooCommerce store to the TAI Software TMS
(Transportation Management System) so customers see live freight rates at checkout.

**Features**

* Real-time rate quotes from the TAI getRateQuote API.
* Supports multiple carrier quotes – let customers pick the best option.
* Configurable origin warehouse address.
* Percentage-based rate markup / markdown.
* Fallback flat rate when the API is unavailable.
* Response caching to reduce API calls.
* Test-mode toggle for the TAI Beta server.
* Debug logging via WooCommerce Status > Logs.
* Compatible with WooCommerce HPOS.

**Requirements**

* A TAI Software account with a Public API Authentication Key (GUID).
* Your TMS Production URL from TAI.

== Installation ==

1. Upload the `tai-freight-shipping` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Go to **WooCommerce → Settings → Shipping → TAI Freight Shipping**.
4. Enter your API Base URL and Authentication Key.
5. Set your origin warehouse zip code, city, and state.
6. Add the shipping method to one or more Shipping Zones.

== Frequently Asked Questions ==

= Where do I get an Authentication Key? =
Contact TAI Software support to request a Public API key for your account.

= Can I test without a production key? =
Enable **Test Mode** in the plugin settings to use the TAI Beta server.

== Changelog ==

= 1.0.0 =
* Initial release.
