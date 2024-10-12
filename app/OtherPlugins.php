<?php

declare(strict_types=1);

namespace BeycanPress\WooCommerce\FreshBooks;

class OtherPlugins
{
    use Helpers;

    /**
     * @var string
     */
    private string $apiUrl = 'https://services.beycanpress.com/wp-json/general-data/';

    /**
     * @param string $pluginFile
     */
    public function __construct(string $pluginFile)
    {
        if (!isset($GLOBALS['beycanpress-plugins'])) {
            add_action('admin_menu', function () use ($pluginFile): void {
                add_menu_page(
                    esc_html__('BeycanPress Plugins', 'woocommerce-freshbooks'),
                    esc_html__('BeycanPress Plugins', 'woocommerce-freshbooks'),
                    'manage_options',
                    'beycanpress-plugins',
                    [$this, 'page'],
                    plugin_dir_url($pluginFile) . 'assets/images/beycanpress.png',
                );
            });
            $GLOBALS['beycanpress-plugins'] = true;
        }
    }

    /**
     * @return void
     */
    public function page(): void
    {
        $res = wp_remote_retrieve_body(wp_remote_get($this->apiUrl . 'get-plugins'));
        $res = json_decode(str_replace(['<p>', '</p>'], '', $res));
        $plugins = $res->data->plugins;
        $this->addStyle('main.css');
        // phpcs:disable
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php echo esc_html__('BeycanPress Plugins', 'woocommerce-freshbooks'); ?>
            </h1>
            <hr class="wp-header-end">
            <br>
            <div class="wrapper">
                <div class="box box-33">
                    <div class="postbox">
                        <div class="activity-block" style="padding: 20px; box-sizing: border-box; margin:0">
                            <ul class="product-list">
                                <?php if (isset($plugins)) :
                                    foreach ($plugins as $product) : ?>
                                        <li>
                                            <a href="<?php echo isset($product->landing_page) ? esc_url($product->landing_page) : esc_url($product->permalink); ?>" target="_blank">
                                                <img src="<?php echo esc_url($product->image); ?>" alt="<?php echo esc_attr($product->title) ?>">
                                                <span><?php echo esc_html($product->title) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach;
                                else :
                                    echo esc_html__('No product found!');
                                endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        // phpcs:enable
    }
}