<?php

try {
    
    $scriptPath = dirname(__FILE__);
    $path = realpath($scriptPath . '/./');
    $filepath = explode("wp-content", $path);
    define('WP_USE_THEMES', false);
    require ('' . $filepath[0] . '/wp-blog-header.php');
    
    if (! defined('ABSPATH')) {
        exit();
    }
    
    $settings = (object) get_option('woocommerce_valsto_payment_settings', array());
    
    $settings->enabled = ($settings->enabled === 'yes') ? true : false;
    $settings->debug = ($settings->debug === 'yes') ? true : false;
    $settings->test_mode_valsto = ($settings->test_mode_valsto === 'yes') ? true : false;
    $settings->capture = ($settings->capture === 'yes') ? true : false;
    $settings->http_proxy = ($settings->http_proxy === 'yes') ? true : false;
    
    if ($settings->debug) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if ($settings->debug) {
            echo $_SERVER['REQUEST_METHOD'];
        }
        exit(' Method is not allowed.');
    }
    
    if (! $settings->enabled) {
        if ($settings->debug) {
            echo "Plugin is not enabled.";
        }
        exit();
    }
    
    if (empty($settings->merchant_account_valsto) || empty($settings->merchant_account_pk_valsto)) {
        echo "Plugin bad configured. <br/>";
        if ($settings->debug) {
            echo "Please set a valid <b>vMerchant Account</b> and <b>Public Key</b> in the Admin Settings";
        }
        exit();
    }
    
    $url = $_POST['vproxy'];
    
    $_POST['merchant'] = $settings->merchant_account_valsto;
    $_POST['api_key'] = $settings->merchant_account_pk_valsto;
    $_POST['currency_code'] = 'USD';
    $_POST['allowed_domain'] = get_site_url();
    
    $opts = array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($_POST)
        )
    );
    
    $context = stream_context_create($opts);
    
    set_error_handler("valsto_proxy_error_handler");
    
    $result = file_get_contents($url, false, $context);
    
    $cookies = array();
    foreach ($http_response_header as $hdr) {
        if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
            parse_str($matches[1], $tmp);
            $cookies += $tmp;
        }
    }
    
    foreach ($cookies as $key => $value) {
        setcookie($key, $value);
    }
    
    if ($settings->http_proxy) {
        preg_match('/action=".*"\s/', $result, $post_url);
        
        $httpProxy = sprintf('%shttp-proxy.php', plugin_dir_url(__FILE__));
        
        $result = str_replace($post_url, sprintf('action="%s"', $httpProxy), $result);
        if (isset($post_url[0])) {
            $post_url = trim(str_replace('action=', '', $post_url[0]));
            $result = str_replace('</form>', sprintf('<input type="hidden" value=%s name="url_proxy" /></form>', $post_url), $result);
        }
    }
    
    echo $result;
} catch (ErrorException $e) {
    include ('templates/error.html');
    if (isset($settings) && $settings->debug) {
        echo "<b>Error:</b>" . $e->getMessage();
    }
}

restore_error_handler();

// FUNCTIONS

/**
 * 
 * @param string $errno
 * @param string $errstr
 * @param string $errfile
 * @param string $errline
 * @param array $errcontext
 * @throws ErrorException
 */
function valsto_proxy_error_handler($errno, $errstr, $errfile, $errline, array $errcontext)
{
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

/**
 * 
 * @param string $url
 * @return string
 */
function get_http_response_code($url)
{
    $headers = get_headers($url);
    return substr($headers[0], 9, 3);
}