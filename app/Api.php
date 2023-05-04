<?php

namespace BeycanPress\WooCommerce\FreshBooks;

use \BeycanPress\Http\Request;

class Api extends PluginHero\Api
{
    private $request;

    public function __construct()
    {
        $this->addRoutes([
            'wcfb' => [
                'get-access-token' => [
                    'callback' => 'getAccessToken',
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
        $conn = $this->callFunc('initFbConnection');

        try {
            $code = $this->request->get('code');
            $conn->getAccessTokenByAuthCode($code);
            $account = $conn->setAccount()->getAccount();
            $this->updateSetting('connected', true);
            $this->updateSetting('account', $account->getId());
            $this->redirect(admin_url('admin.php?page=wcfb_settings'));
        } catch (\Throwable $th) {
            $this->debug($th->getMessage(), 'CRITICAL');
        }
    }

}