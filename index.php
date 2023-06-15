<?php defined('ABSPATH') || exit;

/**
 * Plugin Name: WooCommerce FreshBooks Integration
 * Version:     1.0.2
 * Plugin URI:  https://github.com/BeycanPress/woocommerce-freshbooks-plugin
 * Description: WooCommerce FreshBooks Integration
 * Author:      BeycanPress
 * Author URI:  https://beycanpress.com
 * License:     MIT
 * License URI: https://github.com/BeycanPress/woocommerce-freshbooks-plugin/blob/master/LICENSE
 * Text Domain: wcfb
 * Domain Path: /languages
 * Tags: WooCommerce, FreshBooks, Integration
 * Requires at least: 5.0
 * Tested up to: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 4.4
 * WC tested up to: 7.5
*/

require __DIR__ . '/vendor/autoload.php';
new \BeycanPress\WooCommerce\FreshBooks\Loader(__FILE__);