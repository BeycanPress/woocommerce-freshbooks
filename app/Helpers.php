<?php

declare(strict_types=1);

namespace BeycanPress\WooCommerce\FreshBooks;

// @phpcs:disable PSR1.Files.SideEffects

if (!function_exists('WP_Filesystem')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}

WP_Filesystem();

/**
 * Contains the commonly used ones for this plugin
 */
trait Helpers
{
    /**
     * @param string $name
     * @param \Closure $closure
     * @return void
     */
    public function addFunc(string $name, \Closure $closure): void
    {
        if (!isset(Loader::$properties->funcs)) {
            Loader::$properties->funcs = (object) [];
        }

        if (!isset(Loader::$properties->funcs->$name)) {
            Loader::$properties->funcs->$name = $closure;
        }
    }

    /**
     * @param string $name
     * @param mixed ...$args
     * @return mixed
     */
    public function callFunc(string $name, mixed ...$args): mixed
    {
        if (isset(Loader::$properties->funcs->$name)) {
            $closure = Loader::$properties->funcs->$name;
            return $closure(...$args);
        } else {
            throw new \Exception('Function not found');
        }
    }

    /**
     * @param string $property
     * @return mixed
     */
    public function __get(string $property): mixed
    {
        if (is_null(Loader::$properties)) {
            return null;
        }
        return isset(Loader::$properties->$property) ? Loader::$properties->$property : null;
    }

    /**
     * Easy use for get_option
     * @param string $setting
     * @param mixed $default
     * @return mixed
     */
    public function setting(string $setting = null, mixed $default = null): mixed
    {
        if (is_null(Loader::$settings)) {
            Loader::$settings = get_option($this->settingKey);
        }

        if (is_null($setting)) {
            return Loader::$settings;
        }

        return isset(Loader::$settings[$setting]) ? Loader::$settings[$setting] : $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function updateSetting(string $key, mixed $value): void
    {
        if (is_null(Loader::$settings)) {
            Loader::$settings = get_option($this->settingKey);
        }

        Loader::$settings[$key] = $value;

        update_option($this->settingKey, Loader::$settings);
    }

    /**
     * @param string $message
     * @param string $level
     * @param array<mixed> $context
     * @return void
     */
    public function debug(string $message, string $level = 'INFO', array $context = []): void
    {
        global $wp_filesystem;

        if ($this->debugging) {
            $content = $this->getTemplate('log', [
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'date' => $this->getTime()->format('Y-m-d H:i:s')
            ]);

            $file = $this->pluginDir . 'debug.log';

            if ($wp_filesystem->exists($file)) {
                $content = $wp_filesystem->get_contents($file) . $content;
                $wp_filesystem->put_contents($file, $content, FS_CHMOD_FILE);
            } else {
                $wp_filesystem->put_contents($file, $content, FS_CHMOD_FILE);
            }
        }
    }

    /**
     * @param string $datetime
     * @return \DateTime
     */
    public function getTime(string $datetime = 'now'): \DateTime
    {
        return new \DateTime($datetime, new \DateTimeZone(wp_timezone_string()));
    }

    /**
     * @param string $templateName
     * @param array<mixed> $args
     * @return string
     */
    public function getTemplate(string $templateName, array $args = []): string
    {
        extract($args);
        ob_start();
        include $this->pluginDir . 'app/' . $templateName . '.php';
        return ob_get_clean();
    }

    /**
     * @param string $path
     * @param array<string> $deps
     * @return string
     */
    public function addScript(string $path, array $deps = []): string
    {
        $f = substr($path, 0, 1);
        $key = explode('/', $path);
        $key = $this->pluginKey . '-' . preg_replace('/\..*$/', '', end($key));
        $middlePath = '/' === $f ? 'assets' : 'assets/js/';
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
     * @param array<string> $deps
     * @return string
     */
    public function addStyle(string $path, array $deps = []): string
    {
        $f = substr($path, 0, 1);
        $key = explode('/', $path);
        $key = $this->pluginKey . '-' . preg_replace('/\..*$/', '', end($key));
        $middlePath = '/' === $f ? 'assets' : 'assets/css/';
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
     * New nonce create method
     * @param string|null $externalKey
     * @return string
     */
    public function createNewNonce(?string $externalKey = null): string
    {
        $key = $this->pluginKey . '_nonce' . $externalKey;
        return wp_create_nonce($key);
    }

    /**
     * @param string $url
     * @return void
     */
    public function redirect(string $url): void
    {
        wp_redirect($url);
        exit();
    }

    /**
     * @return string
     */
    public function getCurrentUrl(): string
    {
        $siteURL = explode('/', get_site_url());
        $requestURL = explode(
            '/',
            esc_url_raw(
                isset($_SERVER['REQUEST_URI'])
                ? wp_unslash($_SERVER['REQUEST_URI'])
                : ''
            )
        );
        $currentURL = array_unique(array_merge($siteURL, $requestURL));
        return implode('/', $currentURL);
    }
}
