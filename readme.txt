=== PayZen for WooCommerce ===
Contributors: Lyra Network, Alsacréations
Tags: payment, PayZen, gateway, checkout, credit card, bank card, e-commerce
Requires at least: 3.5
Tested up to: 6.4
WC requires at least: 2.0
WC tested up to: 8.4
Stable tag: 1.12.0
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

= 1.12.0, 2023-12-26=
* New management of WooCommerce subscriptions.
* Added compatibility with WooCommerce Blocks for all payment modes.
* Added compatibility with High-Performance Order Storage (HPOS).
* Handle refund of a split payment.
* [embedded] Improve payment form token refresh management.
* [technical] Improve features management by plugin variant.
* Fix redirection page display with some WordPress themes.
* Bug Fix: Fix PHP warnings related to deprecated creation of dynamic properties.

= 1.11.1, 2023-10-26=
* Fix compatibility with some WordPress 6.3.x themes.
* [smartform] Send capture delay parameter in case of Smartform.
* Update all domains payment means.
* Update all domains supported currencies.

= 1.11.0, 2023-09-15=
* [smartform] Smartform integration.
* [embedded] Bug fix: Fix error related to validation mode.
* Added compatibility with "Woocommerce Blocks" for payment in redirection.
* Added compatibility with "CheckoutWC".
* Added possibility to delete order on failed payment.
* Update list of supported payment means.
* Update list of supported currencies.

1.10.8, 2023-07-10=
* [franfinance] Bug fix: Saving min and max amount of the payment options correctly for Franfinance payment method.
* [subscr] Bug fix: Do not update or cancel subscription processed by another payment method.
* Some code fixes.

1.10.7, 2023-04-19=
* Bug fix: Fix error related to customer form validation in embedded and iframe modes.
* Bug fix: Fix verification of presence of WC Blocks in checkout page.
* Bug fix: Fix SDK autoload for compatibility with PHP 8.2.

1.10.6, 2023-04-12=
* Bug fix: Fix error related to displaying order details.

1.10.5, 2023-04-11=
* Fix compatibility with WooCommerce 7.5.x versions.
* Fix "WooCommerce Blocks" support in standard redirection payment.
* [embedded] Bug fix: Fix number of payment attempts in case of rejected payment.
* Bug fix: Fix error related to displaying multiple payment forms before redirection.
* Improve module documentation management field.

= 1.10.4, 2023-02-10 =
* Added new transaction statuses PENDING and PARTIALLY_AUTHORISED.
* Some code fixes.

= 1.10.3, 2022-12-30 =
* Added compatibility with "Brazilian Market on WooCommerce".
* Update list of supported payment means.
* Update list of supported currencies.

= 1.10.2, 2022-09-08 =
* Improve some translations.

= 1.10.1, 2022-08-26 =
* Bug fix: Fix payment methods for orders created from WooCommerce Back Office.
* [embedded] Bug fix: Fix return to shop and IPN management in multi-site environment in case of payment with embedded fields.
* Added Portuguese translation.
* Display warning messages if no subscription solution is available.
* Bug fix: Handle uncatched exception when choosing custom subscription management option.
* Added new filter to get list of custom order statuses.

= 1.10.0, 2022-06-29 =
* [subscr] Do not process subscription if there is no renewal date.
* [embedded] Bug fix: Do not refresh payment page automatically after an unrecoverable error.
* [franfinance] Send information about shipping method for Franfinance payment method.
* Added shipping options configuration field.
* Support for "Woocommerce Blocks" in standard redirection payment.
* Bug fix: Fix payment methods display based on amount restrictions for orders created from WooCommerce Back Office.
* Possibility to make refunds for payments.

= 1.9.5, 2022-05-05 =
* Update list of supported payment means.

= 1.9.4, 2021-09-27 =
* Some minor fixes.
* [subscr] Bug fix: Fix subscription next payment date.
* [embedded] Bug fix: Fix wrapping payment result for embedded payment.

= 1.9.3, 2021-07-15 =
* [subscr] Bug fix: Fix subscription renewal process (create a renewal order).
* Display installments number in order details when it is available.

= 1.9.2, 2021-07-06 =
* Improve subscription cancellation process (cancel web service is called on buyer action).
* Display authorized amount in order details when it is available.

= 1.9.1, 2021-06-21 =
* Bug fix: Do not create two transactions when trial is disabled for a subscription.
* Bug fix: Fatal error when modifying payment for a subscription in My account > subscriptions.
* Bug fix: Propose dynamically added payment means in "Other payment means" section.
* Bug fix: Propose subscription payment method when client account creation during checkout is enabled.
* Bug fix: Adjust rrule for dates at the end of the month when creating subscriptions.
* Manage retrocompatibility with already validated orders (do not check order key) when processing subscriptions.
* Manage subscription creation from gateway Back Office.
* Improve error management on subscription actions (cancel and update).
* Send the relevant part of the current PHP version in vads_contrib field.
* Improve support e-mails display.

= 1.9.0, 2021-04-21 =
* [subscr] Manage subscriptions with WooCommerce Subscriptions (including subscription update and cancellation).
* [franfinance] Added new Franfinance submodule.
* Possibility to open support issue from the plugin configuration panel or an order details page.
* Reorganize plugin settings (REST API keys section moved to general configuration).
* Possibility to configure REST API URLs.
* Possibility to add payment means dynamically in "Other payment means" section.
* [embedded] Add pop-in choice to card data entry mode field.
* [embedded] Possibility to customize "Register my card" checkbox label.
* Possibility to configure description for popin and iframe modes.
* [alias] Display the brand of the registered means of payment in payment by alias.
* [alias] Added possibility to delete registered payment means.
* [alias] Check alias validity before proceeding to payment.
* Do not use vads_order_info* gateway parameter (use vads_ext_info* instead).
* Update 3DS management option description.

= 1.8.10, 2021-03-05 =
* Save 3DS authentication status and certificate as an order note.
* Use online payment means logos.

= 1.8.9, 2020-12-23 =
* Bug fix: Reorder dynamically added payment means wehen not grouped.
* Restore compatibility with WooCommerce 2.x versions.
* Display warning message on payment in iframe mode enabling.

= 1.8.8, 2020-12-16 =
* Bug fix: Error 500 due to obsolete function (get_magic_quotes_gpc) in PHP 7.4.

= 1.8.7, 2020-10-30 =
* [embedded] Bug fix: Force redirection when there is an error in payment form token creation.
* [embedded] Bug fix: Embedded payment fields not correctly displayed since the last gateway JS library delivery.
* Fix standard payment description management.

= 1.8.6, 2020-10-12 =
* Bug fix: Fix IPN management on cancellation notification for orders in on-hold status.

= 1.8.5, 2020-09-02 =
* [embedded] Bug fix: Error 500 due to riskControl modified format in REST response.
* [embedded] Bug fix: Compatibility of payment with embedded fields with Internet Explorer 11.
* [embedded] Bug fix: Error due to strongAuthenticationState field renaming in REST token creation.
* Update payment means logos.

= 1.8.4, 2020-06-14 =
* Improve plugin translations.

= 1.8.3, 2020-05-21 =
* [embedded] Bug fix: Payment by embedded fields error relative to new JavaScript client library.
* [embedded] Bug fix: Manage new metadata field format returned in REST API IPN.
* [subscr] Bug fix: Fatal error in subscription submodule before redirection.
* [alias] Display confirmation message on payment by token enabling.

= 1.8.2, 2020-03-16 =
* Bug fix: Manage products with zero amount in tax calculation.
* [alias] Bug fix: Payment by alias available only for logged in users.
* Bug fix: Skip confirmation alert after clicking on payment button with IFRAME and REST modes (on WooCommerce >= v3.9).
* Bug fix: Exit script after redirection to cart URL in error cases.
* Fix errors (NOTICE level) when retrieving some configuration fields.
* [embedded] Fix embedded payment fields display in WooCommerce v3.9 (relative to WooCommerce issue #24271).

= 1.8.1, 2019-12-23 =
* Bug fix: update order by IPN call when many attempts option is enabled.

= 1.8.0, 2019-11-20 =
* Possibility to dynamically propose new payment means (only by redirection).
* [embedded] Added feature embedded payment fields (directly on site or in a pop-in) using REST API.
* Improve plugin translations.
* Added support of payment by subscription with Subcriptio plugin in a new submodule (needs activation in source code).

= 1.7.1, 2019-04-01 =
* Fix some plugin translations.
* Do not use vads_order_info2 gateway parameter.
* Bug fix: cannot re-order after a cancelled payment in iframe mode.

= 1.7.0, 2019-02-04 =
* Fix error in shipping amount calculation (on some WooCommerce 2.x versions).
* Improve payment error display on order details and hide message in order email.
* Added payment by token (requires PayZen payment by token option).
* Added possibility to restrict payment submodules to specific countries.
* Manage successful order statuses dynamically to support custom statuses.
* Redirect buyer to cart page (instead of checkout page) after a failed payment.
* Display error messages and notices in WooCommerce 3.5.
* Added API to manage subscriptions payment integration (for developpers).

= 1.6.2, 2018-11-26 =
* Fix new signature algorithm name (HMAC-SHA-256).
* Update payment means logos.
* [prodfaq] Fix notice about shifting the shop to production mode.
* Added Spanish translation.
* Improve iframe mode interface.
* Allow comma when entering amounts in configuration fields.
* [klarna] Send product amounts including taxes for Klarna payments.
* Send shipping fees in vads_shipping_amount variable.

= 1.6.1, 2018-07-06 =
* [shatwo] Enable HMAC-SHA-256 signature algorithm by default.
* Ignore spaces at the beginning and end of certificates when calculating the return signature.

= 1.6.0, 2018-05-23 =
* Enable signature algorithm selection (SHA-1 or HMAC-SHA-256).
* Improve plugin configuration screen.

= 1.5.0, 2018-03-26 =
* Bug fix: error relative to missing shipping phone number.
* [klarna] New Klarna submodule.
* Improve dropdown lists in plugin configuration panel (only in WooCommerce 3.x).
* Added payment in pop-in using iframe mode.
* Display card brand user choice if any in WooCommerce Back Office order details.
* Improve compatibility of plugin with WooCommerce 2.x and 3.x versions.
* Improve management of fatal errors as wrong signature, order not found and inconsistent statuses.
* Manage pending payments by putting orders in "On hold" status.
* Added validation mode and capture delay configuration fields to submodules.

= 1.4.1, 2017-10-09 =
* Bug fix: check cart and customer when checking payment method is available (to avoid errors with WooCommerce Subscriptions).

= 1.4.0, 2017-09-11 =
* Bug fix: allow plugin installation on multisite WordPress gateway.
* Fix notice when card type selection on merchant website option is not used.
* Fix warning in order e-mail sent to buyer relative to empty transaction ID.
* Send delivery phone number to payment gateway.

= 1.3.2, 2017-05-03 =
* Rename referenced directory in code to match new root plugin directory.
* Rename translation domain name.

= 1.3.1, 2017-05-01 =
* [multi] Bug fix: consider contract entered in multiple payment options configuration.
* Ability to propose the card type choice on the WooCommerce frontend.
* Compatibility with WPML translation plugin (module lets WPML translate gateway title and description if installed).
* Compatibility with new WooCommerce versions (3.x).
* Display multilingual field values in website locale by default.

= 1.3.0, 2016-11-15 =
* Using multilingual fields for method title and description and for redirection messages (WordPress 4.0.0 or higher).
* Correction of some text translations.
* Ability to configure order status on payment success.
* Replace deprecated code.
* Remove control over certificate format modified on the gateway.
* Correction of an error to make plugin compatible with WooCommerce 2.6.
* Save payment result sent from payment gateway and send it to customer by mail.

= 1.2.4, 2016-06-01 =
* Adding German translation file.

= 1.2.3, 2015-07-09 =
* Bug fix: when IPN URL call in monosite mode.

= 1.2.2, 2015-06-25 =
* Bug fix: automatic redirection to payment gateway not working with some themes (not use JS window.onload property, use addEventListener/attachEvent functions instead).
* Bug fix: saving order correctly on IPN URL call in multisite mode.
* Replace deprecated code (that gets redirection to gateway URL) to avoid notices in log file.

= 1.2.1, 2015-05-19 =
* Not use jQuery in frontend to avoid redirection problems in some sites.
* Trim spaces from data before sending form to payment gateway.

= 1.2, 2015-02-19 =
* Bug fix: when returning back to store on payment error or cancel.
* Bug fix: avoid a warning displayed on HTTPS sites.
* [multi] Single and multi payment in the same plugin.
* Improvement of text translations.
* Compatibility with WooCommerce version 2.3.

= 1.1a, 2014-07-02 =
* Compatibility with WooCommerce version 2.1.

= 1.1, 2013-10-21 =
* Add the parameter minimum amount to enable selective 3DS.
* Reorganization of the configuration screen in WooCommerce Back Office.
* [multi] Compatibility with the PayZen multi payment plugin.

= 1.0a, 2013-05-15 =
* Use hooks to avoid the modification of WooCommerce files on plugin (re)install.

= 1.0, 2013-03-18 =
* Plugin creation.

--------
Generated automatically from CHANGELOG.md.