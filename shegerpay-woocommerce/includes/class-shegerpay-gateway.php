<?php
if (!defined('ABSPATH')) exit;

class WC_ShegerPay_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'shegerpay';
        $this->icon               = SHEGERPAY_PLUGIN_URL . 'assets/logo.png';
        $this->has_fields         = true;
        $this->method_title       = 'ShegerPay';
        $this->method_description = 'Accept Ethiopian bank payments via CBE, Telebirr, BOA and Awash through ShegerPay verification.';
        $this->supports           = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option('title', 'Pay with Ethiopian Bank');
        $this->description  = $this->get_option('description', 'Pay via CBE, Telebirr, BOA, or Awash. Enter your transaction ID after payment.');
        $this->api_key      = $this->get_option('api_key');
        $this->test_mode    = 'yes' === $this->get_option('test_mode', 'no');
        $this->enable_promos = 'yes' === $this->get_option('enable_promos', 'no');
        $this->instructions = $this->get_option('instructions');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
        add_filter('woocommerce_checkout_form_tag', [$this, 'add_checkout_form_enctype']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable ShegerPay',
                'default' => 'yes',
            ],
            'title' => [
                'title'   => 'Title',
                'type'    => 'text',
                'default' => 'Pay with Ethiopian Bank',
            ],
            'description' => [
                'title'   => 'Description',
                'type'    => 'textarea',
                'default' => 'Pay via CBE, Telebirr, BOA or Awash bank transfer.',
            ],
            'api_key' => [
                'title'       => 'API Key',
                'type'        => 'password',
                'description' => 'Get your API key from shegerpay.com/dashboard',
            ],
            'test_mode' => [
                'title'   => 'Test Mode',
                'type'    => 'checkbox',
                'label'   => 'Enable test mode',
                'default' => 'yes',
            ],
            'enable_promos' => [
                'title'       => 'ShegerPay Promo Codes',
                'type'        => 'checkbox',
                'label'       => 'Allow customers to enter ShegerPay promo codes',
                'description' => 'Uses your secret API key server-side to validate before verification and redeem once after a verified payment. WooCommerce coupons still work normally.',
                'default'     => 'no',
            ],
            'instructions' => [
                'title'       => 'Payment Instructions',
                'type'        => 'textarea',
                'description' => 'Instructions shown to customers. Tell them which account to send to.',
                'default'     => "1. Transfer the exact amount to our CBE account: 1000XXXXXXXXX\n2. Copy your Transaction ID (e.g. FT26XXXXXXXX)\n3. Enter it below and click Place Order.",
            ],
        ];
    }

    public function payment_fields() {
        if ($this->description) {
            echo '<p>' . wp_kses_post($this->description) . '</p>';
        }
        if ($this->instructions) {
            echo '<div class="shegerpay-instructions" style="background:#f8f8f8;padding:12px;border-radius:6px;margin:8px 0;font-size:13px;">';
            echo '<strong>How to pay:</strong><br>';
            echo nl2br(wp_kses_post($this->instructions));
            echo '</div>';
        }
        echo '<div class="form-row form-row-wide">';
        echo '<label>' . esc_html__('Payment Method', 'shegerpay-woocommerce') . ' <span class="required">*</span></label>';
        echo '<select id="shegerpay_provider" name="shegerpay_provider" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">';
        $providers = [
            'cbe' => 'CBE',
            'telebirr' => 'Telebirr',
            'boa' => 'Bank of Abyssinia',
            'awash' => 'Awash',
            'dashen' => 'Dashen',
            'ebirr' => 'eBirr',
        ];
        foreach ($providers as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div class="form-row form-row-wide">';
        echo '<label>' . esc_html__('Transaction ID, SMS text, or receipt link', 'shegerpay-woocommerce') . '</label>';
        echo '<input id="shegerpay_transaction_id" name="shegerpay_transaction_id" type="text" placeholder="e.g. FT26062K7WMY, Telebirr ref, BOA slip URL, or full SMS" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;" />';
        echo '<small style="color:#666;">' . esc_html__('You can enter proof here or upload a receipt below. BOA also needs sender account.', 'shegerpay-woocommerce') . '</small>';
        echo '</div>';

        echo '<div class="form-row form-row-wide">';
        echo '<label>' . esc_html__('Sender Account (BOA only)', 'shegerpay-woocommerce') . '</label>';
        echo '<input id="shegerpay_sender_account" name="shegerpay_sender_account" type="text" placeholder="Sender account used to pay" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;" />';
        echo '</div>';

        echo '<div class="form-row form-row-wide">';
        echo '<label>' . esc_html__('Receipt Image/PDF', 'shegerpay-woocommerce') . '</label>';
        echo '<input id="shegerpay_receipt_file" name="shegerpay_receipt_file" type="file" accept="image/*,.pdf" />';
        echo '<small style="color:#666;display:block;">' . esc_html__('Optional OCR proof. If uploaded, transaction ID can be left empty for supported providers.', 'shegerpay-woocommerce') . '</small>';
        echo '</div>';
        if ($this->enable_promos) {
            echo '<div class="form-row form-row-wide">';
            echo '<label>' . esc_html__('ShegerPay Promo Code', 'shegerpay-woocommerce') . '</label>';
            echo '<input id="shegerpay_promo_code" name="shegerpay_promo_code" type="text" placeholder="SAVE20" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;text-transform:uppercase;" />';
            echo '<small style="color:#666;">' . esc_html__('Optional. The store verifies the final discounted amount with ShegerPay.', 'shegerpay-woocommerce') . '</small>';
            echo '</div>';
        }
    }

    public function validate_fields(): bool {
        $transaction_id = trim((string) ($_POST['shegerpay_transaction_id'] ?? ''));
        $provider = sanitize_text_field($_POST['shegerpay_provider'] ?? '');
        $has_file = !empty($_FILES['shegerpay_receipt_file']['tmp_name'] ?? '');
        if (empty($provider)) {
            wc_add_notice(__('Please choose a ShegerPay payment method.', 'shegerpay-woocommerce'), 'error');
            return false;
        }
        if ($provider === 'boa' && empty($_POST['shegerpay_sender_account'] ?? '')) {
            wc_add_notice(__('BOA payments require the sender account number.', 'shegerpay-woocommerce'), 'error');
            return false;
        }
        if ($transaction_id === '' && !$has_file) {
            wc_add_notice(__('Please enter a transaction proof or upload the receipt image/PDF.', 'shegerpay-woocommerce'), 'error');
            return false;
        }
        return true;
    }

    public function add_checkout_form_enctype($form_tag) {
        if (false === strpos($form_tag, 'enctype=')) {
            $form_tag = str_replace('<form', '<form enctype="multipart/form-data"', $form_tag);
        }
        return $form_tag;
    }

    public function process_payment($order_id): array {
        $order          = wc_get_order($order_id);
        $transaction_id = sanitize_text_field($_POST['shegerpay_transaction_id'] ?? '');
        $provider       = sanitize_text_field($_POST['shegerpay_provider'] ?? '');
        $sender_account = sanitize_text_field($_POST['shegerpay_sender_account'] ?? '');
        $promo_code     = strtoupper(sanitize_text_field($_POST['shegerpay_promo_code'] ?? ''));
        $amount         = (float) $order->get_total();
        $receipt_file   = $_FILES['shegerpay_receipt_file']['tmp_name'] ?? '';

        if (empty($transaction_id) && empty($receipt_file)) {
            wc_add_notice(__('Transaction proof or receipt upload is required.', 'shegerpay-woocommerce'), 'error');
            return ['result' => 'failure'];
        }

        if (empty($this->api_key)) {
            wc_add_notice(__('ShegerPay is not configured. Please contact the store owner.', 'shegerpay-woocommerce'), 'error');
            return ['result' => 'failure'];
        }

        try {
            $api    = new ShegerPay_API($this->api_key, $this->test_mode);
            $verified_amount = $amount;
            $promo_result = null;
            if ($this->enable_promos && !empty($promo_code)) {
                $promo_result = $api->validate_promo_code($promo_code, $amount, $order->get_billing_email(), $provider);
                if (empty($promo_result['valid']) && empty($promo_result['ok'])) {
                    wc_add_notice(__('Promo code is not valid for this order.', 'shegerpay-woocommerce'), 'error');
                    return ['result' => 'failure'];
                }
                $verified_amount = (float) ($promo_result['discounted_amount'] ?? $amount);
            }
            if (!empty($receipt_file)) {
                $result = $api->verify_image_file($receipt_file, $verified_amount, $provider, $transaction_id, $sender_account);
            } else {
                $result = $api->verify($transaction_id, $verified_amount, $provider, $sender_account);
            }

            $is_verified = ($result['verified'] ?? false) || ($result['status'] ?? '') === 'verified' || ($result['valid'] ?? false);

            if ($is_verified) {
                $verified_tx = $result['transaction_id'] ?? $transaction_id ?: ('shegerpay-' . $order_id);
                $order->payment_complete($verified_tx);
                $order->add_order_note(sprintf(
                    'ShegerPay: Payment verified. TX: %s, Provider: %s, Amount: %.2f ETB',
                    $verified_tx,
                    $result['provider'] ?? $provider ?: 'unknown',
                    $result['amount'] ?? $verified_amount
                ));
                if ($promo_result && !empty($promo_code)) {
                    try {
                        $redeem_tx = $result['transaction_id'] ?? $transaction_id ?: ('woo-' . $order_id);
                        $api->redeem_promo_code($promo_code, $amount, $redeem_tx, (string) $order_id, $order->get_billing_email(), $provider);
                        $order->add_order_note(sprintf(
                            'ShegerPay promo applied: %s. Gross: %.2f ETB, verified discounted amount: %.2f ETB.',
                            $promo_code,
                            $amount,
                            $verified_amount
                        ));
                    } catch (Exception $promo_error) {
                        $order->add_order_note('ShegerPay promo redeem warning: ' . $promo_error->getMessage());
                    }
                }
                WC()->cart->empty_cart();
                return [
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order),
                ];
            } else {
                $reason = $result['reason'] ?? $result['message'] ?? 'Could not verify payment.';
                wc_add_notice(
                    sprintf(
                        __('Payment verification failed: %s Please check your transaction ID and try again.', 'shegerpay-woocommerce'),
                        esc_html($reason)
                    ),
                    'error'
                );
                $order->add_order_note('ShegerPay: Verification failed for TX: ' . $transaction_id . '. Reason: ' . $reason);
                return ['result' => 'failure'];
            }
        } catch (Exception $e) {
            wc_add_notice(
                sprintf(__('Payment error: %s', 'shegerpay-woocommerce'), esc_html($e->getMessage())),
                'error'
            );
            $order->add_order_note('ShegerPay: API error — ' . $e->getMessage());
            return ['result' => 'failure'];
        }
    }

    public function thankyou_page($order_id) {
        echo '<div class="shegerpay-thankyou" style="background:#f0f9f0;padding:16px;border-radius:8px;margin:16px 0;">';
        echo '<p><strong>' . esc_html__('Payment verified by ShegerPay.', 'shegerpay-woocommerce') . '</strong> ' . esc_html__('Your order is being processed.', 'shegerpay-woocommerce') . '</p>';
        echo '</div>';
    }
}
