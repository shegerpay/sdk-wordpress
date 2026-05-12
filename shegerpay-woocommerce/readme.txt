=== ShegerPay for WooCommerce ===
Contributors: shegerpay
Tags: woocommerce, payment, ethiopia, cbe, telebirr, boa, awash
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT

Accept Ethiopian bank payments in your WooCommerce store via ShegerPay automatic verification.

== Description ==

ShegerPay for WooCommerce lets Ethiopian merchants accept payments from:
* **CBE** (Commercial Bank of Ethiopia)
* **Telebirr** (Ethio Telecom mobile money)
* **BOA** (Bank of Abyssinia)
* **Awash Bank**

Customers transfer to your bank account, enter their Transaction ID at checkout, and ShegerPay automatically verifies it in real-time.

**No redirect. No third-party checkout. Payments stay in your bank.**

= How it works =

1. Customer selects "Pay with Ethiopian Bank" at checkout
2. They transfer the exact amount to your bank account
3. They enter their Transaction ID (e.g. FT26062K7WMY)
4. ShegerPay verifies the payment automatically
5. Order is confirmed instantly

= Requirements =

* ShegerPay account — get your API key at https://shegerpay.com
* WooCommerce 6.0+
* PHP 7.4+

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/
2. Activate from WP Admin → Plugins
3. Go to WooCommerce → Settings → Payments → ShegerPay
4. Enter your ShegerPay API key
5. Configure payment instructions (which account to send to)
6. Save — done!

== Frequently Asked Questions ==

= Where do I get an API key? =
Sign up at shegerpay.com and find your key in Dashboard → API Keys.

= Does this work without WooCommerce? =
No. This plugin requires WooCommerce.

= Is there a test mode? =
Yes. Enable test mode in settings and use test API keys (sk_test_...).

= Which banks are supported? =
CBE (Commercial Bank of Ethiopia), Telebirr (Ethio Telecom), BOA (Bank of Abyssinia), and Awash Bank.

== Changelog ==

= 1.0.0 =
* Initial release
* CBE, Telebirr, BOA, Awash verification
* WooCommerce HPOS compatible
* Test mode support
