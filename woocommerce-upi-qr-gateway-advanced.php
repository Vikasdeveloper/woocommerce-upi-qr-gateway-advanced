<?php
/**
 * Plugin Name: WooCommerce UPI QR Gateway - Advanced
 * Description: UPI QR Gateway with webhook verification, transaction dedupe, webhook logging, IP allowlist and replay protection.
 * Version: 1.1.0
 * Author: Vikas Rana
 * Text Domain: wc-upi-qr-advanced
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Plugin constants
define('WC_UPI_ADV_DIR', plugin_dir_path(__FILE__));
define('WC_UPI_ADV_URL', plugin_dir_url(__FILE__));

require_once WC_UPI_ADV_DIR . 'includes/class-wc-gateway-upi-qr-advanced.php';
require_once WC_UPI_ADV_DIR . 'includes/webhook.php';
require_once WC_UPI_ADV_DIR . 'includes/install.php';

add_action('plugins_loaded', function(){
    if ( ! class_exists('WC_Payment_Gateway') ) return;
    add_filter('woocommerce_payment_gateways', function($methods){
        $methods[] = 'WC_Gateway_UPI_QR_Advanced';
        return $methods;
    });
});

register_activation_hook(__FILE__, 'wc_upi_adv_activate');
function wc_upi_adv_activate() {
    if ( function_exists('WC_UPI_ADV_install_activate') ) {
        WC_UPI_ADV_install_activate();
    }
}
register_uninstall_hook(__FILE__, 'wc_upi_adv_uninstall');
function wc_upi_adv_uninstall() {
    if ( function_exists('WC_UPI_ADV_install_uninstall') ) {
        WC_UPI_ADV_install_uninstall();
    }
}
