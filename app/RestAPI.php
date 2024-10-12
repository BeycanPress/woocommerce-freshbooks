<?php

declare(strict_types=1);

namespace BeycanPress\WooCommerce\FreshBooks;

use BeycanPress\FreshBooks\Connection;

class RestAPI
{
    use Helpers;

    /**
     * constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * @param string $route
     * @param string $callback
     * @param string $method
     * @return void
     */
    private function registerRoute(string $route, string $callback, string $method = 'GET'): void
    {
        register_rest_route('wcfb', '/' . $route, [
            'methods' => $method,
            'callback' => [$this, $callback],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * register routes
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        $this->registerRoute('get-access-token', 'getAccessToken');
        $this->registerRoute('refresh-authentication', 'refreshAuthentication');
    }

    /**
     * @return void
     */
    public function getAccessToken(): void
    {
        /** @var Connection */
        $conn = $this->callFunc('initFbConnection');

        try {
            $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : null;
            if (!$code) {
                die('Code is required.');
            }
            $conn->getAccessTokenByAuthCode($code);
            $account = $conn->setAccount()->getAccount();
            $this->updateSetting('connected', true);
            $this->updateSetting('account', $account->getId());
            $this->redirect(admin_url('admin.php?page=wcfb_settings'));
        } catch (\Throwable $th) {
            wp_send_json_error($th->getMessage());
            $this->debug($th->getMessage(), 'CRITICAL', [
                'trace' => $th->getTrace(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);
        }
    }

    /**
     * @return void
     */
    public function refreshAuthentication(): void
    {
        try {
            /** @var Connection */
            $this->callFunc('initFbConnection')->refreshAuthentication();
            $this->updateSetting('connected', true);
            Response::success();
        } catch (\Throwable $th) {
            $this->debug($th->getMessage(), 'CRITICAL', [
                'trace' => $th->getTrace(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);
        }
    }
}
