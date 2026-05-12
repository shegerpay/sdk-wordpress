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
        $this->instructions = $this->get_option('instructions');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
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
        echo '<label>' . esc_html__('Transaction ID', 'shegerpay-woocommerce') . ' <span class="required">*</span></label>';
        echo '<input id="shegerpay_transaction_id" name="shegerpay_transaction_id" type="text" placeholder="e.g. FT26062K7WMY or AB12CD34EF" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;" />';
        echo '<small style="color:#666;">' . esc_html__('Enter the transaction reference from your bank/Telebirr receipt.', 'shegerpay-woocommerce') . '</small>';
        echo '</div>';
    }

    public function validate_fields(): bool {
        if (empty($_POST['shegerpay_transaction_id'])) {
            wc_add_notice(__('Please enter your Transaction ID.', 'shegerpay-woocommerce'), 'error');
            return false;
        }
        return true;
    }

    public function process_payment($order_id): array {
        $order          = wc_get_order($order_id);
        $transaction_id = sanitize_text_field($_POST['shegerpay_transaction_id'] ?? '');
        $amount         = (float) $order->get_total();

        if (empty($transaction_id)) {
            wc_add_notice(__('Transaction ID is required.', 'shegerpay-woocommerce'), 'error');
            return ['result' => 'failure'];
        }

        if (empty($this->api_key)) {
            wc_add_notice(__('ShegerPay is not configured. Please contact the store owner.', 'shegerpay-woocommerce'), 'error');
            return ['result' => 'failure'];
        }

        try {
            $api    = new ShegerPay_API($this->api_key);
            $result = $api->verify($transaction_id, $amount);

            $is_verified = ($result['verified'] ?? false) || ($result['status'] ?? '') === 'verified' || ($result['valid'] ?? false);

            if ($is_verified) {
                $order->payment_complete($transaction_id);
                $order->add_order_note(sprintf(
                    'ShegerPay: Payment verified. TX: %s, Provider: %s, Amount: %.2f ETB',
                    $transaction_id,
                    $result['provider'] ?? 'unknown',
                    $result['amount'] ?? $amount
                ));
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
