<?php

/**
 * Plugin Name: CGT GPO Gateway
 * Description: WooCommerce payment gateway for GPO Webframe.
 * Version: 1.0
 * Author: CGT
 * Author URI: https://www.conectglobal.com
 * License: GPLv2 or later
 * Text Domain: cgt-gpo-gateway
 */

defined('ABSPATH') || exit;

add_action('plugins_loaded', 'cgt_gpo_init');

function cgt_gpo_init()
{
    // Include the main gateway class
    require_once plugin_dir_path(__FILE__) . 'includes/class-cgt-gpo-gateway.php';

    // Register the route when the plugin is initialized
    add_action('rest_api_init', 'teubiva_register_payment_callback');

    // Add the payment gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_cgt_gpo_gateway');
}

function add_cgt_gpo_gateway($methods)
{
    $methods[] = 'CGT_GPO_Gateway';
    return $methods;
}

function teubiva_register_payment_callback()
{
    $gpo_gateway = new CGT_GPO_Gateway();
    register_rest_route('teubiva/v1', '/payment-callback/', array(
        'methods'            => WP_REST_Server::ALLMETHODS,
        'callback'            => array($gpo_gateway, 'teubiva_process_payment_callback'),
        'permission_callback' => '__return_true', // Allow public access
    ));
}
