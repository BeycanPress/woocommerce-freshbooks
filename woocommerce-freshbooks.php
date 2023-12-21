<?php defined('ABSPATH') || exit;

/**
 * Plugin Name: WooCommerce FreshBooks Integration
 * Version:     1.1.3
 * Author URI:  https://beycanpress.com/
 * Description: WooCommerce FreshBooks Integration
 * Author:      BeycanPress LLC
 * Author URI:  https://beycanpress.com/
 * License:     MIT
 * License URI: https://github.com/BeycanPress/woocommerce-freshbooks-plugin/blob/master/LICENSE
 * Text Domain: wcfb
 * Domain Path: /languages
 * Tags: WooCommerce, FreshBooks, Integration
 * Requires at least: 5.0
 * Tested up to: 6.4.2
 * Requires PHP: 7.4
*/

add_action('before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});

add_action('plugins_loaded', function() {
    if (function_exists('WC')) {
        require __DIR__ . '/vendor/autoload.php';
        new \BeycanPress\WooCommerce\FreshBooks\Loader(__FILE__);
    }
});