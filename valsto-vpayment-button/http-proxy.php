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
    
    $cookieJID = "";
    
    if (isset($_COOKIE['JSESSIONID'])) {
        $cookieJID = sprintf("\r\nCookie: JSESSIONID=%s\r\n", $_COOKIE['JSESSIONID']);
    }
    
    $url = $_POST['url_proxy'];
    
    // $url = str_replace(array("http://", "//"), "https://",$url, 1);
    
    if (strpos($url, "http://") !== false) {
        $url = str_replace("http://", "https://", $url);
    } else {
        if (strpos($url, "//") !== false) {
            $url = str_replace("//", "https://", $url);
        }
    }
    
    $opts = array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded\r\nContent-Language: en-US' . $cookieJID,
            'content' => http_build_query($_POST)
        )
    );
    
    $context = stream_context_create($opts);
    
    set_error_handler("valsto_proxy_error_handler");
    
    session_write_close(); // unlock the file
    $result = file_get_contents($url, false, $context);
    session_start();
    
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
    
    preg_match('/action=".*"\s/', $result, $post_url);
    
    $httpProxy = sprintf('%shttp-proxy.php', plugin_dir_url(__FILE__));
    
    $result = str_replace($post_url, sprintf('action="%s"', $httpProxy), $result);
    
    if (isset($post_url[0])) {
        $post_url = trim(str_replace('action=', '', $post_url[0]));
        $result = str_replace('</form>', sprintf('<input type="hidden" value=%s name="url_proxy" /></form>', $post_url), $result);
    }
    
    echo $result;
} catch (ErrorException $e) {
    include ('templates/error.html');
    if (isset($settings) && $settings->debug) {
        echo "<b>Error http-proxy:</b>" . $e->getMessage();
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