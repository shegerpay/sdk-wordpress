# ShegerPay WooCommerce Plugin

Accept Ethiopian bank payments in your WooCommerce store via ShegerPay automatic verification.

## Supported payment methods

- CBE (Commercial Bank of Ethiopia)
- Telebirr (Ethio Telecom mobile money)
- BOA (Bank of Abyssinia)
- Awash Bank

## Requirements

- WordPress 6.0+
- WooCommerce 6.0+
- PHP 7.4+
- ShegerPay API key — get one at [shegerpay.com/dashboard](https://shegerpay.com/dashboard)

## Installation

1. Copy the `shegerpay-woocommerce/` folder into your WordPress `wp-content/plugins/` directory.
2. In WP Admin, go to **Plugins** and activate **ShegerPay for WooCommerce**.
3. Go to **WooCommerce → Settings → Payments → ShegerPay**.
4. Paste your API key (starts with `sk_live_` for production or `sk_test_` for testing).
5. Optional: enable **ShegerPay Promo Codes** if you want to validate/redeem ShegerPay discount codes from WooCommerce checkout.
6. Write your payment instructions — tell customers which account to send money to and how to find their transaction ID.
7. Click **Save changes**.

## How it works

1. Customer selects "Pay with Ethiopian Bank" at checkout.
2. They complete a bank transfer to your account.
3. They copy their Transaction ID from the bank receipt (e.g. `FT26062K7WMY`).
4. They enter it in the checkout form and click Place Order.
5. ShegerPay calls the API to verify the transaction in real-time.
6. On success, the order is marked as Processing and the customer sees the Thank You page.
7. On failure, an error is shown so the customer can retry.

## Promo codes

WooCommerce coupons still work normally. If **ShegerPay Promo Codes** is enabled, the plugin also shows an optional ShegerPay promo-code field.

- The plugin validates the code server-side with your secret API key.
- The customer must pay the returned discounted amount exactly.
- After ShegerPay verifies the payment, the plugin redeems the promo code once using the WooCommerce order ID as the idempotency key.
- If redeem is retried for the same order/transaction, ShegerPay will not consume another use.

## Test mode

Enable **Test Mode** in the plugin settings and use a `sk_test_` API key. No real money is transferred in test mode.

## Getting an API key

1. Sign up at [shegerpay.com](https://shegerpay.com)
2. Go to Dashboard → API Keys
3. Create a new key (test or live)
4. Paste it in the plugin settings

## File structure

```
shegerpay-woocommerce/
├── shegerpay-woocommerce.php   Main plugin file
├── readme.txt                  WordPress plugin readme
├── assets/                     Logos and static assets
└── includes/
    ├── class-shegerpay-api.php     API client (wp_remote_post wrapper)
    └── class-shegerpay-gateway.php WooCommerce payment gateway class
```

## Support

- Documentation: https://shegerpay.com/docs
- Email: support@shegerpay.com
