<?php
/**
 * Example: Manually verify a ShegerPay transaction in WordPress (outside WooCommerce)
 *
 * Drop this in your theme's functions.php or a custom plugin.
 * For WooCommerce integration, see sdk/wordpress/shegerpay-woocommerce/.
 */

// =====================================================
// Helper: verify a transaction via wp_remote_post
// =====================================================

function my_verify_payment( $transaction_id, $amount = null ) {
    $api_key = get_option( 'my_shegerpay_api_key' );

    $body = [ 'transaction_id' => sanitize_text_field( $transaction_id ) ];
    if ( ! is_null( $amount ) ) {
        $body['amount'] = floatval( $amount );
    }

    $response = wp_remote_post( 'https://api.shegerpay.com/api/v1/verify', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode( $body ),
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    return $data['verified'] ?? false;
}

// =====================================================
// Helper: create a payment link via wp_remote_post
// =====================================================

function my_create_payment_link( $title, $amount ) {
    $api_key = get_option( 'my_shegerpay_api_key' );

    $response = wp_remote_post( 'https://api.shegerpay.com/api/v1/payment-links', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode( [
            'title'           => sanitize_text_field( $title ),
            'amount'          => floatval( $amount ),
            'currency'        => 'ETB',
            'enable_cbe'      => true,
            'enable_telebirr' => true,
        ] ),
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        return null;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    return $data['url'] ?? null;
}

// =====================================================
// Form handler: verify payment submitted by a user
// =====================================================

add_action( 'init', function () {
    if ( ! isset( $_POST['shegerpay_verify_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shegerpay_verify_nonce'] ) ), 'shegerpay_verify' ) ) {
        wp_die( 'Security check failed.' );
    }

    $transaction_id = sanitize_text_field( $_POST['transaction_id'] ?? '' );
    $amount         = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : null;

    if ( empty( $transaction_id ) ) {
        return;
    }

    $verified = my_verify_payment( $transaction_id, $amount );

    if ( $verified ) {
        // Grant access, update order status, etc.
        update_user_meta( get_current_user_id(), 'payment_verified', true );
        wp_redirect( add_query_arg( 'payment', 'success', get_permalink() ) );
        exit;
    } else {
        wp_redirect( add_query_arg( 'payment', 'failed', get_permalink() ) );
        exit;
    }
} );

// =====================================================
// Webhook endpoint: receive ShegerPay events
// =====================================================

add_action( 'rest_api_init', function () {
    register_rest_route( 'shegerpay/v1', '/webhook', [
        'methods'             => 'POST',
        'callback'            => 'my_shegerpay_webhook_handler',
        'permission_callback' => '__return_true',
    ] );
} );

function my_shegerpay_webhook_handler( WP_REST_Request $request ) {
    $payload   = $request->get_body();
    $signature = $request->get_header( 'x-shegerpay-signature' );
    $secret    = get_option( 'my_shegerpay_webhook_secret' );

    // Verify HMAC signature
    $expected = hash_hmac( 'sha256', $payload, $secret );
    if ( ! hash_equals( $expected, (string) $signature ) ) {
        return new WP_REST_Response( [ 'error' => 'Invalid signature' ], 401 );
    }

    $event = json_decode( $payload, true );

    switch ( $event['type'] ?? '' ) {
        case 'payment.verified':
            // e.g. update WooCommerce order status
            $order_id = $event['data']['order_id'] ?? 0;
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $order->payment_complete();
                }
            }
            break;

        case 'payment.failed':
            // Handle failure
            break;
    }

    return new WP_REST_Response( [ 'received' => true ], 200 );
}
