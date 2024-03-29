<?php 

namespace BeycanPress\WooCommerce\FreshBooks\PluginHero;

/**
 * Contains the commonly used ones for this plugin
 */
trait Helpers
{   
    private $bpApiUrl = 'https://beycanpress.com/wp-json/bp-api/';
    
    /**
     * @param string $property
     * @return mixed
     */
    public function __get(string $property)
    {
        if (is_null(Plugin::$properties)) return null;
        return isset(Plugin::$properties->$property) ? Plugin::$properties->$property : null;
    }

    /**
     * @param string $property
     * @param mixed $value
     * @return mixed
     */
    public function __set(string $property, $value)
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (is_object($val)) {
                    $className = (new \ReflectionClass($val))->getShortName();
                    if ($className != 'stdClass') {
                        if (!isset(Plugin::$properties->$property)) {
                            Plugin::$properties->$property = (object) [];
                        }
                        Plugin::$properties->$property->$className = $val;
                    } else {
                        Plugin::$properties->$property = $value;
                    }
                } else {
                    Plugin::$properties->$property = $value;
                }
            }
        } elseif (is_object($value)) { 
            $className = (new \ReflectionClass($value))->getShortName();
            if ($className != 'stdClass') {
                if (!isset(Plugin::$properties->$property)) {
                    Plugin::$properties->$property = (object) [];
                }
                Plugin::$properties->$property->$className = $value;
            } else {
                Plugin::$properties->$property = $value;
            }
        } else {
            Plugin::$properties->$property = $value;
        }
    }

    /**
     * @param string $name
     * @param \Closure $closure
     * @return void
     */
    public function addFunc(string $name, \Closure $closure)
    {
        if (!isset(Plugin::$properties->funcs)) {
            Plugin::$properties->funcs = (object) [];
        }

        if (!isset(Plugin::$properties->funcs->$name)) {
            Plugin::$properties->funcs->$name = $closure;
        }
    }

    /**
     * @param string $name
     * @param mixed ...$args
     * @return mixed
     */
    public function callFunc(string $name, ...$args)
    {
        if (isset(Plugin::$properties->funcs->$name)) {
            $closure = Plugin::$properties->funcs->$name;
            return $closure(...$args);
        } else {
            throw new \Exception('Function not found');
        }
    }

    /**
     *
     * @param object $page
     * @return void
     */
    public function addPage(object $page) : void
    {
        if (is_null(Plugin::$properties)) return;
        $className = (new \ReflectionClass($page))->getShortName();
        if (!isset(Plugin::$properties->pages)) {
            Plugin::$properties->pages = (object) [];
        }
        Plugin::$properties->pages->$className = $page;
    }

    /**
     * @param object $api
     * @return void
     */
    public function addApi(object $api) : void
    {
        if (is_null(Plugin::$properties)) return;
        $className = (new \ReflectionClass($api))->getShortName();
        if (!isset(Plugin::$properties->apis)) {
            Plugin::$properties->apis = (object) [];
        }
        Plugin::$properties->apis->$className = $api;
    }

    /**
     * @param string $viewName Directory name within the folder
     * @return string
     */
    public function view(string $viewName, array $args = []) : string
    {
        extract($args);
        ob_start();
        include $this->pluginDir . 'views/' . $viewName . '.php';
        return ob_get_clean();
    }

    /**
     * @param string $viewName Directory name within the folder
     * @param array $args
     * @param array $allowedHtml
     * @return void
     */
    public function viewEcho(string $viewName, array $args = [], array $allowedHtml = []) : void
    {
        try {
            $allowedHtml = array_merge_recursive($allowedHtml, $this->parseAllowedHtml($this->pluginDir . 'views/' . $viewName . '.php'));
        } catch (\Throwable $th) {}

        echo wp_kses($this->view($viewName, $args), array_merge_recursive(wp_kses_allowed_html('post'), $allowedHtml));
    }

    /**
     * Easy use for get_option
     * @param string $setting
     * @return mixed
     */
    public function setting(string $setting = null)
    {
        if (is_null(Plugin::$settings)) Plugin::$settings = get_option($this->settingKey); 

        if (is_null($setting)) return Plugin::$settings;

        return isset(Plugin::$settings[$setting]) ? Plugin::$settings[$setting] : null;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function updateSetting(string $key, $value)
    {
        if (is_null(Plugin::$settings)) Plugin::$settings = get_option($this->settingKey); 

        Plugin::$settings[$key] = $value;

        update_option($this->settingKey, Plugin::$settings);
    }

    /**
     * @param string $viewName Directory name within the folder
     * @return string
     */
    public function getTemplate(string $templateName, array $args = []) : string
    {
        extract($args);
        ob_start();
        include $this->phDir . 'Templates/' . $templateName . '.php';
        return ob_get_clean();
    }

    /**
     * @param string $fileName php file name
     * @return string
     */
    public function getFilePath(string $fileName) : string
    {
        return $this->pluginDir . $fileName . '.php';
    }

    /**
     * @param string get image url in asset images folder
     * @return string
     */
    public function getImageUrl(string $imageName) : string
    {
        return $this->pluginUrl . 'assets/images/' . $imageName;
    }

    /**
     * @param string $type error, success more
     * @param string $notice notice to be given
     * @param bool $dismissible in-dismissible button show and hide
     * @return void
     */
    public function notice(string $notice, string $type = 'success', bool $dismissible = false) : void
    {
        echo wp_kses_post($this->getTemplate('notice', [
            'type' => $type,
            'notice' => $notice,
            'dismissible' => $dismissible
        ]));
    }   

    /**
     * @param string $type error, success more
     * @param string $notice notice to be given
     * @param bool $dismissible in-dismissible button show and hide
     * @return void
     */
    public function adminNotice(string $notice, string $type = 'success', bool $dismissible = false) : void
    {
        add_action('admin_notices', function() use ($notice, $type, $dismissible) {
            $this->notice($notice, $type, $dismissible);
        });
    }   
    
    /**
     * Ajax action hooks
     * @param string $action ajax function name
     * @return void
     */
    public function ajaxAction(string $action) : void
    {
        add_action('wp_ajax_'.$action , [$this, $action]);
        add_action('wp_ajax_nopriv_'.$action , [$this, $action]);
    }

    /**
     * New nonce create method
     * @param string|null $externalKey
     * @return string
     */
    public function createNewNonce(?string $externalKey = null) : string
    {
        $key = $this->pluginKey . '_nonce' . $externalKey;
        return wp_create_nonce($key);
    }

    /**
     * Nonce control mehod
     * @param string|null $externalKey
     * @return void
     */
    public function checkNonce(?string $externalKey = null) : void
    {
        $key = $this->pluginKey . '_nonce' . $externalKey;
        check_ajax_referer($key, 'nonce');
    }

    /**
     * New nonce field create method
     * @param string|null $externalKey
     * @return void
     */
    public function createNewNonceField(?string $externalKey = null) : void
    {
        $key = $this->pluginKey . '_nonce' . $externalKey;
        wp_nonce_field($key, 'nonce');
    }

    /**
     * Nonce field control method
     * @param string|null $externalKey
     * @return bool
     */
    public function checkNonceField(?string $externalKey = null) : bool
    {
        if (!isset($_POST['nonce'])) return false;
        $key = $this->pluginKey . '_nonce' . $externalKey;
        return @wp_verify_nonce(sanitize_text_field($_POST['nonce']), $key) ? true : false;
    }

    /**
     * @return string
     */
    public function getCurrentUrl() : string
    {
        $siteURL = explode('/', get_site_url());
        $requestURL = explode('/', esc_url_raw($_SERVER['REQUEST_URI']));
        $currentURL = array_unique(array_merge($siteURL, $requestURL));
        return implode('/', $currentURL);
    }

    /**
     * @param string $datetime
     * @return \DateTime
     */
    public function getTime(string $datetime = 'now') : \DateTime
    {
        return new \DateTime($datetime, new \DateTimeZone(wp_timezone_string()));
    }

    /**
     * @param string $datetime
     * @return \DateTime
     */
    public function getUTCTime(string $datetime = 'now') : \DateTime
    {
        return new \DateTime($datetime, new \DateTimeZone('UTC'));
    }

    /**
     * @param string $url
     * @return void
     */
    public function redirect(string $url) : void
    {
        wp_redirect($url);
        exit();
    }
    
    /**
     * @param string $url
     * @return void
     */
    protected function pageRedirect(string $url) : void
    {
        echo "<script>window.location.href = '".esc_url_raw($url)."'</script>";
        die();
    }

    /**
     * @param string $url
     * @return void
     */
    public function adminRedirect(string $url) : void
    {
        add_action('admin_init', function() use ($url) {
			wp_redirect($url);
            exit();
		});
    }

    /**
     * @param string $url
     * @return void
     */
    public function templateRedirect(string $url) : void
    {
        add_action('template_redirect', function() use ($url) {
            wp_redirect($url);
			exit();
		});
    }

    /**
     * @param string $date
     * @return string
     */
    public function dateToTimeAgo(string $date) : string
    {
        return human_time_diff(strtotime(wp_date('Y-m-d H:i:s')), strtotime($date));
    }

    /**
     * @param int|string|float $number
     * @param int $decimals
     * @return float
     */
    public function toFixed($number, int $decimals) : float
    {
        return floatval(number_format($number, $decimals, '.', ""));
    }

    /**
     * @param string $jsonString
     * @param bool $array
     * @return object|array
     */
    public function parseJson(string $jsonString, bool $array = false)
    {
        return json_decode(html_entity_decode(stripslashes($jsonString)), $array);
    }

    /**
     *
     * @param string $content
     * @return string
     */
    function catchShortcode(string $content) : string
    {
        global $shortcode_tags;
        $tagnames = array_keys($shortcode_tags);
        $tagregexp = join( '|', array_map('preg_quote', $tagnames) );
    
        // WARNING! Do not change this regex without changing do_shortcode_tag() and strip_shortcodes()
        $pattern = '(.?)\[('.$tagregexp.')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)';

        return preg_replace_callback('/'.$pattern.'/s', 'do_shortcode_tag', $content);
    }
	
    /**
     * @param string $path
     * @param string|null $key
     * @param array $deps
     * @return string
     */
    public function registerScript(string $path, ?string $key = null, array $deps = []) : string
    {
        if (!$key) {
            $key = explode('/', $path);
            $key = $this->pluginKey . '-' . end($key);
        }

        wp_register_script(
            $key,
            $this->pluginUrl . 'assets/' . $path,
            $deps,
            $this->pluginVersion,
            true
        );
        
        return $key;
    }

	/**
     * @param string $path
     * @param array $deps
     * @return string
     */
    public function addScript(string $path, array $deps = []) : string
    {
        $f = substr($path, 0, 1);
        $key = explode('/', $path);
        $key = $this->pluginKey . '-' . preg_replace('/\..*$/', '', end($key));
        $middlePath = $f === '/' ? 'assets' : 'assets/js/';
        $url = $this->pluginUrl . $middlePath . $path;
        wp_enqueue_script(
            $key,
            $url,
            $deps,
            $this->pluginVersion,
            true
        );
        
        return $key;
    }

    /**
     * @param string $path
     * @param array $deps
     * @return string
     */
    public function addStyle(string $path, array $deps = []) : string
    {
        $f = substr($path, 0, 1);
        $key = explode('/', $path);
        $key = $this->pluginKey . '-' . preg_replace('/\..*$/', '', end($key));
        $middlePath = $f === '/' ? 'assets' : 'assets/css/';
        $url = $this->pluginUrl . $middlePath . $path;
        wp_enqueue_style(
            $key,
            $url,
            $deps,
            $this->pluginVersion
        );
        
        return $key;
    }

    /**
     * @return string|null
     */
    public function getIp() : ?string
    {
        $ip = null;
		if (isset($_SERVER['REMOTE_ADDR'])) {
			$ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
			$ip = rest_is_ip_address($ip);
			if (false === $ip) {
				$ip = null;
			}
		}

        return $ip;
    }
	
	/**
     * @param string $templateName
     * @param string $templateFile
     * @param array $params
     * @param array $allowedHtml
     * @return void
     */
    public function registerPageTemplate(string $templateName, string $templateFile, array $params = [], array $allowedHtml = []) : void
    {
        add_filter('theme_page_templates', function($templates) use ($templateName, $templateFile){
            $templateFile = $this->pluginKey . $templateFile;
            return array_merge($templates, [$templateFile => esc_html($templateName)]);
        });

        add_filter('template_include', function($template) use ($params, $allowedHtml) {
            global $post;
            
            if ($post && is_page()) {
				$pageTemplate = get_page_template_slug($post->ID);

				if (strpos($pageTemplate, $this->pluginKey) !== false) {
					$this->viewEcho('page-templates/' . str_replace($this->pluginKey,  '', $pageTemplate), $params, $allowedHtml);
				}    
			}   
            
            return $template;
        }, 99);
    }

    /**
     * @param callable $function
     * @param string $file
     * @param int $time
     * @return object
     */
    public function cache(callable $function, string $file, int $time = 600) : object
    {
        if (file_exists($file) && time() - $time < filemtime($file)) {
            $content = file_get_contents($file);
        } else {
            if (file_exists($file)) {
                unlink($file);
            }

            $content = $function();

            $fp = fopen($file, 'w+');
            fwrite($fp, $content);
            fclose($fp);
        }

        return (object) compact('file', 'content');
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function deleteCache(string $name) : bool
    {
        $path = $this->pluginDir . 'cache/';
        $file = $path . $name . '.html';
        if (file_exists($file)) {
            return unlink($file);
        } else {
            return false;
        }
    }

    /**
     * @param string $message
     * @param string $level
     * @param array $context
     * @return void
     */
    public function debug(string $message, string $level = 'INFO', array $context = [])
    {
        if ($this->debugging) {
            $trace = debug_backtrace();

            $content = $this->getTemplate('log', [
                'level' => $level,
                'message' => $message,
                'file' => $trace[0]['file'] . '(' . $trace[0]['line'] . ')',
                'class' => $trace[1]['class'] ?? null,
                'function' => $trace[1]['function'] ?? null,
                'date' => $this->getTime()->format('Y-m-d H:i:s'),
                'context' => print_r(array_merge($this->debugDefaultContext ?? [], $context), true)
            ]);

            $file = $this->pluginDir . 'debug.log';
            
            $fp = fopen($file, 'a+');
            fwrite($fp, $content);
            fclose($fp);
        }
    }

    /**
     * @return ?string
     */
    public function getLogFile() : ?string
    {
        $file = $this->pluginDir . 'debug.log';
        if (file_exists($file)) {
            return file_get_contents($file);
        } else {
            return null;
        }    
    }
    
    /**
     * @return void
     */
    public function deleteLogFile()
    {
        if (file_exists($this->pluginDir . 'debug.log')) {
            unlink($this->pluginDir . 'debug.log');
        }
    }

    /**
     * @param string $amount
     * @param integer $decimals
     * @return string
     */
    public function toString(string $amount, int $decimals) : string
    {
        $pos1 = stripos((string) $amount, 'E-');
        $pos2 = stripos((string) $amount, 'E+');
    
        if ($pos1 !== false) {
            $amount = number_format($amount, $decimals, '.', ',');
        }

        if ($pos2 !== false) {
            $amount = number_format($amount, $decimals, '.', '');
        }
    
        return $amount > 1 ? $amount : rtrim($amount, '0');
    }

    /**
     * @param string|null $address
     * @return string|null
     */
    public function parseDomain(?string $address) : ?string
    { 
		if (!$address) return $address;
		
        $parseUrl = parse_url(trim($address)); 
        if (isset($parseUrl['host'])) {
            return trim($parseUrl['host']);
        } else {
            $domain = explode('/', $parseUrl['path'], 2);
            return array_shift($domain);
        }
    } 
    
    /**
     * @param string $filePath
     * @return array
     */
    public function parseAllowedHtml(string $filePath) : array 
    {
        $html = file_get_contents($filePath);

        $tagAttributes = [];

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);

        $tags = $dom->getElementsByTagName('*');

        foreach ($tags as $tag) {
            $tagName = $tag->tagName;

            if (!array_key_exists($tagName, $tagAttributes)) {
                $tagAttributes[$tagName] = [];
            }

            foreach ($tag->attributes as $attr) {
                $attributeName = $attr->name;
                if (!array_key_exists($attributeName, $tagAttributes[$tagName])) {
                    $tagAttributes[$tagName][$attributeName] = true;
                }
            }
        }

        return $tagAttributes;
    }
    
    /**
     * @param string $domain
     * @return boolean
     */
    public function isValidDomain(string $domain) : bool {
        if (!preg_match('/^[a-zA-Z0-9\-]+(\.[a-zA-Z]{2,})+$/', $domain)) {
            return false;
        }
    
        return true;
    }

    /**
     * @param mixed $closureOrMethodName
     * @return void
     */
    public function registerActivation($closureOrMethodName) : void
    {
        register_activation_hook($this->pluginFile, $closureOrMethodName);
    }

    /**
     * @param mixed $closureOrMethodName
     * @return void
     */
    public function registerDeactivation($closureOrMethodName) : void
    {
        register_deactivation_hook($this->pluginFile, $closureOrMethodName);
    }

    /**
     * @param mixed $closureOrMethodName
     * @return void
     */
    public function registerUninstall($closureOrMethodName) : void
    {
        register_uninstall_hook($this->pluginFile, $closureOrMethodName);
    }

    /**
     * @param string $field
     * @param array $value
     * @return object|null
     */
    public function getUserBy(string $field, $value) : ?object
    {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM $wpdb->users WHERE $field = '$value'");
    }

    /**
     * @return string
     */
    public function getAdminEmail() : string
    {
        try {
            try {
                return wp_get_current_user()->user_email;
            } catch (\Throwable $th) {
                global $wpdb;
                return ($wpdb->get_row("SELECT * FROM {$wpdb->users} WHERE ID = 1"))->user_email;
            }
        } catch (\Throwable $th) {
            return get_option('admin_email');
        }
    }

    /**
     * @return array
     */
    public function getSiteInfos() : array
    {
        return [
            'email' => $this->getAdminEmail(),
            'pluginKey' => $this->pluginKey,
            'pluginVersion' => $this->pluginVersion,
            'siteUrl' => get_site_url(),
            'siteName' => get_bloginfo('name'),
        ];
    }

    /**
     * @param bool $form
     * @param string|null $wpOrgSlug
     * 
     * @return void
     */
    public function feedback(bool $form = true, ?string $wpOrgSlug = null) : void
    {
        $this->registerActivation([$this, '_sendActivationInfo']);

        if ($form) {
            global $pagenow;
            if ($pagenow === 'plugins.php') {
                add_action('admin_enqueue_scripts', function() {
                    wp_enqueue_style($this->pluginKey . '-feedback', $this->pluginUrl . 'app/PluginHero/templates/feedback.css', [], $this->pluginVersion);
                    wp_enqueue_script($this->pluginKey . '-feedback', $this->pluginUrl . 'app/PluginHero/templates/feedback.js', [], $this->pluginVersion);
                });
                add_action('admin_footer', function() use ($wpOrgSlug) {
                    $allowedHtml = wp_kses_allowed_html('post');
                    $allowedHtml['input'] = array(
                        'type' => true,
                        'value' => true,
                        'class' => true,
                        'name' => true,
                        'data-reason-code' => true,
                        'id' => true,
                    );
                    echo wp_kses($this->getTemplate('feedback', array_merge([
                        'wpOrgSlug' => $wpOrgSlug
                    ], $this->getSiteInfos())), $allowedHtml);
                });
            }

            $this->sendDeactivationInfoApi();
        } else {
            $this->registerDeactivation(function() {
                $this->_sendDeactivationInfo([
                    'reason' => 'Without feedback form',
                    'email' => $this->getAdminEmail(),
                ]);
            });
        }
    }

    /**
     * @return void
     */
    public function sendDeactivationInfoApi() : void
    {
        add_action('rest_api_init', function () {
            register_rest_route($this->pluginKey . '-deactivation', 'deactivate', [
                'callback' => [$this, '_sendDeactivationInfoApi'],
                'methods' => ['POST'],
                'permission_callback' => '__return_true'
            ]);
        });
    }

    /**
     * @return void
     */
    public function _sendDeactivationInfoApi() : void
    {
        delete_option($this->pluginKey . '_feedback_activation');
        if (function_exists('curl_version')) {
            try {
                $this->_sendDeactivationInfo([
                    'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : null,
                    'reason' =>  isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : null,
                    'reasonCode' =>  isset($_POST['reasonCode']) ? sanitize_text_field($_POST['reasonCode']) : null,
                ]);
            } catch (\Exception $e) {
                wp_send_json_success($e->getMessage());
                return;
            }
        }

        wp_send_json_success();
    }

    /**
     * @param string $message
     * 
     * @return bool
     */
    public function _sendFeedbackMessage(string $message) : bool
    {
        if (function_exists('curl_version')) {
            try {

                $data = array_merge([
                    'message' => 'message',
                ], $this->getSiteInfos());
                
                wp_remote_post($this->bpApiUrl . 'plugin-feedbacks', [
                    'body' => $data
                ]);

                return true;
            } catch (\Exception $th) {
                return false;
            }
        }

        return false;
    }
    
    /**
     * @return bool
     */
    public function _sendActivationInfo() : bool
    {
        if (function_exists('curl_version')) {
            try {

                $data = array_merge([
                    'process' => 'activation',
                ], $this->getSiteInfos());

                wp_remote_post($this->bpApiUrl . 'active-plugins', [
                    'body' => $data
                ]);

                return true;
            } catch (\Exception $th) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param array $params
     * 
     * @return bool
     */
    public function _sendDeactivationInfo(array $params = []) : bool
    {
        if (function_exists('curl_version')) {
            try {

                $data = array_merge([
                    'process' => 'deactivation',
                ], array_merge(
                    $this->getSiteInfos(), 
                    $params
                ));

                wp_remote_post($this->bpApiUrl . 'active-plugins', [
                    'body' => $data
                ]);

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }
    
    /**
     * @param string $key
     * @param string $file
     * @return void
     */
    public function registerAddon(string $key, string $file) : void
    {
        if (!isset(Plugin::$properties->addons)) {
            Plugin::$properties->addons = (object) [];
        }
        
        if (!isset(Plugin::$properties->addons->$key)) {
            Plugin::$properties->addons->$key = new Addon($key, $file);
        } else {
            throw new \Exception('Addon already registered');
        }
    }

    /**
     * @param string $file
     * @return object
     */
    public function getPluginData(string $file) : object
    {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        return (object) get_plugin_data($file);
    }
}