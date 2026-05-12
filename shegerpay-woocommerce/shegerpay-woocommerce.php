<?php
/**
 * Plugin Name: ShegerPay for WooCommerce
 * Plugin URI: https://shegerpay.com/docs/wordpress
 * Description: Accept Ethiopian bank payments (CBE, Telebirr, BOA, Awash) and crypto via ShegerPay. Verify payments automatically.
 * Version: 1.0.0
 * Author: ShegerPay
 * Author URI: https://shegerpay.com
 * License: MIT
 * Text Domain: shegerpay-woocommerce
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('SHEGERPAY_VERSION', '1.0.0');
define('SHEGERPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SHEGERPAY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check WooCommerce is active
add_action('plugins_loaded', function() {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>ShegerPay requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }
    require_once SHEGERPAY_PLUGIN_DIR . 'includes/class-shegerpay-api.php';
    require_once SHEGERPAY_PLUGIN_DIR . 'includes/class-shegerpay-gateway.php';

    add_filter('woocommerce_payment_gateways', function($gateways) {
        $gateways[] = 'WC_ShegerPay_Gateway';
        return $gateways;
    });
});

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

register_activation_hook(__FILE__, function() {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('ShegerPay requires PHP 7.4 or higher.');
    }
});
