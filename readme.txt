=== WooCommerce PayZen Payment ===
Contributors: Lyra Network, Alsacréations
Tags: payment, PayZen, gateway, checkout, credit card, bank card, e-commerce
Requires at least: 3.5
Tested up to: 5.4
WC requires at least: 2.0
WC tested up to: 4.0
Stable tag: 1.8.2
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
* Possibility to enable / disable module logs.
* Possibility to configure order status on payment success.

== Installation ==

1. Upload the folder `woo-payzen-payment` to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress
3. To configure the plugin, go to the `WooCommerce > Settings` menu in WordPress then choose `Checkout` or `Payments` tab (depending on your WooCommerce version).

== Screenshots ==

1. PayZen general configuration.
2. PayZen standard payment configuration.
3. PayZen payment in installments configuration.
4. PayZen payment options in checkout page.
5. PayZen payment page.

== Changelog ==

= 1.8.2, 2020-03-16 =
* Bug fix: Manage products with zero amount in tax calculation.
* Bug fix: Payment by alias available only for logged in users.
* Bug fix: Skip confirmation alert after clicking on payment button with IFRAME and REST modes (on WooCommerce >= v3.9).
* Bug fix: Exit script after redirection to cart URL in error cases.
* Fix errors (NOTICE level) when retrieving some configuration fields.
* [embedded] Fix embedded payment fields display in WooCommerce v3.9 (relative to WooCommerce issue #24271).

--------

A full changelog is available in the CHANGELOG.md file.