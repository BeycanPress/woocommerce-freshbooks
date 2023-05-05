<?php

namespace BeycanPress\WooCommerce\FreshBooks;

use \BeycanPress\WooCommerce\FreshBooks\PluginHero\Setting;

class Settings extends Setting
{
    public function __construct()
    {
        parent::__construct(esc_html__('WC FreshBooks Settings', 'wcfb'));

        $conn = $this->callFunc('initFbConnection', true);

        $accounts = [];
        if ($conn && $this->setting('connected')) {
            foreach ($conn->getAccounts() as $account) {
                $accounts[$account->account_id] = $account->name;
            }
        } else {
            $accounts['unconnected'] = esc_html__('Unconnected', 'wcfb');
        }

        add_action('admin_head', function() {
            echo '<style>
                .connected-switcher {display: none;}
                .connected-status {color: green; font-weight: bold;}
                </style>';
        });

        self::createSection(array(
            'id'     => 'generalSettings', 
            'title'  => esc_html__('General settings', 'wcfb'),
            'icon'   => 'fa fa-cog',
            'fields' => array(
                array(
                    'id'      => 'connected',
                    'title'   => esc_html__('Connected', 'wcfb'),
                    'type'    => 'switcher',
                    'default' => false,
                    'class'   => 'connected-switcher',
                ),
                array(
                    'id'      => 'clientId',
                    'title'   => esc_html__('Client ID', 'wcfb'),
                    'type'    => 'text',
                    'default' => '',
                ),
                array(
                    'id'      => 'clientSecret',
                    'title'   => esc_html__('Client Secret', 'wcfb'),
                    'type'    => 'text',
                    'default' => '',
                ),
                array(
                    'id'      => 'connectedStatus',
                    'title'   => esc_html__('Connect', 'wcfb'),
                    'type'    => 'content',
                    'content' => '<span class="connected-status">' . esc_html__('Connected', 'wcfb') . '</span>',
                    'dependency' => array('connected', '==', true)
                ),
                array(
                    'id'      => 'connect',
                    'title'   => esc_html__('Connect', 'wcfb'),
                    'type'    => 'content',
                    'content' => (self::get('clientId') && self::get('clientSecret')) ? '<a href="#" class="button button-primary connect-to-freshbooks">' . esc_html__('Connect', 'wcfb') . '</a>' : esc_html__('Before you can connect, you must enter your application credentials. Please do not forget to refresh the page after entering the relevant settings.', 'wcfb'),
                    'dependency' => array('connected', '==', false)
                ),
                array(
                    'id'      => 'account',
                    'title'   => esc_html__('Account', 'wcfb'),
                    'type'    => 'select',
                    'options' => $accounts,
                    'default' => 'unconnected',
                    'dependency' => array('connected', '==', true)
                ),
            )
        ));

        self::createSection(array(
            'id'     => 'invoiceSettings', 
            'title'  => esc_html__('Invoice settings', 'wcfb'),
            'icon'   => 'fas fa-file-alt',
            'fields' => array(
                array(
                    'id'      => 'sendToEmail',
                    'title'   => esc_html__('Send to email', 'wcfb'),
                    'type'    => 'switcher',
                    'default' => false,
                    'help'    => esc_html__('If you want to send invoice to customer email, you can enable this setting.', 'wcfb')
                )
            ) 
        ));

        self::createSection(array(
            'id'     => 'backup', 
            'title'  => esc_html__('Backup', 'wcfb'),
            'icon'   => 'fas fa-shield-alt',
            'fields' => array(
                array(
                    'type'  => 'backup',
                    'title' => esc_html__('Backup', 'wcfb')
                ),
            ) 
        ));
    }
}