<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Plugin Name: FreshBooks Integration for WooCommerce
 * Version:     1.1.8
 * Author URI:  https://beycanpress.com/
 * Description: FreshBooks Integration for WooCommerce
 * Author:      BeycanPress LLC
 * Plugin URI:  https://github.com/BeycanPress/woocommerce-freshbooks
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woocommerce-freshbooks
 * Domain Path: /languages
 * Tags: WooCommerce, FreshBooks, Integration
 * Requires at least: 5.0
 * Tested up to: 6.7.1
 * Requires PHP: 8.1
*/

add_action('before_woocommerce_init', function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', function (): void {
    if (function_exists('WC')) {
        require __DIR__ . '/vendor/autoload.php';
        new \BeycanPress\WooCommerce\FreshBooks\Loader(__FILE__);
    }
});
