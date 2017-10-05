=== WooCommerce PayZen Payment ===
Contributors: Lyra Network, Alsacréations
Tags: payment, PayZen, gateway, checkout, credit card, bank card, e-commerce
Requires at least: 3.5
Tested up to: 4.8.2
Depends: WooCommerce 2.0 or higher
Stable tag: 1.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plug-in links your WordPress WooCommerce shop to the PayZen payment platform.

== Description ==

The payment plug-in has the following features:
* Compatible with WooCommerce v 2.0.0 and above.
* Management of one-time payment and payment in installments.
* Possibility to define many options for payment in installments (2 times payment, 3 times payment,...).
* Can do automatic redirection to the shop at the end of the payment.
* Setting of a minimum / maximum amount to enable payment module.
* Selective 3D-Secure depending on the order amount.
* Update orders after payment through a silent URL (Instant Payment Notification).
* Multi languages compliance.
* Multi currencies compliance.
* Possibility to enable / disbale module logs.
* Possibility to configure order status on payment success.

== Installation ==

1. Upload the folder `woo-payzen-payment` to the `/wp-content/plugins/` directory
2. Activate the plug-in through the `Plugins` menu in WordPress
3. To configure the plug-in, go to the `WooCommerce > Settings` menu in WordPress then choose `Checkout` tab.

== Screenshots ==

1. PayZen general configuration.
2. PayZen one-time payment configuration.
3. PayZen payment in several times configuration.
4. PayZen payment options in checkout page.
5. PayZen payment page.

== Changelog ==

= 1.4.1, 2017-10-09 =
* Bug fix: check cart and customer when checking payment method is available (to avoid errors with WooCommerce Subscriptions).

= 1.4.0, 2017-09-11 =
* Bug fix: allow plugin installation on multisite WordPress platform.
* Fix notice when card type selection on merchant website option is not used.
* Fix warning in order e-mail sent to buyer relative to empty transaction ID.
* Send delivery phone number to payment platform.

= 1.3.2, 2017-05-03 =
* Rename referenced directory in code to match new root plugin directory.
* Rename translation domain name.

= 1.3.1, 2017-05-01 =
* Bug fix: consider contract entered in multiple payment options configuration.
* Ability to propose the card type choice on the WooCommerce frontend.
* Compatibility with WPML translation plugin (module lets WPML translate gateway title and description if installed).
* Compatibility with new WooCommerce versions (3.x).
* Display multilingual field values in website locale by default.

= 1.3.0, 2016-11-15 =
* Using multilingual fields for method title and description and for redirection messages (WordPress 4.0.0 or higher).
* Correction of some text translations.
* Ability to configure order status on payment success.
* Replace deprecated code.
* Remove control over certificate format modified on the platform.
* Correction of an error to make module compatible with WooCommerce 2.6.
* Save payment result sent from payment platform and send it to customer by mail.

= 1.2.4, 2016-06-01 =
* Adding German translation file.

= 1.2.3, 2015-07-09 =
* Bug fix when IPN URL call in monosite mode.

= 1.2.2, 2015-06-25 =
* Bug fix: automatic redirection to payment platform not working with some themes (not use JS window.onload property, use addEventListener/attachEvent functions instead).
* Bug fix: saving order correctly on IPN URL call in multisite mode.
* Replace deprecated code (that gets redirection to platform URL) to avoid notices in log file. 

= 1.2.1, 2015-05-19 =
* Not use jquery in frontend to avoid redirection problems in some sites.
* Trim spaces from data before sending form to payment platform.

= 1.2, 2015-02-19 =
* Single and multi payment in the same plug-in.
* Bug fix: when returning back to store on payment error or cancel.
* Improvement of text translations.
* Bug fix: avoid a warning displayed on HTTPS sites.
* Compatibility with WooCommerce version 2.3.

= 1.1a, 2014-07-02 =
* Compatibility with WooCommerce version 2.1.

= 1.1, 2013-10-21 =
* Add the parameter minimum amount to enable selective 3-DS.
* Reorganization of the configuration screen in module backend.
* Compatibility with the PayZen multi payment plug-in.

= 1.0a, 2013-05-15 =
* Use hooks to avoid the modification of WooCommerce files on the plug-in (re)install.

= 1.0, 2013-03-18 =
* Module creation.
