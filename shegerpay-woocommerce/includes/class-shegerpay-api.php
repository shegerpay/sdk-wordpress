<?php
if (!defined('ABSPATH')) exit;

class ShegerPay_API {
    private string $api_key;
    private string $base_url;

    public function __construct(string $api_key, bool $test_mode = false) {
        $this->api_key  = $api_key;
        $this->base_url = $test_mode
            ? 'https://sandbox.api.shegerpay.com'
            : 'https://api.shegerpay.com';
    }

    public function verify(string $transaction_id, ?float $amount = null, ?string $provider = null): array {
        $body = ['transaction_id' => $transaction_id];
        if ($provider !== null) $body['provider'] = $provider;
        if ($amount !== null) $body['amount'] = $amount;
        return $this->post('/api/v1/verify', $body);
    }

    public function get_payment_link(string $link_id): array {
        return $this->get('/api/v1/payment-links/' . $link_id);
    }

    public function validate_promo_code(string $code, float $amount, ?string $customer = null): array {
        return $this->post('/api/v1/promo-codes/validate', [
            'code' => $code,
            'amount' => $amount,
            'customer_identifier' => $customer,
        ]);
    }

    public function redeem_promo_code(string $code, float $amount, string $transaction_id, ?string $order_id = null, ?string $customer = null): array {
        return $this->post('/api/v1/promo-codes/redeem', [
            'code' => $code,
            'amount' => $amount,
            'transaction_id' => $transaction_id,
            'order_id' => $order_id,
            'idempotency_key' => $order_id ? 'woocommerce:' . $order_id . ':' . strtoupper($code) : null,
            'customer_identifier' => $customer,
        ]);
    }

    private function post(string $path, array $body): array {
        $response = wp_remote_post($this->base_url . $path, [
            'headers' => [
                'X-API-Key'     => $this->api_key,
                'Content-Type'  => 'application/json',
                'X-SDK-Version' => 'wordpress/1.0.0',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 400) {
            throw new Exception($data['detail'] ?? $data['message'] ?? 'ShegerPay API error');
        }

        return $data ?? [];
    }

    private function get(string $path): array {
        $response = wp_remote_get($this->base_url . $path, [
            'headers' => [
                'X-API-Key'     => $this->api_key,
                'X-SDK-Version' => 'wordpress/1.0.0',
            ],
            'timeout' => 30,
        ]);
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code >= 400) {
            throw new Exception($data['detail'] ?? $data['message'] ?? 'ShegerPay API error');
        }
        return $data ?? [];
    }
}
