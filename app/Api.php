<?php

namespace BeycanPress\WooCommerce\FreshBooks;

use \BeycanPress\Http\Request;
use \BeycanPress\Http\Response;
use \BeycanPress\FreshBooks\Connection;

class Api extends PluginHero\Api
{
    /**
     * @var Request
     */
    private $request;

    public function __construct()
    {
        $this->addRoutes([
            'wcfb' => [
                'get-access-token' => [
                    'callback' => 'getAccessToken',
                    'methods' => ['GET']
                ],
                'refresh-authentication' => [
                    'callback' => 'refrestAuthentication',
                    'methods' => ['GET']
                ]
            ]
        ]);

        $this->request = new Request();
    }
    
    /**
     * @return void
     */
    public function getAccessToken()
    {
        /** @var Connection */
        $conn = $this->callFunc('initFbConnection');

        try {
            $code = $this->request->get('code');
            if (!$code) die('Code is required.');
            $conn->getAccessTokenByAuthCode($code);
            $account = $conn->setAccount()->getAccount();
            $this->updateSetting('connected', true);
            $this->updateSetting('account', $account->getId());
            $this->redirect(admin_url('admin.php?page=wcfb_settings'));
        } catch (\Throwable $th) {
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
    public function refrestAuthentication()
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