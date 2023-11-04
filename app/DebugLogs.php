<?php 

namespace BeycanPress\WooCommerce\FreshBooks;

use BeycanPress\WooCommerce\FreshBooks\PluginHero\Page;

class DebugLogs extends Page
{   
    public function __construct()
    {
        parent::__construct([
            'pageName' => esc_html__('Debug logs', 'wcfb'),
            'parent' => 'wcfb_settings',
            'priority' => 11,
        ]);
    }

    /**
     * @return void
     */
    public function page() : void
    {
        if ($_POST['delete'] ?? 0) {
            $this->deleteLogFile();
            wp_redirect(admin_url('admin.php?page=wcfb-debug-logs'));
        }
        
        $this->viewEcho('debug-logs', [
            'logs' => $this->getLogFile()
        ]);
    }
}