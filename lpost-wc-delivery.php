<?php

/**
 * Plugin Name: Delivery LPost for WooCommerce
 * Description: Служба доставки Л-Пост для WooCommerce
 * Plugin URI: https://wordpress.org/plugins/lpost-wc-delivery/
 * Version: 1.0
 * Author: L-Post
 * Author URI: https://l-post.ru/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 5.5
 * Requires PHP: 7.2
 * WC requires at least: 3.3
 * WC tested up to: 5.4
 * Text Domain: lpost-wc-delivery
 */

defined( 'ABSPATH' ) || exit;

// Include the main class.
if ( ! class_exists( 'LPost_WC', false ) ) {
	include_once __DIR__ . '/class-lpost_wc.php';
}

// Init plugin if woo is active.
if ( in_array(
	'woocommerce/woocommerce.php',
	apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
	true
) ) {
	new LPost_WC();
}
