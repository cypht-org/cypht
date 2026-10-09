<?php

/**
 * Path based request routing for module sets
 * @package framework
 * @subpackage path_router
 */

/**
 * Module sets can serve requests for URL paths (for example /api/v1/...) instead of
 * the ?page= based dispatcher. A module set declares the paths it owns with the
 * 'allowed_routes' key returned from its setup.php file:
 *
 *   'allowed_routes' => [
 *       '/api/v1/*' => 'my_module',
 *   ]
 *
 * A pattern ending in '*' matches any path that starts with the text before it.
 * Other patterns match one exact path, with or without a trailing slash. When a
 * request matches, the routes.php file of the module set is loaded. It must return
 * a callable that receives the site config and the matched path, and that sends
 * the complete response. Requests that do not match continue to the page dispatcher.
 */
class Hm_Path_Router {

    /**
     * Find the request path relative to the directory Cypht is served from
     * @param array $server request server values ($_SERVER)
     * @return string|false path starting with '/', or false if the path is unsafe
     */
    public static function relative_path($server) {
        $uri = $server['REQUEST_URI'] ?? '';
        if (!is_string($uri) || $uri === '') {
            return false;
        }
        $path = explode('?', $uri, 2)[0];
        $path = explode('#', $path, 2)[0];
        if ($path === '' || $path[0] !== '/') {
            return false;
        }
        if (strpos($path, '..') !== false || strpos($path, '//') !== false ||
            strpos($path, '\\') !== false || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return false;
        }
        $base = self::base_path($server);
        if ($base !== '/') {
            if (strpos($path, $base) !== 0) {
                return false;
            }
            $path = '/'.substr($path, strlen($base));
        }
        return $path;
    }

    /**
     * Find the installation directory from the script name
     * @param array $server request server values
     * @return string directory with leading and trailing slash
     */
    public static function base_path($server) {
        $script = $server['SCRIPT_NAME'] ?? '/index.php';
        if (!is_string($script) || $script === '' || $script[0] !== '/') {
            return '/';
        }
        $dir = str_replace('\\', '/', dirname($script));
        if ($dir === '/' || $dir === '.' || $dir === '') {
            return '/';
        }
        return rtrim($dir, '/').'/';
    }

    /**
     * Find the module set that owns a path
     * @param array $routes pattern => module set name
     * @param string $path relative request path
     * @return string|false module set name
     */
    public static function match($routes, $path) {
        if (!is_array($routes) || !is_string($path)) {
            return false;
        }
        $best = false;
        $best_len = -1;
        foreach ($routes as $pattern => $set) {
            if (!is_string($pattern) || !is_string($set) || $pattern === '' || $pattern[0] !== '/') {
                continue;
            }
            if (substr($pattern, -1) === '*') {
                $prefix = substr($pattern, 0, -1);
                $matched = strpos($path, $prefix) === 0;
            } else {
                $matched = rtrim($path, '/') === rtrim($pattern, '/');
                if ($pattern === '/') {
                    $matched = $path === '/';
                }
            }
            /* the most specific (longest) pattern wins */
            if ($matched && strlen($pattern) > $best_len) {
                $best = $set;
                $best_len = strlen($pattern);
            }
        }
        return $best;
    }

    /**
     * Serve the request if a module set owns its path
     * @param object $config site config
     * @param array $filters combined module set filters
     * @param array|null $server request server values, defaults to $_SERVER
     * @return bool true if a module set handled the request
     */
    public static function dispatch($config, $filters, $server = null) {
        if (empty($filters['allowed_routes']) || !is_array($filters['allowed_routes'])) {
            return false;
        }
        $server = $server ?? $_SERVER;
        $path = self::relative_path($server);
        if ($path === false || $path === '/') {
            return false;
        }
        $set = self::match($filters['allowed_routes'], $path);
        if ($set === false || !preg_match('/^[a-z0-9_]+$/', $set)) {
            return false;
        }
        $modules = $config->get_modules();
        if (!is_array($modules) || !in_array($set, $modules, true)) {
            return false;
        }
        $file = APP_PATH.'modules/'.$set.'/routes.php';
        if (!is_readable($file)) {
            Hm_Debug::add(sprintf('Route handler for module set %s not found', $set), 'warning');
            return false;
        }
        $handler = require $file;
        if (!is_callable($handler)) {
            Hm_Debug::add(sprintf('Route handler for module set %s is not callable', $set), 'warning');
            return false;
        }
        Hm_Debug::add(sprintf('Path %s routed to module set %s', $path, $set), 'info');
        $handler($config, $path);
        return true;
    }
}
