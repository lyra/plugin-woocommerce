1.7.1, 2019-04-01:
- Fix some plugin translations.
- Do not use vads\_order\_info2 gateway parameter.
- Bug fix: cannot re-order after a cancelled payment in iframe mode.

1.7.0, 2019-02-04:
- Fix error in shipping amount calculation (on some WooCommerce 2.x versions).
- Improve payment error display on order details and hide message in order email.
- Added payment by token (requires PayZen payment by token option).
- Added possibility to restrict payment submodules to specific countries.
- Manage successful order statuses dynamically to support custom statuses.
- Redirect buyer to cart page (instead of checkout page) after a failed payment.
- Display error messages and notices in WooCommerce 3.5.
- Added API to manage subscriptions payment integration (for developpers).

1.6.2, 2018-11-26:
- Fix new signature algorithm name (HMAC-SHA-256).
- Update payment means logos.
- [prodfaq] Fix notice about shifting the shop to production mode.
- Added Spanish translation.
- Improve iframe mode interface.
- Allow comma when entering amounts in configuration fields.
- [klarna] Send product amounts including taxes for Klarna payments.
- Send shipping fees in vads\_shipping\_amount variable.

1.6.1, 2018-07-06:
- [shatwo] Enable HMAC-SHA-256 signature algorithm by default.
- Ignore spaces at the beginning and end of certificates when calculating the return signature.

1.6.0, 2018-05-23:
- Enable signature algorithm selection (SHA-1 or HMAC-SHA-256).
- Improve backend configuration screen.

1.5.0, 2018-03-26:
- Bug fix: error relative to missing shipping phone number.
- [klarna] New Klarna submodule.
- Improve dropdown lists in module backend (only in WooCommerce 3.x).
- Added payment in pop-in using iframe mode.
- Display card brand user choice if any in backend order details.
- Improve compatibility of plugin with WooCommerce 2.x and 3.x versions.
- Improve management of fatal errors as wrong signature, order not found and inconsistent statuses.
- Manage pending payments by putting orders in "On hold" status.
- Added validation mode and capture delay configuration fields to submodules.

1.4.1, 2017-10-09:
- Bug fix: check cart and customer when checking payment method is available (to avoid errors with WooCommerce Subscriptions).

1.4.0, 2017-09-11:
- Bug fix: allow plugin installation on multisite WordPress gateway.
- Fix notice when card type selection on merchant website option is not used.
- Fix warning in order e-mail sent to buyer relative to empty transaction ID.
- Send delivery phone number to payment gateway.

1.3.2, 2017-05-03:
- Rename referenced directory in code to match new root plugin directory.
- Rename translation domain name.

1.3.1, 2017-05-01:
- [multi] Bug fix: consider contract entered in multiple payment options configuration.
- Ability to propose the card type choice on the WooCommerce frontend.
- Compatibility with WPML translation plugin (module lets WPML translate gateway title and description if installed).
- Compatibility with new WooCommerce versions (3.x).
- Display multilingual field values in website locale by default.

1.3.0, 2016-11-15:
- Using multilingual fields for method title and description and for redirection messages (WordPress 4.0.0 or higher).
- Correction of some text translations.
- Ability to configure order status on payment success.
- Replace deprecated code.
- Remove control over certificate format modified on the gateway.
- Correction of an error to make module compatible with WooCommerce 2.6.
- Save payment result sent from payment gateway and send it to customer by mail.

1.2.4, 2016-06-01:
- Adding German translation file.

1.2.3, 2015-07-09:
- Bug fix: when IPN URL call in monosite mode.

1.2.2, 2015-06-25:
- Bug fix: automatic redirection to payment gateway not working with some themes (not use JS window.onload property, use addEventListener/attachEvent functions instead).
- Bug fix: saving order correctly on IPN URL call in multisite mode.
- Replace deprecated code (that gets redirection to gateway URL) to avoid notices in log file.

1.2.1, 2015-05-19:
- Not use jquery in frontend to avoid redirection problems in some sites.
- Trim spaces from data before sending form to payment gateway.

1.2, 2015-02-19:
- Bug fix: when returning back to store on payment error or cancel.
- Bug fix: avoid a warning displayed on HTTPS sites.
- [multi] Single and multi payment in the same plugin.
- Improvement of text translations.
- Compatibility with WooCommerce version 2.3.

1.1a, 2014-07-02:
- Compatibility with WooCommerce version 2.1.

1.1, 2013-10-21:
- Add the parameter minimum amount to enable selective 3DS.
- Reorganization of the configuration screen in module backend.
- [multi] Compatibility with the PayZen multi payment plugin.

1.0a, 2013-05-15:
- Use hooks to avoid the modification of WooCommerce files on the plugin (re)install.

1.0, 2013-03-18:
- Module creation.