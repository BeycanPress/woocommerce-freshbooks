<?php

declare(strict_types=1);

// phpcs:disable WordPress.Security.NonceVerification.Recommended

namespace BeycanPress\WooCommerce\FreshBooks;

use BeycanPress\FreshBooks\Connection;

class Loader
{
    use Helpers;

    /**
     * @var object
     */
    public static object $properties;

    /**
     * @var array<mixed>|null
     */
    public static ?array $settings = null;

    /**
     * @var Connection|null
     */
    private ?Connection $conn = null;

    /**
     * constructor
     *
     * @param string $pluginFile
     */
    public function __construct(string $pluginFile)
    {
        self::$properties = (object) array_merge([
            'textDomain' => 'woocommerce-freshbooks',
            'pluginKey' => 'wcfb',
            'settingKey' => 'wcfb_settings',
            'pluginVersion' => '1.1.4',
            'debugging' => true,
        ], [
            'phVersion' => '0.2.2',
            'phDir'     => trailingslashit(__DIR__),
            'pluginUrl' => plugin_dir_url($pluginFile),
            'pluginDir' => plugin_dir_path($pluginFile),
        ], (array) $this->getPluginData($pluginFile));

        if (file_exists($this->pluginDir . 'vendor/beycanpress/csf/csf.php')) {
            require_once $this->pluginDir . 'vendor/beycanpress/csf/csf.php';
        }

        new RestAPI();

        $this->addFunc('initFbConnection', function (bool $authenticate = false) {
            if (!$this->conn) {
                try {
                    if ($this->setting('clientId') && $this->setting('clientSecret')) {
                        $this->conn = new Connection(
                            $this->setting('clientId'),
                            $this->setting('clientSecret'),
                            home_url('/wp-json/wcfb/get-access-token')
                        );

                        if ($authenticate && file_exists($this->conn->getTokenFile())) {
                            $this->conn->refreshAuthentication();
                            $this->updateSetting('connected', true);
                            $this->conn->setAccount($this->setting('account'));
                        }

                        return $this->conn;
                    } else {
                        $this->debug('Client ID and Client Secret are required.', 'CRITICAL');
                        return null;
                    }
                } catch (\Throwable $th) {
                    $this->debug($th->getMessage(), 'CRITICAL', [
                        'trace' => $th->getTrace(),
                        'file' => $th->getFile(),
                        'line' => $th->getLine()
                    ]);
                    $this->updateSetting('connected', false);
                    return null;
                }
            } else {
                return $this->conn;
            }
        });

        if ($this->setting('createInvoice')) {
            new WooCommerce();
        }

        new OtherPlugins($pluginFile);

        if (is_admin()) {
            $this->adminProcess();
        }
    }

    /**
     * @return void
     */
    public function adminProcess(): void
    {
        if (
            !$this->setting('connected') &&
            isset($_GET['page']) &&
            'wcfb_settings' == $_GET['page']
        ) {
            add_action('admin_enqueue_scripts', function (): void {
                if ($conn = $this->callFunc('initFbConnection')) {
                    $this->addStyle('main.css');
                    $key = $this->addScript('admin.js', ['jquery']);
                    wp_localize_script($key, 'WCFB', [
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce' => $this->createNewNonce(),
                        'fbUrl' => $conn->getAuthRequestUrl()
                    ]);
                }
            });
        }

        add_action('init', function (): void {
            new Settings();
        }, 9);
    }

    /**
     * @param string $file
     * @return object
     */
    public function getPluginData(string $file): object
    {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        return (object) get_plugin_data($file);
    }
}
