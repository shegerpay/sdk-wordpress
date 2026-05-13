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

    public function verify(string $transaction_id, ?float $amount = null, ?string $provider = null, ?string $sender_account = null): array {
        $body = ['transaction_id' => $transaction_id];
        if ($provider !== null) $body['provider'] = $provider;
        if ($amount !== null) $body['amount'] = $amount;
        if ($sender_account !== null) $body['sender_account'] = $sender_account;
        return $this->post('/api/v1/verify', $body);
    }

    public function verify_image_file(string $file_path, ?float $amount = null, ?string $provider = null, ?string $transaction_id = null, ?string $sender_account = null): array {
        if (!function_exists('curl_file_create')) {
            throw new Exception('Receipt upload requires PHP cURL file upload support.');
        }
        if (!is_readable($file_path)) {
            throw new Exception('Receipt file is not readable.');
        }

        $body = ['screenshot' => curl_file_create($file_path)];
        if ($amount !== null) $body['amount'] = (string) $amount;
        if ($provider !== null) $body['provider'] = $provider;
        if ($transaction_id !== null && $transaction_id !== '') $body['transaction_id'] = $transaction_id;
        if ($sender_account !== null && $sender_account !== '') $body['sender_account'] = $sender_account;

        return $this->multipart('/api/v1/verify-image', $body);
    }

    public function get_payment_link(string $link_id): array {
        return $this->get('/api/v1/payment-links/' . $link_id);
    }

    public function validate_promo_code(string $code, float $amount, ?string $customer = null, ?string $provider = null): array {
        return $this->post('/api/v1/promo-codes/validate', [
            'code' => $code,
            'amount' => $amount,
            'customer_identifier' => $customer,
            'provider' => $provider,
        ]);
    }

    public function redeem_promo_code(string $code, float $amount, string $transaction_id, ?string $order_id = null, ?string $customer = null, ?string $provider = null): array {
        return $this->post('/api/v1/promo-codes/redeem', [
            'code' => $code,
            'amount' => $amount,
            'transaction_id' => $transaction_id,
            'order_id' => $order_id,
            'idempotency_key' => $order_id ? 'woocommerce:' . $order_id . ':' . strtoupper($code) : null,
            'customer_identifier' => $customer,
            'provider' => $provider,
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

    private function multipart(string $path, array $body): array {
        $ch = curl_init($this->base_url . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $this->api_key,
                'X-SDK-Version: wordpress/1.0.0',
            ],
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new Exception($error ?: 'ShegerPay upload request failed');
        }
        $data = json_decode($raw, true);
        if ($code >= 400) {
            throw new Exception($data['detail'] ?? $data['message'] ?? 'ShegerPay API error');
        }
        return $data ?? [];
    }
}
