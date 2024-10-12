<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength
// phpcs:disable WordPress.Security.NonceVerification.Recommended

namespace BeycanPress\WooCommerce\FreshBooks;

use CSF;

class Settings
{
    use Helpers;

    /**
     * constructor
     */
    public function __construct()
    {
        CSF::createOptions($this->settingKey, [
            'framework_title'         => 'WC FreshBooks Settings' . ' <small>By BeycanPress</small>',

            // menu settings
            'menu_title'              => 'WC FreshBooks Settings',
            'menu_slug'               => $this->settingKey,
            'menu_capability'         => 'manage_options',
            'menu_position'           => 999,
            'menu_hidden'             => false,

            // menu extras
            'show_bar_menu'           => false,
            'show_sub_menu'           => false,
            'show_network_menu'       => true,
            'show_in_customizer'      => false,

            'show_search'             => true,
            'show_reset_all'          => true,
            'show_reset_section'      => true,
            'show_footer'             => true,
            'show_all_options'        => true,
            'sticky_header'           => true,
            'save_defaults'           => true,
            'ajax_save'               => false,

            // database model
            'transient_time'          => 0,

            // contextual help
            'contextual_help'         => [],

            // typography options
            'enqueue_webfont'         => false,
            'async_webfont'           => false,

            // others
            'output_css'              => false,

            // theme
            'theme'                   => 'dark',

            // external default values
            'defaults'                => [],
        ]);

        $conn = $this->callFunc('initFbConnection', true);
        if (isset($_GET['disconnect'])) {
            $conn->deleteTokenFile();
            $this->updateSetting('connected', false);
            $this->redirect(admin_url('admin.php?page=wcfb_settings'));
        }

        try {
            $accounts = [];
            if ($conn && $this->setting('connected')) {
                foreach ($conn->getAccounts() as $account) {
                    $accounts[$account->account_id] = $account->name;
                }
            } else {
                $accounts['unconnected'] = esc_html__('Unconnected', 'woocommerce-freshbooks');
            }
        } catch (\Exception $e) {
            $this->debug($e->getMessage(), 'CRITICAL', [
                'trace' => $e->getTrace(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            $this->updateSetting('connected', false);
            $accounts['unconnected'] = esc_html__('Unconnected', 'woocommerce-freshbooks');
        }

        add_action('admin_head', function (): void {
            echo '<style>
                .connected-switcher {display: none;}
                .connected-status {color: green; font-weight: bold;}
                </style>';
        });

        self::createSection([
            'id'     => 'generalSettings',
            'title'  => esc_html__('General settings', 'woocommerce-freshbooks'),
            'icon'   => 'fa fa-cog',
            'fields' => [
                [
                    'id'      => 'connected',
                    'title'   => esc_html__('Connected', 'woocommerce-freshbooks'),
                    'type'    => 'switcher',
                    'default' => false,
                    'class'   => 'connected-switcher',
                ],
                [
                    'id'      => 'clientId',
                    'title'   => esc_html__('Client ID', 'woocommerce-freshbooks'),
                    'type'    => 'text',
                    'default' => '',
                ],
                [
                    'id'      => 'clientSecret',
                    'title'   => esc_html__('Client Secret', 'woocommerce-freshbooks'),
                    'type'    => 'text',
                    'default' => '',
                ],
                [
                    'id'      => 'connectedStatus',
                    'title'   => esc_html__('Connect', 'woocommerce-freshbooks'),
                    'type'    => 'content',
                    'content' => '<span class="connected-status">' . esc_html__('Connected', 'woocommerce-freshbooks') . '</span><br>
                    <a href="' . $this->getCurrentUrl() . '&disconnect=1" class="disconnect-from-freshbooks">' . esc_html__('Disconnect', 'woocommerce-freshbooks') . '</a>',
                    'dependency' => ['connected', '==', true]
                ],
                [
                    'id'      => 'connect',
                    'title'   => esc_html__('Connect', 'woocommerce-freshbooks'),
                    'type'    => 'content',
                    'content' => ($this->setting('clientId') && $this->setting('clientSecret')) ? '<a href="#" class="button button-primary connect-to-freshbooks">' . esc_html__('Connect', 'woocommerce-freshbooks') . '</a>' : esc_html__('Before you can connect, you must enter your application credentials. Please do not forget to refresh the page after entering the relevant settings.', 'woocommerce-freshbooks'),
                    'dependency' => ['connected', '==', false]
                ],
                [
                    'id'      => 'account',
                    'title'   => esc_html__('Account', 'woocommerce-freshbooks'),
                    'type'    => 'select',
                    'options' => $accounts,
                    'default' => 'unconnected',
                    'dependency' => ['connected', '==', true]
                ],
            ]
        ]);

        $gateways = [];
        try {
            foreach (WC()->payment_gateways->get_available_payment_gateways() as $key => $value) {
                $gateways[$key] = $value->title;
            }
        } catch (\Throwable $th) {
            $this->debug($th->getMessage(), 'CRITICAL', [
                'trace' => $th->getTrace(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);
        }

        self::createSection([
            'id'     => 'invoiceSettings',
            'title'  => esc_html__('Invoice settings', 'woocommerce-freshbooks'),
            'icon'   => 'fas fa-file-alt',
            'fields' => [
                [
                    'id'      => 'createInvoice',
                    'title'   => esc_html__('Create invoice', 'woocommerce-freshbooks'),
                    'type'    => 'switcher',
                    'default' => false,
                    'help'    => esc_html__('If you want create invoice for order, you can enable this setting.', 'woocommerce-freshbooks')
                ],
                [
                    'id'      => 'addDiscountData',
                    'title'   => esc_html__('Invoice with discount data', 'woocommerce-freshbooks'),
                    'type'    => 'switcher',
                    'default' => false,
                    'help'    => esc_html__('Use this if you have set decimal to at least 2 in your store\'s settings, otherwise data incompatibility will occur.', 'woocommerce-freshbooks')
                ],
                [
                    'id'      => 'sendToEmail',
                    'title'   => esc_html__('Send to email', 'woocommerce-freshbooks'),
                    'type'    => 'switcher',
                    'default' => false,
                    'help'    => esc_html__('If you want to send invoice to customer email, you can enable this setting.', 'woocommerce-freshbooks')
                ],
                [
                    'id'       => 'excludePaymentMethods',
                    'title'    => esc_html__('Excluded payment methods', 'woocommerce-freshbooks'),
                    'type'     => 'select',
                    'options'  => $gateways,
                    'chosen'   => true,
                    'multiple' => true,
                ],
            ]
        ]);

        self::createSection([
            'id'     => 'backup',
            'title'  => esc_html__('Backup', 'woocommerce-freshbooks'),
            'icon'   => 'fas fa-shield-alt',
            'fields' => [
                [
                    'type'  => 'backup',
                    'title' => esc_html__('Backup', 'woocommerce-freshbooks')
                ],
            ]
        ]);
    }

    /**
     * @param array<mixed> $args
     * @return void
     */
    public static function createSection(array $args): void
    {
        CSF::createSection(Loader::$properties->settingKey, $args);
    }
}
