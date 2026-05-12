<?php
if (!defined('ABSPATH')) exit;

class ShegerPay_API {
    private string $api_key;
    private string $base_url = 'https://api.shegerpay.com';

    public function __construct(string $api_key) {
        $this->api_key = $api_key;
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

    private function post(string $path, array $body): array {
        $response = wp_remote_post($this->base_url . $path, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
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
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key],
            'timeout' => 30,
        ]);
        if (is_wp_error($response)) throw new Exception($response->get_error_message());
        return json_decode(wp_remote_retrieve_body($response), true) ?? [];
    }
}
