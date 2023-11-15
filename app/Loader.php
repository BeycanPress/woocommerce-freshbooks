<?php

namespace BeycanPress\WooCommerce\FreshBooks;

use \BeycanPress\FreshBooks\Connection;

class Loader extends PluginHero\Plugin
{
    /**
     * @var Connection
     */
    private $conn;

    public function __construct(string $pluginFile)
    {
        parent::__construct([
            'pluginFile' => $pluginFile,
            'textDomain' => 'wcfb',
            'pluginKey' => 'wcfb',
            'settingKey' => 'wcfb_settings',
            'pluginVersion' => '1.1.1',
            'debugging' => true,
        ]);

        new Api();

        $this->addFunc('initFbConnection', function(bool $authenticate = false) {
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
    }

    public function adminProcess() : void
    {
        if (
            !$this->setting('connected') && 
            isset($_GET['page']) && 
            $_GET['page'] == 'wcfb_settings'
        ) {
            add_action('admin_enqueue_scripts', function() {
                if ($conn = $this->callFunc('initFbConnection')) {
                    $key = $this->addScript('js/admin.js', ['jquery']);
                    wp_localize_script($key, 'WCFB', [
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce' => $this->createNewNonce(),
                        'fbUrl' => $conn->getAuthRequestUrl()
                    ]);
                }
            });
        }

        add_action('init', function(){
            new Settings();
            new DebugLogs();
        }, 9);
    }
}
