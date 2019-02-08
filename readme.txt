=== WooCommerce PayZen Payment ===
Contributors: Lyra Network, Alsacréations
Tags: payment, PayZen, gateway, checkout, credit card, bank card, e-commerce
Requires at least: 3.5
Tested up to: 5.0
WC requires at least: 2.0
WC tested up to: 3.5
Stable tag: 1.7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin links your WordPress WooCommerce shop to the PayZen payment gateway.

== Description ==

The payment plugin has the following features:
* Compatible with WooCommerce v 2.0.0 and above.
* Management of one-time payment and payment in installments.
* Possibility to define many options for payment in installments (2 times payment, 3 times payment,...).
* Can do automatic redirection to the shop at the end of the payment.
* Setting of a minimum / maximum amount to enable payment module.
* Selective 3D Secure depending on the order amount.
* Update orders after payment through a silent URL (Instant Payment Notification).
* Multi languages compliance.
* Multi currencies compliance.
* Possibility to enable / disbale module logs.
* Possibility to configure order status on payment success.

== Installation ==

1. Upload the folder `woo-payzen-payment` to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress
3. To configure the plugin, go to the `WooCommerce > Settings` menu in WordPress then choose `Checkout` or `Payments` tab (depending on your WooCommerce version).

== Screenshots ==

1. PayZen general configuration.
2. PayZen one-time payment configuration.
3. PayZen payment in installments configuration.
4. PayZen payment options in checkout page.
5. PayZen payment page.

== Changelog ==

= 1.7.0, 2019-01-21 =
* Fix error in shipping amount calculation (on some WooCommerce 2.x versions).
* Improve payment error display on order details and hide message in order email.
* Added payment by token (requires PayZen payment by token option).
* Added possibility to restrict payment submodules to specific countries.
* Manage successful order statuses dynamically to support custom statuses.
* Redirect buyer to cart page (instead of checkout page) after a failed payment.
* Display error messages and notices in WooCommerce 3.5.
* Added API to manage subscriptions payment integration (for developpers).

--------

[See the previous changelogs here](./CHANGELOG.md).